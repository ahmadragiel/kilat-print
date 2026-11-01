<?php

namespace App\Http\Requests;

use App\Enums\PricingType;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MasterDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isRole(UserRole::Admin) === true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeVariationOptions();
        $this->normalizeSpecifications();
    }

    /**
     * The admin form edits variation options as one "value|label" line per
     * choice. Normalise that text into the JSON shape stored by the model.
     */
    private function normalizeVariationOptions(): void
    {
        $variations = $this->input('variations');

        if (! is_array($variations) || ! is_string($variations['options'] ?? null)) {
            return;
        }

        $options = [];

        foreach (preg_split('/\r\n|\r|\n/', $variations['options']) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            [$rawValue, $rawLabel] = array_pad(array_map('trim', explode('|', $line, 2)), 2, null);
            $value = Str::slug($rawValue);

            if ($value === '') {
                continue;
            }

            $options[] = [
                'value' => $value,
                'label' => filled($rawLabel) ? $rawLabel : $rawValue,
            ];
        }

        $variations['options'] = $options;
        $this->merge(['variations' => $variations]);
    }

    /**
     * Specifications are edited as one "key|value" line per row. Parsing them
     * here keeps every existing key instead of replacing the JSON with two
     * hard-coded fields.
     */
    private function normalizeSpecifications(): void
    {
        $specifications = $this->input('specifications');

        if (! is_string($specifications)) {
            return;
        }

        $rows = [];

        foreach (preg_split('/\r\n|\r|\n/', $specifications) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || ! str_contains($line, '|')) {
                continue;
            }

            [$rawKey, $rawValue] = array_map('trim', explode('|', $line, 2));
            $key = Str::slug($rawKey, '_');

            if ($key === '' || $rawValue === '') {
                continue;
            }

            $rows[$key] = $rawValue;
        }

        $this->merge(['specifications' => $rows]);
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
                'specifications.*' => ['nullable', 'string', 'max:1000'],
                'variations' => ['nullable', 'array'],
                'variations.key' => ['nullable', 'string', 'max:40', 'regex:/^[a-z][a-z0-9_-]*$/'],
                'variations.label' => ['nullable', 'string', 'max:80'],
                'variations.required' => ['nullable', 'boolean'],
                'variations.options' => ['nullable', 'array', 'max:50'],
                'variations.options.*.value' => ['required', 'string', 'max:60', 'distinct'],
                'variations.options.*.label' => ['required', 'string', 'max:80'],
                'is_featured' => ['nullable', 'boolean'],
                'base_price' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
                'price_display' => ['nullable', Rule::in(['starting', 'exact', 'per_unit'])],
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
