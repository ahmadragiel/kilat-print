<?php

namespace App\Http\Requests;

use App\Enums\PricingType;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
