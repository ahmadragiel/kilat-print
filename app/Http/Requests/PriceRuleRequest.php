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

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'exists:products,id'],
            'name' => ['required', 'string', 'max:150'],
            'pricing_type' => ['required', Rule::enum(PricingType::class)],
            'price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'min_quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
