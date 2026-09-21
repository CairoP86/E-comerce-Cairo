<?php

namespace App\Services;

use App\Availability\Availability;
use App\Availability\AvailabilityState;
use App\Availability\HoldUnavailable;
use App\Contracts\CartStore;
use App\Contracts\ProductAvailability;
use App\Delivery\DeliveryQuoter;
use App\Delivery\DeliveryUnavailable;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\Audit;
use App\Support\CartHolder;
use App\Support\CostaRicaTerritories;
use App\Support\ValueAddedTax;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public function __construct(private CartStore $store, private ProductAvailability $availability, private StockHolds $holds, private DeliveryQuoter $delivery) {}

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['checkout' => $message]);
    }

    public function quote(bool $lock = false, ?string $cantonCode = null): array
    {
        $cart = $this->store->read();
        if (! $cart['items'] || count($cart['items']) > CartService::MAX_LINES) {
            $this->fail('Tu carrito está vacío o debe revisarse. Vuelve al carrito.');
        }
        $ids = array_column($cart['items'], 'product_id');
        $query = Product::query()->whereIn('id', $ids)->orderBy('id');
        $products = ($lock ? $query->lockForUpdate() : $query)->get()->keyBy('id');
        // Lock taxonomy before the visibility query so publication cannot change during commit.
        if ($lock) {
            $categories = DB::table('categories')->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $brands = DB::table('brands')->whereIn('id', $products->pluck('brand_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            // Use current locked rows, not an older MySQL repeatable-read snapshot.
            $visible = $products->filter(function ($product) use ($categories, $brands) {
                if ($product->status !== 'published' || $brands->get($product->brand_id)?->status !== 'published') {
                    return false;
                }
                $categoryId = $product->category_id;
                $seen = [];
                while ($categoryId) {
                    $category = $categories->get($categoryId);
                    if (! $category || $category->status !== 'published' || isset($seen[$categoryId])) {
                        return false;
                    }
                    $seen[$categoryId] = true;
                    $categoryId = $category->parent_id;
                }

                return true;
            })->pluck('id')->all();
        } else {
            $visible = Product::publiclyVisible()->whereIn('id', $ids)->pluck('id')->all();
        }
        $holder = CartHolder::current();
        // Offers and holds are locked after products and taxonomy; hold writers only lock offers, then holds.
        $availability = $this->availability->forProducts($ids, $holder, $lock);
        $holds = $this->holds->active($holder, $ids, $lock);
        $items = [];
        foreach ($cart['items'] as $item) {
            $product = $products->get($item['product_id']);
            if (! $product || ! in_array($product->id, $visible, true) || $product->currency !== $cart['currency'] || ! in_array($product->currency, ['CRC', 'USD'], true) || ! is_int($item['quantity']) || $item['quantity'] < 1 || $item['quantity'] > CartService::MAX_QUANTITY) {
                $this->fail('Hay productos que ya no se pueden confirmar. Revisa el carrito antes de continuar.');
            }
            $current = $availability[$product->id] ?? Availability::unknown();
            $hold = $holds->get($product->id);
            if ($current->state !== AvailabilityState::Available || $item['quantity'] > $current->quantity || ! $hold || $hold->quantity !== $item['quantity'] || $hold->supplier_product_id !== $current->offerId) {
                $this->fail('La disponibilidad o la reserva de algunos productos cambió. Revisa el carrito antes de continuar.');
            }
            $items[] = ['product_id' => $product->id, 'name' => $product->name, 'sku' => $product->sku, 'is_demo' => $product->is_demo, 'quantity' => $item['quantity'], 'unit_price_minor' => $product->price_minor, 'subtotal_minor' => $product->price_minor * $item['quantity'], 'currency' => $product->currency];
        }

        $subtotal = array_sum(array_column($items, 'subtotal_minor'));
        // Currency gate: delivery rates are in colones and no implicit conversion exists (ADR-002).
        if ($cart['currency'] !== 'CRC') {
            $this->fail('Por ahora solo procesamos pedidos en colones. Escríbenos para coordinar una compra en otra moneda.');
        }
        $shipping = null;
        if ($cantonCode !== null) {
            try {
                $shipping = $this->delivery->quote($cantonCode, $subtotal, $cart['currency']);
            } catch (DeliveryUnavailable $exception) {
                $this->fail($exception->getMessage());
            }
        }

        return [
            'items' => $items, 'currency' => $cart['currency'], 'subtotal_minor' => $subtotal,
            'shipping' => $shipping?->toPublic(),
            'shipping_rate_set_id' => $shipping?->rateSetId, 'shipping_zone' => $shipping?->zone->value,
            'total_minor' => $subtotal + ($shipping?->amountMinor ?? 0),
            'revision' => $cart['revision'],
        ];
    }

    private function digest(array $data): string
    {
        return hash_hmac('sha256', json_encode($data, JSON_THROW_ON_ERROR), config('app.key'));
    }

    public function ownerHash(): string
    {
        if (! session()->has('checkout_owner')) {
            session()->put('checkout_owner', Str::random(64));
        }

        return $this->digest([session('checkout_owner')]);
    }

    public function review(?string $cantonCode = null): array
    {
        $quote = $this->quote(cantonCode: $cantonCode);
        $hash = $this->digest($quote);
        $review = session('checkout_review');
        if (! $review || $review['hash'] !== $hash) {
            $review = ['token' => (string) Str::uuid(), 'hash' => $hash];
            session()->put('checkout_review', $review);
        }
        $this->ownerHash();
        unset($quote['revision'], $quote['shipping_rate_set_id'], $quote['shipping_zone']);
        $quote['items'] = array_map(function ($item) {
            unset($item['product_id']);

            return $item;
        }, $quote['items']);

        // Display-only breakdown derived from the product subtotal. It is not part of the quote,
        // so it never enters the review hash nor the amount confirm() persists (ADR-008).
        $quote['tax'] = ValueAddedTax::breakdown($quote['subtotal_minor']);

        return [...$quote, 'token' => $review['token']];
    }

    public function confirm(array $data, ?User $user): Order
    {
        $key = hash('sha256', $data['token']);
        $owner = $this->ownerHash();
        $requestHash = $this->digest($data);
        $order = DB::transaction(function () use ($data, $key, $owner, $requestHash, $user) {
            $existing = Order::where('checkout_key', $key)->first();
            if ($existing) {
                abort_unless(hash_equals($existing->owner_hash, $owner), 404);
                if (! hash_equals($existing->request_hash, $requestHash)) {
                    $this->fail('Esta confirmación ya fue utilizada con otros datos.');
                }

                return $existing;
            }
            $review = session('checkout_review');
            if (! $review || ! hash_equals($review['token'], $data['token'])) {
                $this->fail('La revisión venció. Actualiza el checkout antes de confirmar.');
            }
            $quote = $this->quote(true, $data['canton_code']);
            if (! hash_equals($review['hash'], $this->digest($quote))) {
                $this->fail('El carrito o los precios cambiaron. Revisa el resumen actualizado y vuelve a confirmar.');
            }
            $territory = CostaRicaTerritories::find($data['province_code'], $data['canton_code'], $data['district_code']);
            $order = new Order;
            $order->forceFill([
                'id' => (string) Str::uuid(), 'number' => 'TC-'.strtoupper(bin2hex(random_bytes(10))),
                'user_id' => $user?->role === Role::Customer ? $user->id : null,
                'checkout_key' => $key, 'owner_hash' => $owner, 'request_hash' => $requestHash,
                'cart_revision' => $quote['revision'], 'status' => OrderStatus::PendingPayment,
                'first_name' => $data['first_name'], 'last_name' => $data['last_name'], 'email' => $data['email'], 'phone' => $data['phone'],
                'currency' => $quote['currency'], 'subtotal_minor' => $quote['subtotal_minor'],
                'shipping_minor' => $quote['shipping']['amount_minor'], 'shipping_zone' => $quote['shipping_zone'],
                'delivery_rate_set_id' => $quote['shipping_rate_set_id'], 'total_minor' => $quote['total_minor'],
            ]);
            $order->save();
            $order->items()->createMany($quote['items']);
            try {
                $this->holds->convert(CartHolder::current() ?? '', $order, $quote['items']);
            } catch (HoldUnavailable $exception) {
                $this->fail($exception->getMessage());
            }
            $order->address()->create([
                'country_code' => 'CR', 'province_code' => $data['province_code'], 'canton_code' => $data['canton_code'], 'district_code' => $data['district_code'],
                'province' => $territory['province'], 'canton' => $territory['canton'], 'district' => $territory['name'], 'territory_version' => 'IGN-2026',
                'exact_address' => $data['exact_address'], 'additional' => $data['additional'] ?? null,
            ]);
            DB::table('order_status_history')->insert(['order_id' => $order->id, 'from_status' => null, 'to_status' => OrderStatus::PendingPayment->value, 'actor_id' => $user?->id, 'created_at' => now()]);
            Audit::record('order.created', $user?->id, metadata: ['entity_type' => 'order', 'entity_id' => $order->id, 'to_status' => OrderStatus::PendingPayment->value]);

            return $order;
        }, 3);
        // Only after commit. A retry must never clear a newer cart.
        $cart = $this->store->read();
        if ($cart['revision'] === $order->cart_revision) {
            $cart['items'] = [];
            $cart['currency'] = null;
            $cart['revision']++;
            $this->store->write($cart);
        }

        return $order;
    }
}
