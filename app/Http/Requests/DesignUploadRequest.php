<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class DesignUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isRole(UserRole::Customer) === true;
    }

    public function rules(): array
    {
        return [
            'design' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:'.config('printing.design_max_kilobytes')],
            'order_item_id' => ['nullable', 'integer', 'exists:order_items,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
