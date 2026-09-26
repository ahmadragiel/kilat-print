<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'cart_id',
        'product_id',
        'quantity',
        'size',
        'length_cm',
        'width_cm',
        'material_id',
        'finishing_id',
        'color',
        'production_method',
        'custom_parameters',
        'custom_design_draft_id',
        'price_at_addition',
        'design_reference',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'length_cm' => 'decimal:2',
            'width_cm' => 'decimal:2',
            'custom_parameters' => 'array',
            'price_at_addition' => 'decimal:2',
        ];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function customDesignDraft(): BelongsTo
    {
        return $this->belongsTo(CustomDesignDraft::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function finishing(): BelongsTo
    {
        return $this->belongsTo(Finishing::class);
    }
}
