<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A customer's in-progress product customisation.
 *
 * `specification` holds the bounded print specification (size, material, ...) while
 * `design` holds the sanitised canvas payload: `{"front":{"elements":[]},"back":{"elements":[]}}`.
 * Canvas elements never store filesystem paths; uploaded bitmaps are referenced by
 * `asset_id` and hydrated with authorised route URLs at response time.
 *
 * @property int $customer_id
 * @property int $product_id
 * @property array<string, mixed>|null $specification
 * @property array<string, mixed>|null $design
 * @property int $version
 * @property string $status
 * @property-read Customer $customer
 * @property-read Product $product
 * @property-read Collection<int, CustomDesignAsset> $assets
 */
class CustomDesignDraft extends Model
{
    use HasUuids;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_COMPLETED = 'completed';

    public const SIDES = ['front', 'back'];

    protected $fillable = [
        'customer_id',
        'product_id',
        'specification',
        'design',
        'version',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'specification' => 'array',
            'design' => 'array',
            'version' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(CustomDesignAsset::class, 'draft_id');
    }

    /** Elements of one canvas side, always returned as a list. */
    public function elements(string $side = 'front'): array
    {
        $elements = $this->design[$side]['elements'] ?? [];

        return is_array($elements) ? array_values($elements) : [];
    }

    /** Every `asset_id` referenced by either canvas side. */
    public function referencedAssetIds(): array
    {
        $ids = [];

        foreach (self::SIDES as $side) {
            foreach ($this->elements($side) as $element) {
                if (! is_array($element) || ($element['type'] ?? null) !== 'image') {
                    continue;
                }

                $id = $element['asset_id'] ?? $element['assetId'] ?? null;

                if (is_string($id) && $id !== '') {
                    $ids[] = $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeForCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }
}
