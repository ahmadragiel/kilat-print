<?php

namespace App\Console\Commands;

use App\Services\Payment\Drivers\MidtransGateway;
use App\Services\Payment\PaymentSettings;
use Illuminate\Console\Command;
use Throwable;

/**
 * Reports whether the payment gateway is ready, without ever printing a secret.
 *
 * It reads the environment the application actually resolved, then asks the
 * provider whether it can be reached and whether the server key is accepted.
 * The probe uses a read-only status lookup, so it never creates a transaction.
 */
class PaymentsCheckCommand extends Command
{
    protected $signature = 'payments:check {--json : Machine readable output}';

    protected $description = 'Verify the payment gateway configuration and provider connectivity';

    public function handle(MidtransGateway $gateway, PaymentSettings $settings): int
    {
        $sandbox = $settings->sandbox();
        $serverKey = $settings->serverKey();
        $merchantId = $settings->merchantId();
        $clientKey = $settings->clientKey();

        $report = [
            'driver' => $settings->driver(),
            'base_url' => $gateway->baseUrl(),
            'sandbox' => $sandbox,
            'environment' => app()->environment(),
            'currency' => config('payments.currency'),
            'expiry_hours' => config('payments.expiry_hours'),
            'bank_transfer_bank' => config('payments.bank_transfer_bank'),
            'bank_supported' => in_array(
                strtolower((string) config('payments.bank_transfer_bank')),
                MidtransGateway::SUPPORTED_BANKS,
                true,
            ),
            'merchant_id' => filled($merchantId) ? $merchantId : null,
            'server_key_present' => is_string($serverKey) && trim($serverKey) !== '',
            'client_key_present' => is_string($clientKey) && trim($clientKey) !== '',
            'sources' => $settings->sources(),
            'webhook_path' => route('payments.webhook.midtrans', absolute: false),
            'webhook_publicly_reachable' => $this->isPubliclyReachable(),
            'provider' => ['reachable' => false, 'authenticated' => false, 'status' => 0, 'message' => 'not probed'],
            'problems' => [],
        ];

        // Guard rails: a sandbox key must never be used against production.
        if (! $sandbox && app()->environment('production')) {
            $report['problems'][] = 'PAYMENT_SANDBOX=false pada environment production. Pastikan server key adalah kredensial production, bukan sandbox.';
        }

        if (! $report['bank_supported']) {
            $report['problems'][] = 'PAYMENT_BANK_TRANSFER_BANK tidak didukung Midtrans Core API. Pilihan: '.implode(', ', MidtransGateway::SUPPORTED_BANKS).'.';
        }

        if (! $report['server_key_present']) {
            $report['problems'][] = 'PAYMENT_SERVER_KEY kosong. Isi lewat `php artisan payments:key` (input disembunyikan) lalu `php artisan optimize:clear`.';
        }

        if (! $report['webhook_publicly_reachable']) {
            $report['problems'][] = 'APP_URL bukan URL publik. Midtrans tidak bisa memanggil webhook pada localhost; gunakan tunnel HTTPS atau staging.';
        }

        $report['active_driver'] = $gateway->name();

        if ($report['server_key_present']) {
            try {
                $report['provider'] = $gateway->probe();
            } catch (Throwable $exception) {
                $report['provider'] = [
                    'reachable' => false,
                    'authenticated' => false,
                    'status' => 0,
                    'message' => $exception->getMessage(),
                ];
            }

            if (! $report['provider']['authenticated']) {
                $report['problems'][] = 'Server key ditolak oleh '.$gateway->baseUrl().'.';
            }
        }

        $report['ready'] = $report['problems'] === [];

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $report['ready'] ? self::SUCCESS : self::FAILURE;
        }

        $this->renderReport($report);

        return $report['ready'] ? self::SUCCESS : self::FAILURE;
    }

    /** @param array<string, mixed> $report */
    private function renderReport(array $report): void
    {
        $this->newLine();
        $this->line(' Payment gateway check');
        $this->line(' ─────────────────────');
        $this->row('Driver', (string) $report['driver']);
        $this->row('Base URL', (string) $report['base_url'].($report['sandbox'] ? '  (sandbox)' : '  (PRODUCTION)'));
        $this->row('Environment', (string) $report['environment']);
        $this->row('Currency', (string) $report['currency']);
        $this->row('Expiry', $report['expiry_hours'].' hour(s)');
        $this->row('Bank transfer', $report['bank_supported'] ? 'supported' : 'NOT SUPPORTED  '.(string) $report['bank_transfer_bank']);
        // Only presence is reported; the value never leaves configuration.
        $this->row('Server key', ($report['server_key_present'] ? 'present' : 'MISSING').'  ('.$report['sources']['server_key'].')');
        $this->row('Client key', ($report['client_key_present'] ? 'present' : '-').'  ('.$report['sources']['client_key'].')');
        $this->row('Merchant id', ($report['merchant_id'] ? 'present' : '-').'  ('.$report['sources']['merchant_id'].')');
        $this->row('Webhook', (string) $report['webhook_path']);
        $this->row('Webhook public', $report['webhook_publicly_reachable'] ? 'yes' : 'no (localhost?)');

        $provider = $report['provider'];
        $this->row('Provider reachable', $provider['reachable'] ? 'yes' : 'no');
        $this->row('Provider auth', $provider['authenticated'] ? 'accepted' : 'not verified');
        $this->row('Provider detail', (string) $provider['message']);

        if ($report['problems'] !== []) {
            $this->newLine();
            $this->line(' Problems');
            $this->line(' ────────');
            foreach ($report['problems'] as $problem) {
                $this->line('  • '.$problem);
            }
        }

        $this->newLine();
        $this->line($report['ready']
            ? ' Gateway siap dipakai. Buat transaksi uji dengan `php artisan payments:sandbox-transaction`.'
            : ' Gateway belum siap. Perbaiki masalah di atas lebih dulu.');
        $this->newLine();
    }

    private function row(string $label, string $value): void
    {
        $this->line(' <fg=gray>'.str_pad($label, 18).'</> '.$value);
    }

    /**
     * Midtrans calls the webhook over the public internet, so a loopback or
     * private host can never receive it.
     */
    private function isPubliclyReachable(): bool
    {
        $host = parse_url((string) config('payments.public_url'), PHP_URL_HOST) ?: '';

        return $host !== ''
            && ! in_array($host, ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true)
            && ! str_starts_with($host, '192.168.')
            && ! str_starts_with($host, '10.')
            && ! str_starts_with($host, '172.16.');
    }
}
