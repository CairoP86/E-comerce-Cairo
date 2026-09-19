<?php

namespace App\Services;

use App\Availability\Availability;
use App\Availability\AvailabilityState;
use App\Availability\HoldUnavailable;
use App\Contracts\CartStore;
use App\Contracts\ProductAvailability;
use App\Models\Product;
use App\Models\StockHold;
use App\Support\CartHolder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CartService
{
    public const MAX_QUANTITY = 99;

    public const MAX_LINES = 50;

    public function __construct(private CartStore $store, private StockHolds $holds, private ProductAvailability $availability) {}

    public function summary(): array
    {
        $cart = $this->store->read();

        return ['units' => array_sum(array_column($cart['items'], 'quantity')), 'revision' => $cart['revision']];
    }

    public function snapshot(): array
    {
        $cart = $this->store->read();
        $holder = CartHolder::current();
        $products = Product::storefrontVisible()->whereIn('id', array_column($cart['items'], 'product_id'))->with('images')->get()->keyBy('id');
        $availability = $this->availability->forProducts($products->keys()->all(), $holder);
        $holds = $this->holds->active($holder, $products->keys()->all());
        $lines = [];
        $subtotal = 0;
        $blocked = false;
        foreach ($cart['items'] as $id => $item) {
            $product = $products->get($item['product_id']);
            $reason = match (true) {
                ! $product => 'unpublished',
                default => $this->reason($product, $item['quantity'], $cart['currency'], $availability[$product->id] ?? Availability::unknown(), $holds->get($product->id)),
            };
            $available = $reason === null;
            $lineSubtotal = $available ? $product->price_minor * $item['quantity'] : null;
            $subtotal += $lineSubtotal ?? 0;
            $blocked = $blocked || ! $available;
            $image = $product?->images->firstWhere('is_primary', true) ?? $product?->images->first();
            // Explicit public projection. Hidden products do not disclose current metadata.
            $lines[] = [
                'id' => $id, 'quantity' => $item['quantity'], 'available' => $available, 'reason' => $reason,
                'name' => $product?->name ?? 'Producto no disponible', 'slug' => $product?->slug,
                'is_demo' => $product?->is_demo ?? false,
                'image' => $image ? ['url' => route('catalog.image', $image), 'alt' => $image->alt] : null,
                'unit_price_minor' => $available ? $product->price_minor : null,
                'subtotal_minor' => $lineSubtotal, 'currency' => $available ? $cart['currency'] : null,
            ];
        }
        $expiry = $holds->sortBy('expires_at')->first()?->expires_at;

        return [
            'lines' => $lines, 'currency' => $cart['currency'], 'units' => array_sum(array_column($cart['items'], 'quantity')),
            'subtotal_minor' => $subtotal, 'total_minor' => $blocked ? null : $subtotal,
            'has_unavailable' => $blocked, 'revision' => $cart['revision'],
            'max_quantity' => self::MAX_QUANTITY, 'max_lines' => self::MAX_LINES,
            'hold_expires_at' => $expiry?->toISOString(),
        ];
    }

    public function mutate(string $action, array $data, ?string $line = null): bool
    {
        $cart = $this->store->read();
        $fingerprint = hash('sha256', json_encode([$action, $line, $data], JSON_THROW_ON_ERROR));
        if (isset($cart['mutations'][$data['mutation_id']])) {
            if (! hash_equals($cart['mutations'][$data['mutation_id']], $fingerprint)) {
                $this->fail('Esta solicitud ya se utilizó. Actualiza el carrito antes de intentarlo de nuevo.');
            }

            return false;
        }
        if ((int) $data['revision'] !== $cart['revision']) {
            $this->fail('Tu carrito cambió en otra pestaña. Revisa su contenido actualizado e inténtalo de nuevo.');
        }
        $holder = CartHolder::ensure();
        if ($action === 'add') {
            $product = Product::storefrontVisible()->where('slug', $data['product_slug'])->first();
            if (! $product) {
                $this->fail('Este producto ya no está disponible en el catálogo.');
            }
            if ($cart['currency'] && $cart['currency'] !== $product->currency) {
                $this->fail('Tu carrito contiene productos en '.$cart['currency'].'. Vacíalo antes de agregar productos en otra moneda.');
            }
            $existing = collect($cart['items'])->search(fn ($item) => $item['product_id'] === $product->id);
            if ($existing === false && count($cart['items']) >= self::MAX_LINES) {
                $this->fail('Has alcanzado el límite de 50 productos distintos en el carrito.');
            }
            $quantity = (int) $data['quantity'] + ($existing !== false ? $cart['items'][$existing]['quantity'] : 0);
            if ($quantity > self::MAX_QUANTITY) {
                $this->fail('El límite temporal es de 99 unidades por producto. No indica existencias de inventario.');
            }
            $this->hold($holder, $product, $quantity);
            $id = $existing !== false ? $existing : (string) Str::uuid();
            $cart['items'][$id] = ['product_id' => $product->id, 'quantity' => $quantity];
            $cart['currency'] = $product->currency;
        } elseif (in_array($action, ['update', 'remove'], true)) {
            abort_unless(isset($cart['items'][$line]), 404);
            if ($action === 'remove') {
                $this->holds->release($holder, $cart['items'][$line]['product_id']);
                unset($cart['items'][$line]);
            } else {
                $product = Product::storefrontVisible()->whereKey($cart['items'][$line]['product_id'])->first();
                if (! $product || $product->currency !== $cart['currency']) {
                    $this->fail('Este producto cambió y ya no puede actualizarse. Retíralo del carrito.');
                }
                $this->hold($holder, $product, (int) $data['quantity']);
                $cart['items'][$line]['quantity'] = (int) $data['quantity'];
            }
        } elseif ($action === 'clear') {
            $this->holds->releaseAll($holder);
            $cart['items'] = [];
        } else {
            throw new \LogicException('Unknown cart operation.');
        }
        if (! $cart['items']) {
            $cart['currency'] = null;
        }
        $cart['revision']++;
        $cart['mutations'][$data['mutation_id']] = $fingerprint;
        $cart['mutations'] = array_slice($cart['mutations'], -100, null, true);
        $this->store->write($cart);

        return true;
    }

    /** Why a public product line cannot be bought now; null when it can. */
    private function reason(Product $product, int $quantity, ?string $currency, Availability $availability, ?StockHold $hold): ?string
    {
        return match (true) {
            $product->currency !== $currency => 'currency_changed',
            $availability->state !== AvailabilityState::Available || $quantity > $availability->quantity => 'out_of_stock',
            ! $hold || $hold->quantity !== $quantity || $hold->supplier_product_id !== $availability->offerId => 'hold_expired',
            default => null,
        };
    }

    private function hold(string $holder, Product $product, int $quantity): void
    {
        try {
            $this->holds->reserve($holder, $product, $quantity);
        } catch (HoldUnavailable $exception) {
            $this->fail($exception->getMessage());
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['cart' => $message]);
    }
}
