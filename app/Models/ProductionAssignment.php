<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionAssignment extends Model
{
    use HasFactory;

    protected $table = 'production_assignments';

    protected $fillable = [
        'production_order_id',
        'operator_id',
        'assigned_by',
        'assigned_at',
        'unassigned_at',
        'is_current',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
            'assigned_at' => 'datetime',
            'unassigned_at' => 'datetime',
        ];
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function production(): BelongsTo
    {
        return $this->productionOrder();
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }
}
