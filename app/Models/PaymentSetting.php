<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The single row holding the payment gateway credentials.
 *
 * Every key column uses Laravel's `encrypted` cast, so values are encrypted with
 * `APP_KEY` before they are written and decrypted only inside the process. A
 * database dump therefore never contains a usable credential.
 *
 * The class deliberately offers no accessor that returns a raw secret to a view:
 * the dashboard only ever learns whether a value exists.
 */
class PaymentSetting extends Model
{
    protected $fillable = [
        'driver',
        'server_key',
        'client_key',
        'merchant_id',
        'sandbox',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'server_key' => 'encrypted',
            'client_key' => 'encrypted',
            'merchant_id' => 'encrypted',
            'sandbox' => 'boolean',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * A short, non-reversible fingerprint used to tell two keys apart in the UI.
     *
     * It is deliberately not reversible and never contains key material.
     */
    public static function fingerprint(?string $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $trimmed = trim($value);

        return substr(hash('sha256', $trimmed), 0, 8);
    }

    public function hasServerKey(): bool
    {
        return filled($this->server_key);
    }

    public function hasClientKey(): bool
    {
        return filled($this->client_key);
    }

    public function hasMerchantId(): bool
    {
        return filled($this->merchant_id);
    }
}
