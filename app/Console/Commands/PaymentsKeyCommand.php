<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Writes the Midtrans server key into the local `.env` without echoing it.
 *
 * The key is read from a hidden prompt so it never appears in shell history, a
 * scrollback buffer, or a pasted transcript. Only `.env` is touched; no value is
 * written to source code, Blade, or a tracked file.
 */
class PaymentsKeyCommand extends Command
{
    protected $signature = 'payments:key
                            {--env= : Target the given .env file (defaults to APP_ENV)}
                            {--force : Overwrite an existing value}';

    protected $description = 'Store the Midtrans server key in .env using a hidden prompt';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Perintah ini memerlukan prompt interaktif agar kunci tidak terekspos.');

            return self::FAILURE;
        }

        $file = $this->envFile();

        if (! is_file($file)) {
            $this->error("Berkas {$file} tidak ditemukan. Salin .env.example terlebih dahulu.");

            return self::FAILURE;
        }

        $contents = (string) file_get_contents($file);

        if (! preg_match('/^PAYMENT_SERVER_KEY=.*$/m', $contents)) {
            $this->error('PAYMENT_SERVER_KEY belum ada di '.$file.' Tambahkan baris tersebut terlebih dahulu.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Ganti nilai PAYMENT_SERVER_KEY yang ada?Yn', true)) {
            return self::SUCCESS;
        }

        $this->line('Masukkan Midtrans Server Key. Input disembunyikan dan tidak akan dicetak.');
        $key = (string) $this->secret('Server Key');

        $key = trim($key);

        if ($key === '') {
            $this->error('Server key kosong; tidak ada perubahan yang ditulis.');

            return self::FAILURE;
        }

        // Midtrans keys never contain quotes or whitespace; refuse anything else
        // so a stray paste cannot corrupt the file.
        if (preg_match('/[\s"\']/', $key)) {
            $this->error('Server key tidak valid: hanya karakter tanpa spasi dan tanpa tanda kutip yang diterima.');

            return self::FAILURE;
        }

        $contents = (string) preg_replace('/^PAYMENT_SERVER_KEY=.*$/m', 'PAYMENT_SERVER_KEY='.$key, $contents, 1);

        if (file_put_contents($file, $contents) === false) {
            $this->error('Gagal menulis '.$file.'.');

            return self::FAILURE;
        }

        $this->info('Server key disimpan di '.$file.' (panjang '.$this->mask($key).').');
        $this->line('Jalankan `php artisan optimize:clear` lalu `php artisan payments:check` untuk memverifikasi.');

        return self::SUCCESS;
    }

    private function envFile(): string
    {
        $environment = $this->option('env') ?: (string) env('APP_ENV', 'local');

        return app()->environmentFilePath($environment === 'local' ? null : $environment);
    }

    /** Report only a length hint, never the key itself. */
    private function mask(string $key): string
    {
        return strlen($key).' karakter';
    }
}
