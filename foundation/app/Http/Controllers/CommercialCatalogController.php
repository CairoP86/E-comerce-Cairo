<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\PricingRule;
use App\Models\Product;
use App\Models\ProductCommercialSetting;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\CommercialPricing;
use App\Support\Audit;
use App\Support\CommercialDecimal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CommercialCatalogController extends Controller
{
    private function validated(Request $request, array $rules): array
    {
        if (array_diff(array_keys($request->all()), [...array_keys($rules), '_token', '_method'])) {
            throw ValidationException::withMessages(['commercial' => 'La solicitud contiene campos no permitidos.']);
        }

        return $request->validate($rules);
    }

    private function multiplier(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }
        try {
            return CommercialDecimal::multiplier($value);
        } catch (\InvalidArgumentException) {
            throw ValidationException::withMessages(['multiplier' => 'Usa un multiplicador entre 0.0001 y 100.0000, con hasta cuatro decimales.']);
        }
    }

    public function suppliers()
    {
        return Inertia::render('admin/commercial/Suppliers', ['suppliers' => Supplier::withCount('offers')->orderBy('name')->get(), 'canManage' => request()->user()->can('manage-catalog')]);
    }

    public function supplier(Supplier $supplier)
    {
        return Inertia::render('admin/commercial/Supplier', ['supplier' => $supplier, 'offers' => $supplier->offers()->with('product:id,name,is_demo')->paginate(20), 'canManage' => request()->user()->can('manage-catalog')]);
    }

    public function saveSupplier(Request $request, ?Supplier $supplier = null)
    {
        $data = $this->validated($request, ['name' => ['required', 'string', 'max:120'], 'code' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('suppliers')->ignore($supplier?->id)], 'active' => ['required', 'boolean'], 'notes' => ['nullable', 'string', 'max:2000']]);
        DB::transaction(function () use ($supplier, $data, $request) {
            $supplier = $supplier ? Supplier::whereKey($supplier->id)->lockForUpdate()->firstOrFail() : new Supplier;
            $event = $supplier->exists ? 'supplier.updated' : 'supplier.created';
            $supplier->fill($data);
            $fields = array_keys($supplier->getDirty());
            $supplier->save();
            Audit::record($event, $request->user()->id, metadata: ['entity_type' => 'suppliers', 'entity_id' => $supplier->id, 'changed_fields' => $fields]);
        });

        return back()->with('status', 'Proveedor guardado. No se ha configurado ninguna API.');
    }

    public function product(Product $product, CommercialPricing $pricing)
    {
        return Inertia::render('admin/commercial/Product', [
            'product' => $product->only(['id', 'name', 'sku', 'currency', 'price_minor', 'is_demo']),
            'offers' => SupplierProduct::where('product_id', $product->id)->with('supplier:id,name,active')->orderBy('id')->get(),
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name', 'active']),
            'quote' => $pricing->quote($product),
            'override' => ($units = ProductCommercialSetting::where('product_id', $product->id)->value('multiplier_units')) === null ? '' : CommercialDecimal::format($units),
            'canManage' => request()->user()->can('manage-catalog'),
        ]);
    }

    public function saveOffer(Request $request, Product $product, ?SupplierProduct $offer = null)
    {
        if ($offer) {
            abort_unless($offer->product_id === $product->id, 404);
        }
        $data = $this->validated($request, [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'supplier_sku' => ['required', 'string', 'max:100', Rule::unique('supplier_products')->where('supplier_id', $request->input('supplier_id'))->ignore($offer?->id)],
            'reference' => ['nullable', 'string', 'max:180'], 'cost_minor' => ['nullable', 'integer', 'min:1', 'max:999999999999'],
            'currency' => ['required', Rule::in(['CRC', 'USD'])], 'stock' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
            'availability' => ['required', Rule::in(['unknown', 'available', 'unavailable'])],
            'observed_at' => ['required', 'date', 'before_or_equal:now'], 'active' => ['required', 'boolean'], 'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $stock = $data['stock'] ?? null;
        if (($data['availability'] === 'unknown' && $stock !== null) || ($data['availability'] === 'available' && $stock !== null && (int) $stock === 0) || ($data['availability'] === 'unavailable' && $stock !== null && (int) $stock !== 0)) {
            throw ValidationException::withMessages(['stock' => 'La cantidad no coincide con la disponibilidad indicada. Usa desconocida con cantidad vacía si no tienes confirmación.']);
        }
        DB::transaction(function () use ($product, $offer, $data, $request) {
            Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $offer = $offer ? SupplierProduct::whereKey($offer->id)->lockForUpdate()->firstOrFail() : new SupplierProduct;
            $event = $offer->exists ? 'supplier_offer.updated' : 'supplier_offer.created';
            $offer->fill($data);
            $offer->product_id = $product->id;
            $offer->source = 'manual';
            $fields = array_keys($offer->getDirty());
            $offer->save();
            Audit::record($event, $request->user()->id, metadata: ['entity_type' => 'supplier_products', 'entity_id' => $offer->id, 'changed_fields' => $fields]);
        });

        return back()->with('status', 'Oferta guardada. El precio público no ha cambiado.');
    }

    public function settings(Request $request, Product $product)
    {
        $data = $this->validated($request, ['preferred_offer_id' => ['nullable', 'integer', 'exists:supplier_products,id'], 'multiplier' => ['nullable', 'string', 'max:8']]);
        $units = $this->multiplier($data['multiplier'] ?? null);
        DB::transaction(function () use ($product, $data, $units, $request) {
            Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            if ($data['preferred_offer_id'] ?? null) {
                $offer = SupplierProduct::whereKey($data['preferred_offer_id'])->where('product_id', $product->id)->first();
                if (! $offer || ! $offer->active || ! $offer->supplier->active) {
                    throw ValidationException::withMessages(['preferred_offer_id' => 'Selecciona una oferta activa de este producto y de un proveedor activo.']);
                }
            }
            $setting = ProductCommercialSetting::firstOrNew(['product_id' => $product->id]);
            $setting->product_id = $product->id;
            $setting->preferred_offer_id = $data['preferred_offer_id'] ?? null;
            $setting->multiplier_units = $units;
            $fields = array_keys($setting->getDirty());
            $setting->save();
            Audit::record('product.commercial_settings_changed', $request->user()->id, metadata: ['entity_type' => 'products', 'entity_id' => $product->id, 'changed_fields' => $fields]);
        });

        return back()->with('status', 'Preferencia y excepción guardadas. Revisa el precio sugerido.');
    }

    public function apply(Request $request, Product $product, CommercialPricing $pricing)
    {
        $data = $this->validated($request, ['revision' => ['required', 'string', 'size:64']]);
        $pricing->apply($product, $data['revision'], $request->user()->id);

        return back()->with('status', 'Precio sugerido aplicado al producto. Los pedidos anteriores conservan sus importes.');
    }

    public function rules()
    {
        return Inertia::render('admin/commercial/Rules', [
            'rules' => PricingRule::orderBy('name')->get()->map(fn ($rule) => ['id' => $rule->id, 'name' => $rule->name, 'multiplier' => CommercialDecimal::format($rule->multiplier_units)]),
            'categories' => Category::orderBy('name')->get(['id', 'name', 'parent_id', 'status']),
            'assignments' => DB::table('category_pricing_rules')->get(), 'canManage' => request()->user()->can('manage-catalog'),
        ]);
    }

    public function saveRule(Request $request, ?PricingRule $rule = null)
    {
        $data = $this->validated($request, ['name' => ['required', 'string', 'max:120', Rule::unique('pricing_rules')->ignore($rule?->id)], 'multiplier' => ['required', 'string', 'max:8']]);
        $units = $this->multiplier($data['multiplier']);
        DB::transaction(function () use ($rule, $data, $units, $request) {
            PricingRule::orderBy('id')->lockForUpdate()->get();
            $rule = $rule ? PricingRule::findOrFail($rule->id) : new PricingRule;
            $event = $rule->exists ? 'pricing_rule.updated' : 'pricing_rule.created';
            $rule->fill(['name' => $data['name'], 'multiplier_units' => $units])->save();
            Audit::record($event, $request->user()->id, metadata: ['entity_type' => 'pricing_rules', 'entity_id' => $rule->id, 'changed_fields' => ['name', 'multiplier_units']]);
        });

        return back()->with('status', 'Regla guardada. No se han cambiado precios públicos.');
    }

    public function assignRule(Request $request)
    {
        $data = $this->validated($request, ['category_id' => ['required', 'integer', 'exists:categories,id'], 'pricing_rule_id' => ['nullable', 'integer', 'exists:pricing_rules,id']]);
        DB::transaction(function () use ($data, $request) {
            PricingRule::orderBy('id')->lockForUpdate()->get();
            if ($data['pricing_rule_id'] ?? null) {
                DB::table('category_pricing_rules')->updateOrInsert(['category_id' => $data['category_id']], ['pricing_rule_id' => $data['pricing_rule_id']]);
            } else {
                DB::table('category_pricing_rules')->where('category_id', $data['category_id'])->delete();
            }
            Audit::record('category.pricing_assigned', $request->user()->id, metadata: ['entity_type' => 'categories', 'entity_id' => (int) $data['category_id'], 'changed_fields' => ['pricing_rule_id']]);
        });

        return back()->with('status', 'Asignación guardada. Se aplica a descendientes sin regla propia.');
    }
}
