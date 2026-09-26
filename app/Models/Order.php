<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'number',
        'customer_id',
        'customer_name',
        'customer_phone',
        'customer_email',
        'status',
        'subtotal',
        'shipping_fee',
        'grand_total',
        'shipping_method',
        'internal_notes',
        'deadline',
        'paid_at',
        'completed_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'subtotal' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'deadline' => 'date',
            'paid_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function address(): HasOne
    {
        return $this->hasOne(OrderAddress::class);
    }

    public function orderAddress(): HasOne
    {
        return $this->address();
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function designFiles(): HasMany
    {
        return $this->hasMany(DesignFile::class);
    }

    public function production(): HasOne
    {
        return $this->hasOne(ProductionOrder::class);
    }

    public function productionOrder(): HasOne
    {
        return $this->production();
    }

    public function scopeStatus(Builder $query, OrderStatus|string $status): Builder
    {
        return $query->where('status', $status instanceof OrderStatus ? $status->value : $status);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', [OrderStatus::COMPLETED->value, OrderStatus::CANCELLED->value]);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, function (Builder $builder, string $value) {
            $like = '%'.trim($value).'%';
            $builder->where(function (Builder $nested) use ($like) {
                $nested->where('number', 'like', $like)
                    ->orWhereHas('customer.user', fn (Builder $user) => $user
                        ->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like))
                    ->orWhereHas('items', fn (Builder $item) => $item->where('product_name', 'like', $like));
            });
        });
    }

    public function isTerminal(): bool
    {
        return $this->status?->isTerminal() ?? false;
    }
}
