<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'catalog_key',
        'description',
        'specifications',
        'variations',
        'thumbnail',
        'front_mockup',
        'back_mockup',
        'base_price',
        'price_display',
        'status',
        'is_featured',
        'minimum_order',
        'production_days',
        'popularity_count',
    ];

    protected function casts(): array
    {
        return [
            'specifications' => 'array',
            'variations' => 'array',
            'base_price' => 'decimal:2',
            'minimum_order' => 'integer',
            'production_days' => 'integer',
            'popularity_count' => 'integer',
            'is_featured' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function materials(): BelongsToMany
    {
        return $this->belongsToMany(Material::class, 'product_materials', 'product_id', 'material_id')
            ->withPivot(['price_override'])
            ->withTimestamps();
    }

    public function finishings(): BelongsToMany
    {
        return $this->belongsToMany(Finishing::class, 'product_finishings', 'product_id', 'finishing_id')
            ->withPivot(['price_override'])
            ->withTimestamps();
    }

    public function priceRules(): HasMany
    {
        return $this->hasMany(PriceRule::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Normalised single-dimension variation definition.
     *
     * The catalog currently needs one required choice per product (paper bag
     * size, party hat model). Keeping the shape small avoids a second table for
     * data that is always loaded with the product.
     *
     * @return array{key:string,label:string,required:bool,options:list<array{value:string,label:string}>}|null
     */
    public function variationDefinition(): ?array
    {
        $definition = $this->variations;

        if (! is_array($definition) || ! is_array($definition['options'] ?? null)) {
            return null;
        }

        $key = trim((string) ($definition['key'] ?? ''));
        if ($key === '') {
            return null;
        }

        $options = collect($definition['options'])
            ->filter(fn ($option) => is_array($option) && filled($option['value'] ?? null))
            ->map(fn (array $option) => [
                'value' => (string) $option['value'],
                'label' => (string) ($option['label'] ?? $option['value']),
            ])
            ->unique('value')
            ->values()
            ->all();

        if ($options === []) {
            return null;
        }

        return [
            'key' => $key,
            'label' => (string) ($definition['label'] ?? ucfirst($key)),
            'required' => (bool) ($definition['required'] ?? true),
            'options' => $options,
        ];
    }

    /** @return list<array{value:string,label:string}> */
    public function variationOptions(): array
    {
        return $this->variationDefinition()['options'] ?? [];
    }

    /** @return array{value:string,label:string}|null */
    public function variationOption(?string $value): ?array
    {
        if (blank($value)) {
            return null;
        }

        return collect($this->variationOptions())->firstWhere('value', $value);
    }

    public function defaultVariant(): ?string
    {
        return $this->variationOptions()[0]['value'] ?? null;
    }

    public function variantLabel(?string $value): ?string
    {
        return $this->variationOption($value)['label'] ?? null;
    }

    public function priceDisplayLabel(): string
    {
        return match ($this->price_display) {
            'per_unit' => 'Harga per unit',
            'exact' => 'Harga',
            default => 'Mulai dari',
        };
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Products that can actually be ordered: active and backed by at least one
     * active non-wholesale tariff.
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->whereHas('priceRules', fn (Builder $price) => $price->where('active', true)->where('is_wholesale', false));
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, function (Builder $builder, string $value) {
            $like = '%'.trim($value).'%';
            $builder->where(function (Builder $nested) use ($like) {
                $nested->where('name', 'like', $like)
                    ->orWhere('slug', 'like', $like)
                    ->orWhere('description', 'like', $like);
            });
        });
    }

    public function scopePopular(Builder $query): Builder
    {
        return $query->orderByDesc('popularity_count');
    }
}
