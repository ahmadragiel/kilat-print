<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Services\CustomDesignService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Body contract for the customiser save endpoint (POST create / PUT update).
 *
 * The draft itself is resolved from the route parameter, `draft_id` or `id`; when none is
 * present a new draft is created. CSRF and authentication are applied by the routes.
 */
class SaveCustomDesignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isRole(UserRole::Customer) === true
            && $this->user()->customer !== null;
    }

    public function rules(): array
    {
        return array_merge([
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where(fn ($query) => $query->where('status', 'active')),
            ],
            'draft_id' => ['sometimes', 'nullable', 'uuid'],
            'status' => ['sometimes', 'nullable', Rule::in(CustomDesignService::STATUSES)],
        ], CustomDesignService::specificationRules(), CustomDesignService::designRules());
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'Produk wajib dipilih.',
            'product_id.exists' => 'Produk tidak tersedia atau tidak aktif.',
            'design.front.elements.max' => 'Maksimal 50 elemen pada sisi depan.',
            'design.back.elements.max' => 'Maksimal 50 elemen pada sisi belakang.',
            'design.front.elements.*.type.in' => 'Tipe elemen harus image, text, atau sticker.',
            'design.back.elements.*.type.in' => 'Tipe elemen harus image, text, atau sticker.',
        ];
    }

    /** The draft identifier supplied by the client, if any. */
    public function draftKey(): ?string
    {
        $key = $this->input('draft_id') ?? $this->input('id');

        return is_string($key) && $key !== '' ? $key : null;
    }

    /**
     * Raw, unfiltered canvas payload. Bounded sanitisation happens in
     * {@see CustomDesignService} so unknown properties are dropped instead of stored.
     */
    public function designPayload(): mixed
    {
        return $this->input('design', []);
    }

    public function specificationPayload(): mixed
    {
        return $this->input('specification', []);
    }
}
