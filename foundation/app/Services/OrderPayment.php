<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\StockHold;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Support\Audit;
use App\Support\OrderNumber;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registers a payment confirmed outside the platform, turning the order's temporary hold into a
 * permanent sale: the offer stock is discounted and the hold retired. All or nothing: if any unit
 * is no longer there, nothing is written and the operator gets the reason to resolve it by hand.
 *
 * Lock order is order, then offers (by id), then holds. Checkout locks offers then holds and never
 * locks an existing order, so no inverted ordering is introduced. Nothing is sent to a supplier.
 */
class OrderPayment
{
    /** @return bool false when the order was already paid */
    public function markPaid(Order $order, User $actor): bool
    {
        return DB::transaction(function () use ($order, $actor) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === OrderStatus::Paid) {
                return false;
            }
            if ($locked->status !== OrderStatus::PendingPayment) {
                $this->refuse(['Solo un pedido pendiente de pago puede marcarse como pagado.']);
            }
            $now = CarbonImmutable::now()->startOfSecond();
            $items = $locked->items()->get();
            $ownHolds = StockHold::where('order_id', $locked->id)->get()->keyBy('product_id');
            if ($items->contains(fn ($item) => ! $ownHolds->has($item->product_id))) {
                $this->refuse(['No se puede registrar el pago de este pedido: es anterior a las reservas y no se sabe de qué oferta salió cada unidad. Descontá el stock a mano.']);
            }

            $offerIds = $ownHolds->pluck('supplier_product_id')->unique()->sort()->values();
            $offers = SupplierProduct::whereIn('id', $offerIds)->with('supplier:id,active,name')->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $competing = StockHold::whereIn('supplier_product_id', $offerIds)->where('expires_at', '>', $now)
                ->where(fn ($q) => $q->whereNull('order_id')->orWhere('order_id', '!=', $locked->id))
                ->orderBy('id')->lockForUpdate()->get()->groupBy('supplier_product_id');

            [$plan, $problems] = $this->plan($items, $ownHolds, $offers, $competing, $now);
            if ($problems) {
                $this->refuse(['No se puede registrar el pago. No se modificó nada.', ...$problems]);
            }

            foreach ($plan as $step) {
                $from = $step['offer']->stock;
                $step['offer']->forceFill(['stock' => $from - $step['quantity']])->save();
                // Retired, not deleted: order holds stay as history, and an active one would make
                // the unit count twice now that the stock itself has been discounted.
                $step['hold']->forceFill(['expires_at' => $now])->save();
                // A payment beats a cart: that checkout will find its hold gone and refuse.
                $step['revoke']->each->delete();
                Audit::record('order.stock_committed', $actor->id, metadata: ['entity_type' => 'supplier_products', 'entity_id' => $step['offer']->id, 'quantity' => $step['quantity'], 'from_stock' => $from, 'to_stock' => $from - $step['quantity']]);
            }

            $from = $locked->status;
            $locked->forceFill(['status' => OrderStatus::Paid])->save();
            DB::table('order_status_history')->insert(['order_id' => $locked->id, 'from_status' => $from->value, 'to_status' => OrderStatus::Paid->value, 'actor_id' => $actor->id, 'created_at' => now()]);
            Audit::record('order.marked_paid', $actor->id, metadata: ['entity_type' => 'order', 'entity_id' => $locked->id, 'from_status' => $from->value, 'to_status' => OrderStatus::Paid->value]);

            return true;
        }, 3);
    }

    /**
     * Checks every item before anything is written.
     *
     * @return array{0: list<array>, 1: list<string>}
     */
    private function plan(Collection $items, Collection $ownHolds, Collection $offers, Collection $competing, CarbonImmutable $now): array
    {
        $plan = [];
        $problems = [];
        foreach ($items as $item) {
            $hold = $ownHolds->get($item->product_id);
            $offer = $offers->get($hold->supplier_product_id);
            $others = $competing->get($offer->id, collect());
            $pending = (int) $others->whereNotNull('order_id')->sum('quantity');
            // Newest carts give way first: the last to reserve is the first to lose the unit.
            $carts = $others->whereNull('order_id')->sortByDesc('id')->values();
            $expired = $hold->expires_at->lessThanOrEqualTo($now)
                ? ' La reserva de este pedido venció el '.CarbonImmutable::instance($hold->expires_at)->setTimezone(OrderNumber::TIMEZONE)->format('d/m/Y \a \l\a\s g:i a').'.'
                : '';

            $problem = match (true) {
                ! $offer->active || ! $offer->supplier?->active => 'la oferta de '.($offer->supplier?->name ?? 'este proveedor').' está desactivada. Revisá por qué antes de registrar el pago.',
                $offer->stock === null => 'la oferta no tiene stock confirmado. Actualizala antes de registrar el pago.',
                $offer->availability !== 'available' => 'la oferta está marcada como no disponible. Actualizala antes de registrar el pago.',
                $offer->stock - $pending < $item->quantity => sprintf(
                    'el pedido necesita %d y hay %d disponibles: %s.%s Confirmá el stock con el proveedor antes de registrar el pago.',
                    $item->quantity, max(0, $offer->stock - $pending),
                    $pending > 0 ? 'la unidad quedó comprometida con otro pedido pendiente' : 'no queda stock registrado',
                    $expired,
                ),
                default => null,
            };
            if ($problem !== null) {
                $problems[] = $item->name.': '.$problem;

                continue;
            }

            // Only as many carts as needed give way; the rest keep their reservation.
            $free = $offer->stock - $pending - (int) $carts->sum('quantity');
            $revoke = collect();
            foreach ($carts as $cart) {
                if ($free >= $item->quantity) {
                    break;
                }
                $revoke->push($cart);
                $free += $cart->quantity;
            }
            $plan[] = ['offer' => $offer, 'hold' => $hold, 'quantity' => $item->quantity, 'revoke' => $revoke];
        }

        return [$plan, $problems];
    }

    /** @param list<string> $lines */
    private function refuse(array $lines): never
    {
        throw ValidationException::withMessages(['order' => implode("\n", $lines)]);
    }
}
