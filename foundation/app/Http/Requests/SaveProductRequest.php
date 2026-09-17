<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-catalog') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->sku)) {
            $this->merge(['sku' => strtoupper(trim($this->sku))]);
        }
        if (! $this->filled('slug') && is_string($this->name)) {
            $this->merge(['slug' => Str::slug($this->name)]);
        }
    }

    public function rules(): array
    {
        $id = $this->route('product')?->id;

        return [
            'name' => ['required', 'string', 'max:180'],
            'sku' => ['required', 'string', 'max:64', 'regex:/^[A-Z0-9][A-Z0-9._-]*$/', Rule::unique('products')->ignore($id)],
            'slug' => ['required', 'string', 'max:180', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('products')->ignore($id)],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where(fn ($q) => $q->where('status', '!=', 'archived'))],
            'brand_id' => ['required', 'integer', Rule::exists('brands', 'id')->where(fn ($q) => $q->where('status', '!=', 'archived'))],
            'short_description' => ['required', 'string', 'max:500'], 'description' => ['required', 'string', 'max:20000'],
            'warranty' => ['nullable', 'string', 'max:3000'],
            'currency' => ['required', Rule::in(['CRC', 'USD'])],
            'price_minor' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'previous_price_minor' => ['nullable', 'integer', 'max:999999999999', 'gt:price_minor'],
            'featured' => ['required', 'boolean'], 'meta_title' => ['nullable', 'string', 'max:70'], 'meta_description' => ['nullable', 'string', 'max:170'],
            'specifications' => ['present', 'array', 'max:50'],
            'specifications.*' => ['required', 'array:key,label,value,unit,group'],
            'specifications.*.key' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]*$/', 'max:60', 'distinct:strict'],
            'specifications.*.label' => ['required', 'string', 'max:100'],
            'specifications.*.value' => ['required', 'string', 'max:500'],
            'specifications.*.unit' => ['nullable', 'string', 'max:30'],
            'specifications.*.group' => ['nullable', 'string', 'max:80'],
            'status' => ['prohibited'], 'published_at' => ['prohibited'], 'is_demo' => ['prohibited'],
            'cost' => ['prohibited'], 'supplier_id' => ['prohibited'], 'supplier_products' => ['prohibited'],
        ];
    }
}
