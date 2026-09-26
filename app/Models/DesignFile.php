<?php

namespace App\Models;

use App\Enums\DesignStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DesignFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'order_item_id',
        'original_filename',
        'stored_filename',
        'path',
        'extension',
        'mime_type',
        'size',
        'version',
        'status',
        'notes',
        'review_note',
        'reviewed_by',
        'reviewed_at',
        'uploaded_by',
        'uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => DesignStatus::class,
            'size' => 'integer',
            'version' => 'integer',
            'reviewed_at' => 'datetime',
            'uploaded_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->uploader();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->reviewer();
    }

    public function scopeStatus(Builder $query, DesignStatus|string $status): Builder
    {
        return $query->where('status', $status instanceof DesignStatus ? $status->value : $status);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, function (Builder $builder, string $value) {
            $like = '%'.trim($value).'%';
            $builder->where(function (Builder $nested) use ($like) {
                $nested->where('original_filename', 'like', $like)
                    ->orWhereHas('order', fn (Builder $order) => $order->where('number', 'like', $like));
            });
        });
    }
}
