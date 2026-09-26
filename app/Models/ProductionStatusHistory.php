<?php

namespace App\Models;

use App\Enums\ProductionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionStatusHistory extends Model
{
    use HasFactory;

    protected $table = 'production_status_histories';

    protected $fillable = [
        'production_order_id',
        'changed_by',
        'old_status',
        'new_status',
        'progress',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'old_status' => ProductionStatus::class,
            'new_status' => ProductionStatus::class,
            'progress' => 'integer',
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

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
