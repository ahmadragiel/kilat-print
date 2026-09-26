<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'status',
        'method',
        'amount',
        'bank_name',
        'account_number',
        'account_name',
        'proof_path',
        'proof_original_filename',
        'proof_extension',
        'proof_mime_type',
        'proof_size',
        'proof_version',
        'submitted_at',
        'verified_at',
        'verified_by',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'proof_size' => 'integer',
            'proof_version' => 'integer',
            'submitted_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->verifiedBy();
    }
}
