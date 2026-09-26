<?php

namespace App\Models;

use App\Enums\PricingType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Finishing extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'pricing_type',
        'price',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'pricing_type' => PricingType::class,
            'price' => 'decimal:2',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_finishings', 'finishing_id', 'product_id')
            ->withPivot(['price_override'])
            ->withTimestamps();
    }

    public function productFinishings(): HasMany
    {
        return $this->hasMany(ProductFinishing::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, function (Builder $builder, string $value) {
            $like = '%'.trim($value).'%';
            $builder->where(function (Builder $nested) use ($like) {
                $nested->where('name', 'like', $like)
                    ->orWhere('description', 'like', $like);
            });
        });
    }
}
