<?php

namespace App\Services;

use App\Availability\Availability;
use App\Availability\AvailabilityConfig;
use App\Availability\AvailabilityState;
use App\Availability\HoldUnavailable;
use App\Availability\PreferredOfferAvailability;
use App\Contracts\ProductAvailability;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockHold;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Local soft holds against double selling (V1-B-SCOPE §5). Writers lock the offer row first,
 * so concurrent holds on the same offer are serialized. Expiry needs no job: a hold only
 * counts while expires_at is in the future; holds:prune just removes old rows.
 */
class StockHolds
{
    public function __construct(private ProductAvailability $availability) {}

    /** Create or resize the holder's hold. Resizing keeps the original expiry (D7). */
    public function reserve(string $holder, Product $product, int $quantity): void
    {
        DB::transaction(function () use ($holder, $product, $quantity) {
            $availability = $this->availability->forProducts([$product->id], $holder, true)[$product->id] ?? Availability::unknown();
            if (! $availability->state->isPubliclyVisible()) {
                throw new HoldUnavailable('Este producto ya no está disponible en el catálogo.');
            }
            if ($availability->state !== AvailabilityState::Available) {
                throw new HoldUnavailable('Este producto está agotado.');
            }
            if ($quantity > $availability->quantity) {
                throw new HoldUnavailable($availability->quantity === 1 ? 'Solo queda 1 unidad disponible.' : 'Solo quedan '.$availability->quantity.' unidades disponibles.');
            }
            $now = PreferredOfferAvailability::now();
            $hold = StockHold::where('holder', $holder)->where('product_id', $product->id)->lockForUpdate()->first();
            $keepExpiry = $hold && $hold->supplier_product_id === $availability->offerId && $hold->expires_at->greaterThan($now);
            $hold ??= new StockHold;
            $hold->forceFill(['holder' => $holder, 'product_id' => $product->id, 'supplier_product_id' => $availability->offerId, 'quantity' => $quantity, 'order_id' => null]);
            if (! $keepExpiry) {
                $hold->expires_at = $now->addMinutes(AvailabilityConfig::holdMinutes());
            }
            $hold->save();
        }, 3);
    }

    public function release(?string $holder, int $productId): void
    {
        if ($holder !== null) {
            StockHold::where('holder', $holder)->where('product_id', $productId)->whereNull('order_id')->delete();
        }
    }

    public function releaseAll(?string $holder): void
    {
        if ($holder !== null) {
            StockHold::where('holder', $holder)->whereNull('order_id')->delete();
        }
    }

    /** @return Collection<int, StockHold> active holds keyed by product id */
    public function active(?string $holder, array $productIds, bool $lock = false): Collection
    {
        if ($holder === null || ! $productIds) {
            return collect();
        }
        $query = StockHold::where('holder', $holder)->whereIn('product_id', $productIds)->where('expires_at', '>', PreferredOfferAvailability::now())->orderBy('id');

        return ($lock ? $query->lockForUpdate() : $query)->get()->keyBy('product_id');
    }

    /**
     * Attach the holder's active holds to an order, keeping their expiry (D8). Call inside the
     * checkout transaction after availability was verified under lock.
     */
    public function convert(string $holder, Order $order, array $items): void
    {
        $now = PreferredOfferAvailability::now();
        foreach ($items as $item) {
            $updated = StockHold::where('holder', $holder)->where('product_id', $item['product_id'])->whereNull('order_id')
                ->where('expires_at', '>', $now)->where('quantity', $item['quantity'])
                ->update(['holder' => null, 'order_id' => $order->id, 'updated_at' => $now]);
            if ($updated !== 1) {
                throw new HoldUnavailable('La reserva de algunos productos venció. Revisa el carrito antes de continuar.');
            }
        }
    }

    /** Remove expired cart holds. Order holds are kept as history. */
    public function prune(): int
    {
        return StockHold::whereNull('order_id')->where('expires_at', '<=', PreferredOfferAvailability::now())->delete();
    }
}
