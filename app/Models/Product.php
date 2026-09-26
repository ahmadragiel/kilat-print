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
        'description',
        'specifications',
        'thumbnail',
        'front_mockup',
        'back_mockup',
        'base_price',
        'status',
        'minimum_order',
        'production_days',
        'popularity_count',
    ];

    protected function casts(): array
    {
        return [
            'specifications' => 'array',
            'base_price' => 'decimal:2',
            'minimum_order' => 'integer',
            'production_days' => 'integer',
            'popularity_count' => 'integer',
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
