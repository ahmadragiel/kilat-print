<?php

namespace App\Models;

use App\Enums\ProductionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProductionOrder extends Model
{
    use HasFactory;

    protected $table = 'production_orders';

    protected $fillable = [
        'order_id',
        'operator_id',
        'status',
        'progress',
        'notes',
        'deadline',
        'assigned_at',
        'started_at',
        'finished_at',
        'completed_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProductionStatus::class,
            'progress' => 'integer',
            'deadline' => 'date',
            'assigned_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class, 'operator_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ProductionAssignment::class);
    }

    public function currentAssignment(): HasOne
    {
        return $this->hasOne(ProductionAssignment::class)
            ->where('is_current', true)
            ->latestOfMany('assigned_at');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(ProductionStatusHistory::class);
    }

    public function qualityChecks(): HasMany
    {
        return $this->hasMany(QualityCheck::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ProductionPhoto::class);
    }

    public function scopeStatus(Builder $query, ProductionStatus|string $status): Builder
    {
        return $query->where('status', $status instanceof ProductionStatus ? $status->value : $status);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', [ProductionStatus::COMPLETED->value, ProductionStatus::CANCELLED->value]);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, function (Builder $builder, string $value) {
            $like = '%'.trim($value).'%';
            $builder->where(function (Builder $nested) use ($like) {
                $nested->whereHas('order', fn (Builder $order) => $order->where('number', 'like', $like))
                    ->orWhereHas('operator.user', fn (Builder $user) => $user->where('name', 'like', $like));
            });
        });
    }
}
