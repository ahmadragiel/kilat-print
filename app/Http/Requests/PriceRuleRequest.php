<?php

namespace App\Http\Requests;

use App\Enums\PricingType;
use App\Enums\UserRole;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PriceRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isRole(UserRole::Admin) === true;
    }

    protected function prepareForValidation(): void
    {
        if (array_key_exists('discount_percent', $this->all()) && $this->input('discount_percent') === null) {
            $this->merge(['discount_percent' => 0]);
        }

        if ($this->has('variant_key') && blank($this->input('variant_key'))) {
            $this->merge(['variant_key' => null]);
        }

        if (! $this->has('is_wholesale')) {
            $this->merge(['is_wholesale' => false]);
        }
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'exists:products,id'],
            'name' => ['required', 'string', 'max:150'],
            'pricing_type' => ['required', Rule::enum(PricingType::class)],
            'price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'discount_percent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'min_quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'variant_key' => ['nullable', 'string', 'max:80'],
            'is_wholesale' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Reject a variant that does not belong to the selected product and keep a
     * wholesale tariff disabled until it starts at a real quantity threshold.
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $product = Product::find($this->integer('product_id'));

            if (! $product) {
                return;
            }

            $variant = $this->input('variant_key');

            if (filled($variant) && $product->variationOption((string) $variant) === null) {
                $validator->errors()->add('variant_key', 'Varian tersebut tidak tersedia untuk produk yang dipilih.');
            }

            if ($this->boolean('is_wholesale') && $this->boolean('active') && $this->integer('min_quantity') < 2) {
                $validator->errors()->add('min_quantity', 'Harga grosir hanya boleh aktif mulai jumlah minimal 2.');
            }
        }];
    }
}
