<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductFinishing extends Model
{
    use HasFactory;

    protected $table = 'product_finishings';

    protected $fillable = [
        'product_id',
        'finishing_id',
        'price_override',
    ];

    protected function casts(): array
    {
        return [
            'price_override' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function finishing(): BelongsTo
    {
        return $this->belongsTo(Finishing::class);
    }
}
