<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One gateway transaction for one order.
 *
 * The payment is created as PENDING at checkout. Only the settlement service can
 * write PAID, and only from a provider confirmation. The legacy `proof_*`
 * columns stay so previously uploaded proofs remain readable, but nothing in the
 * current workflow writes them.
 */
class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'status',
        'method',
        'amount',
        'provider',
        'provider_reference',
        'provider_status',
        'channel',
        'qr_image',
        'qr_payload',
        'virtual_account_bank',
        'virtual_account_number',
        'bank_name',
        'account_number',
        'account_name',
        'expires_at',
        'paid_at',
        'failure_reason',
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
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
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

    /** The channel this transaction was created for. Call the method, do not read it as an attribute. */
    public function paymentMethod(): ?PaymentMethod
    {
        return PaymentMethod::tryFrom((string) $this->method);
    }

    public function isPaid(): bool
    {
        return $this->status === PaymentStatus::Paid;
    }

    /**
     * The transaction reached a state the provider will not change again.
     *
     * These can never return to PENDING, so no retry may reopen them.
     */
    public function isFinal(): bool
    {
        return $this->status instanceof PaymentStatus && $this->status->isFinal();
    }

    /** A payment page is only useful while the customer can still pay. */
    public function isAwaitingPayment(): bool
    {
        return $this->status === PaymentStatus::Pending
            && ! ($this->expires_at && $this->expires_at->isPast());
    }

    public function scopeStatus(Builder $query, PaymentStatus|string $status): Builder
    {
        return $query->where('status', $status instanceof PaymentStatus ? $status->value : $status);
    }

    /**
     * QR images are rendered as-is, but only from the provider over HTTPS or as an
     * inline PNG data URI.
     *
     * The host is pinned to Midtrans so a tampered or unexpected provider response
     * cannot make the payment page load an image from an outside host.
     */
    public function safeQrImage(): ?string
    {
        $image = $this->qr_image;

        if (! is_string($image) || $image === '') {
            return null;
        }

        if (preg_match('#^data:image/png;base64,[A-Za-z0-9+/=]+$#', $image)) {
            return $image;
        }

        $host = parse_url($image, PHP_URL_HOST);

        if (! is_string($host)) {
            return null;
        }

        $host = strtolower($host);
        $isMidtrans = $host === 'midtrans.com' || str_ends_with($host, '.midtrans.com');

        return ($isMidtrans && parse_url($image, PHP_URL_SCHEME) === 'https') ? $image : null;
    }
}
