<?php

namespace App\Http\Requests;

use App\Enums\PricingType;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MasterDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isRole(UserRole::Admin) === true;
    }

    public function rules(): array
    {
        $route = $this->route();
        $type = $this->route('resourceType') ?? $route?->getDefaults()['resourceType'] ?? null;
        $record = $this->route('record');
        $id = is_object($record) ? $record->id : $record;
        if ($id && ! ctype_digit((string) $id)) {
            $table = match ($type) {
                'products' => 'products',
                'categories' => 'categories',
                'materials' => 'materials',
                'finishings' => 'finishings',
                default => null,
            };
            $id = $table ? DB::table($table)->where('slug', $id)->value('id') : null;
        }

        $common = [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];

        return match ($type) {
            'products' => $common + [
                'category_id' => ['required', 'exists:categories,id'],
                'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('products', 'slug')->ignore($id)],
                'specifications' => ['nullable', 'array'],
                'is_featured' => ['nullable', 'boolean'],
                'minimum_order' => ['required', 'integer', 'min:1', 'max:100000'],
                'production_days' => ['required', 'integer', 'min:1', 'max:365'],
                'thumbnail' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
                'front_mockup' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
                'back_mockup' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
                'materials' => ['nullable', 'array'],
                'materials.*' => ['integer', 'exists:materials,id'],
                'finishings' => ['nullable', 'array'],
                'finishings.*' => ['integer', 'exists:finishings,id'],
            ],
            'categories' => $common + [
                'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('categories', 'slug')->ignore($id)],
                'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            ],
            'materials', 'finishings' => $common + [
                'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', Rule::unique($type, 'slug')->ignore($id)],
                'pricing_type' => ['required', Rule::enum(PricingType::class)],
                'price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            ],
            default => abort(404),
        };
    }
}
