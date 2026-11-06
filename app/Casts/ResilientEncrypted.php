<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Encrypted attribute that treats undecryptable ciphertext as missing.
 *
 * A deployment can rotate APP_KEY while the payment_settings table still holds
 * values encrypted with the old key. Laravel's built-in `encrypted` cast throws
 * a DecryptException in that case, taking the whole admin/payment page down.
 * Returning null lets PaymentSettings fall back to the environment values and
 * lets an admin re-enter the credentials from the dashboard.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
class ResilientEncrypted implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            $decrypted = Crypt::decryptString($value);
        } catch (DecryptException) {
            return null;
        }

        return is_string($decrypted) ? $decrypted : null;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Crypt::encryptString((string) $value);
    }
}
