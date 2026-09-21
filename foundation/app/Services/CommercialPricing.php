<?php

namespace App\Services;

use App\Models\Category;
use App\Models\PricingRule;
use App\Models\Product;
use App\Models\ProductCommercialSetting;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Support\Audit;
use App\Support\CommercialDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommercialPricing
{
    public function quote(Product $product): array
    {
        $setting = ProductCommercialSetting::where('product_id', $product->id)->first();
        $offer = $setting?->preferred_offer_id ? SupplierProduct::where('product_id', $product->id)->find($setting->preferred_offer_id) : null;
        $units = $setting?->multiplier_units;
        $ruleName = $units !== null ? 'Excepción del producto' : null;
        $category = $product->category_id;
        $seen = [];
        while ($units === null && $category && ! isset($seen[$category])) {
            $seen[$category] = true;
            $row = Category::find($category);
            $ruleId = DB::table('category_pricing_rules')->where('category_id', $category)->value('pricing_rule_id');
            if ($ruleId && ($rule = PricingRule::find($ruleId))) {
                $units = $rule->multiplier_units;
                $ruleName = $rule->name.' · '.$row->name;
            }
            $category = $row?->parent_id;
        }
        $reason = match (true) {
            ! $offer => 'Selecciona una oferta preferida.',
            ! $offer->active || ! $offer->supplier->active => 'La oferta o su proveedor están inactivos.',
            $offer->cost_minor === null => 'El costo de esta oferta todavía no está confirmado.',
            $offer->currency !== $product->currency => 'La moneda del costo no coincide con la del producto. No hay conversión automática.',
            $units === null => 'Asigna una regla a la categoría o una excepción al producto.',
            default => null,
        };
        $price = $reason === null ? CommercialDecimal::suggested($offer->cost_minor, $units) : null;
        // Colón prices carry no céntimos in practice, so a suggestion never proposes them.
        // Other currencies keep their minor unit: cents are a real amount in dollars.
        if ($price !== null && $product->currency === 'CRC') {
            $price = CommercialDecimal::wholeUnits($price);
        }
        if ($price !== null && ($price < 1 || $price > 999999999999)) {
            $reason = 'El resultado supera los límites del precio público.';
            $price = null;
        }
        $data = ['product_id' => $product->id, 'preferred_offer_id' => $setting?->preferred_offer_id, 'cost_minor' => $offer?->cost_minor, 'cost_currency' => $offer?->currency, 'multiplier' => $units === null ? null : CommercialDecimal::format($units), 'rule' => $ruleName, 'suggested_minor' => $price, 'published_minor' => $product->price_minor, 'previous_price_minor' => $product->previous_price_minor, 'currency' => $product->currency, 'reason' => $reason,
            'offer_updated_at' => $offer?->updated_at?->toISOString(), 'observed_at' => $offer?->observed_at?->toISOString(), 'availability' => $offer?->availability, 'stock' => $offer?->stock];
        $data['revision'] = hash_hmac('sha256', json_encode($data, JSON_THROW_ON_ERROR), config('app.key'));

        return $data;
    }

    public function apply(Product $product, string $revision, int $actor): void
    {
        DB::transaction(function () use ($product, $revision, $actor) {
            $product = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            // Lock calculation inputs against product, offer, supplier and rule writers.
            // Broad locks are deliberate for the small, manually maintained catalog.
            PricingRule::orderBy('id')->lockForUpdate()->get();
            ProductCommercialSetting::where('product_id', $product->id)->lockForUpdate()->get();
            SupplierProduct::where('product_id', $product->id)->lockForUpdate()->get();
            Supplier::orderBy('id')->lockForUpdate()->get();
            Category::orderBy('id')->lockForUpdate()->get();
            DB::table('category_pricing_rules')->lockForUpdate()->get();
            $quote = $this->quote($product);
            if (! hash_equals($quote['revision'], $revision) || $quote['suggested_minor'] === null) {
                throw ValidationException::withMessages(['commercial' => 'El cálculo cambió o no está disponible. Revisa la oferta y el precio sugerido antes de aplicar.']);
            }
            $product->price_minor = $quote['suggested_minor'];
            if ($product->previous_price_minor !== null && $product->previous_price_minor <= $product->price_minor) {
                $product->previous_price_minor = null;
            }
            $product->save();
            Audit::record('commercial.price_applied', $actor, metadata: ['entity_type' => 'products', 'entity_id' => $product->id, 'changed_fields' => ['price_minor', 'previous_price_minor']]);
        }, 3);
    }
}
