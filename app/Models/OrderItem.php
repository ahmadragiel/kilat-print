<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'product_reference',
        'product_slug',
        'quantity',
        'size',
        'length_cm',
        'width_cm',
        'material_id',
        'material_name',
        'material_reference',
        'finishing_id',
        'finishing_name',
        'finishing_reference',
        'color',
        'production_method',
        'custom_parameters',
        'configuration',
        'custom_design_draft_id',
        'design_reference',
        'notes',
        'unit_price',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'length_cm' => 'decimal:2',
            'width_cm' => 'decimal:2',
            'custom_parameters' => 'array',
            'configuration' => 'array',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
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

    public function designFiles(): HasMany
    {
        return $this->hasMany(DesignFile::class);
    }
}
