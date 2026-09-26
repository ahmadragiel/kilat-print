<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isRole(UserRole::Customer) === true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'size' => ['nullable', 'string', 'max:80'],
            'length_cm' => ['nullable', 'numeric', 'min:0.1', 'max:100000'],
            'width_cm' => ['nullable', 'numeric', 'min:0.1', 'max:100000'],
            'material_id' => ['nullable', 'integer', 'exists:materials,id'],
            'finishing_id' => ['nullable', 'integer', 'exists:finishings,id'],
            'color' => ['nullable', 'string', 'max:100'],
            'production_method' => ['required', Rule::in(['digital', 'offset', 'large_format', 'sublimation'])],
            'notes' => ['nullable', 'string', 'max:2000'],
            'design_draft_id' => ['nullable', 'uuid', 'exists:custom_design_drafts,id'],
            'editing_cart_item_id' => ['nullable', 'integer', 'exists:cart_items,id'],
            'design_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:'.config('printing.design_max_kilobytes')],
        ];
    }
}
