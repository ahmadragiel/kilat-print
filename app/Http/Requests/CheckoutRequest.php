<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isRole(UserRole::Customer) === true;
    }

    /**
     * No material, finishing or payment proof is accepted here. The customer only
     * chooses the payment channel; the gateway creates the transaction afterwards.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+ -]+$/'],
            'email' => ['required', 'email', 'max:255'],
            'shipping_method' => ['required', Rule::in(['pickup', 'delivery'])],
            'payment_method' => ['required', Rule::in(PaymentMethod::values())],
            'recipient' => ['required', 'string', 'max:255'],
            'address_phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+ -]+$/'],
            'address' => ['required', 'string', 'max:1000'],
            'district' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:100'],
            'postal_code' => ['required', 'string', 'max:10'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'payment_method.required' => 'Pilih metode pembayaran QRIS atau transfer bank.',
            'payment_method.in' => 'Metode pembayaran harus QRIS atau transfer bank.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Legacy clients still post the raw radio value.
        if ($this->has('payment_method')) {
            $this->merge([
                'payment_method' => strtoupper((string) $this->input('payment_method')),
            ]);
        }
    }
}
