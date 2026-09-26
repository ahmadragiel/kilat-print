<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionPhoto extends Model
{
    use HasFactory;

    protected $table = 'production_photos';

    protected $fillable = [
        'production_order_id',
        'uploaded_by',
        'path',
        'original_filename',
        'mime_type',
        'size',
        'notes',
        'uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'uploaded_at' => 'datetime',
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

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
