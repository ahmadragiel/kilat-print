<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DesignApproval extends Model
{
    protected $fillable = [
        'design_file_id',
        'order_id',
        'customer_id',
        'status',
        'notes',
        'requested_by',
        'requested_at',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function designFile(): BelongsTo
    {
        return $this->belongsTo(DesignFile::class, 'design_file_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
