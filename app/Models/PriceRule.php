<?php

namespace App\Models;

use App\Enums\PricingType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PriceRule extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id',
        'catalog_key',
        'name',
        'pricing_type',
        'type',
        'variant_key',
        'price',
        'discount_percent',
        'min_quantity',
        'active',
        'is_wholesale',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'pricing_type' => PricingType::class,
            'price' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'min_quantity' => 'integer',
            'active' => 'boolean',
            'is_wholesale' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeForQuantity(Builder $query, int $quantity): Builder
    {
        return $query->where('min_quantity', '<=', max(1, $quantity));
    }

    public function scopeRetail(Builder $query): Builder
    {
        return $query->where('is_wholesale', false);
    }

    /** Compatibility alias for callers that refer to the rule unit as type. */
    public function getTypeAttribute(): ?PricingType
    {
        return $this->pricing_type;
    }

    public function setTypeAttribute(PricingType|string|null $value): void
    {
        if ($value !== null) {
            $this->attributes['pricing_type'] = $value instanceof PricingType ? $value->value : $value;
        }
    }

    public function getDiscountedPriceAttribute(): float
    {
        return (float) $this->price * (1 - ((float) $this->discount_percent / 100));
    }
}
