<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isRole(UserRole::Customer) === true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+ -]+$/'],
            'email' => ['required', 'email', 'max:255'],
            'shipping_method' => ['required', Rule::in(['pickup', 'delivery'])],
            'recipient' => ['required', 'string', 'max:255'],
            'address_phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+ -]+$/'],
            'address' => ['required', 'string', 'max:1000'],
            'district' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:100'],
            'postal_code' => ['required', 'string', 'max:10'],
        ];
    }
}
