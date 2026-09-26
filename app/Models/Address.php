<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Address extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_id',
        'label',
        'recipient',
        'phone',
        'address',
        'district',
        'city',
        'province',
        'postal_code',
        'country',
        'is_primary',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function scopePrimary(Builder $query): Builder
    {
        return $query->where('is_primary', true);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, function (Builder $builder, string $value) {
            $like = '%'.trim($value).'%';
            $builder->where(function (Builder $nested) use ($like) {
                $nested->where('recipient', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('city', 'like', $like)
                    ->orWhere('province', 'like', $like);
            });
        });
    }
}
