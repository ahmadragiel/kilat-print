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
     * chooses a saved address and the payment channel; the gateway creates the
     * transaction afterwards. Pickup is no longer a selectable service.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+ -]+$/'],
            'email' => ['required', 'email', 'max:255'],
            'shipping_method' => ['required', Rule::in(['delivery'])],
            'address_id' => [
                'required',
                'integer',
                Rule::exists('addresses', 'id')
                    ->where('customer_id', $this->user()?->customer?->id ?? 0)
                    ->whereNull('deleted_at'),
            ],
            'payment_method' => ['required', Rule::in(PaymentMethod::values())],
            'shipping_notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'shipping_method.required' => 'Pesanan harus dikirim ke alamat. Pilih metode pengiriman yang tersedia.',
            'shipping_method.in' => 'Pengambilan di lokasi sudah tidak tersedia. Pesanan hanya dapat dikirim ke alamat.',
            'address_id.required' => 'Pilih alamat pengiriman yang tersimpan sebelum membuat pesanan.',
            'address_id.integer' => 'Alamat pengiriman tidak valid.',
            'address_id.exists' => 'Alamat pengiriman tidak ditemukan atau bukan milik akun Anda.',
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
