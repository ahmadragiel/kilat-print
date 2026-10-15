<?php

namespace App\Services\Payment;

use App\Models\PaymentSetting;

/**
 * Resolves the effective payment gateway configuration.
 *
 * Credentials entered by an admin from the dashboard win over the environment
 * variables, which stay available as a fallback for deployments that manage
 * secrets outside the application. A value counts as "configured" only when it is
 * non-empty, so clearing a field in the UI falls back to the environment instead
 * of leaving the gateway half configured.
 *
 * Resolved values are memoised for the current request so a single page render
 * never queries the table more than once.
 */
class PaymentSettings
{
    private ?PaymentSetting $record = null;

    private bool $resolved = false;

    public function record(): ?PaymentSetting
    {
        if (! $this->resolved) {
            $this->record = PaymentSetting::query()->first();
            $this->resolved = true;
        }

        return $this->record;
    }

    /** A row exists, even if every credential field in it is empty. */
    public function exists(): bool
    {
        return $this->record() !== null;
    }

    public function serverKey(): ?string
    {
        return $this->pick(
            $this->record()?->server_key,
            config('payments.server_key'),
        );
    }

    public function clientKey(): ?string
    {
        return $this->pick(
            $this->record()?->client_key,
            config('payments.client_key'),
        );
    }

    public function merchantId(): ?string
    {
        return $this->pick(
            $this->record()?->merchant_id,
            config('payments.merchant_id'),
        );
    }

    public function driver(): string
    {
        return $this->record()?->driver ?: (string) config('payments.driver', 'null');
    }

    public function sandbox(): bool
    {
        return (bool) ($this->record()?->sandbox ?? config('payments.sandbox', true));
    }

    /** Forget the memoised row so the next read sees a fresh write. */
    public function flush(): void
    {
        $this->record = null;
        $this->resolved = false;
    }

    /**
     * Where the active value came from, for the admin screen.
     *
     * @return array{driver: string, server_key: string, client_key: string, merchant_id: string}
     */
    public function sources(): array
    {
        return [
            'driver' => $this->record()?->driver ? 'dashboard' : 'environment',
            'server_key' => $this->sourceOf($this->record()?->server_key),
            'client_key' => $this->sourceOf($this->record()?->client_key),
            'merchant_id' => $this->sourceOf($this->record()?->merchant_id),
        ];
    }

    private function sourceOf(?string $stored): string
    {
        return filled($stored) ? 'dashboard' : 'environment';
    }

    private function pick(?string $stored, mixed $fromEnvironment): ?string
    {
        if (is_string($stored) && trim($stored) !== '') {
            return trim($stored);
        }

        $value = $fromEnvironment;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
