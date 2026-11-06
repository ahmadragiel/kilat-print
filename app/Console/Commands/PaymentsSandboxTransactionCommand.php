<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Cart;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Services\OrderService;
use App\Services\Payment\Drivers\MidtransGateway;
use App\Services\Payment\GatewayException;
use App\Services\Payment\PaymentTransactionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Creates a real transaction against the Midtrans Sandbox.
 *
 * This builds a genuine test order and asks the provider for payment
 * instructions, then prints what the provider returned. It deliberately stops
 * before any payment: the transaction stays PENDING until Midtrans sends a
 * notification. A sandbox run proves the integration, never a live payment.
 */
class PaymentsSandboxTransactionCommand extends Command
{
    protected $signature = 'payments:sandbox-transaction
                            {--method=both : qris, bank_transfer, or both}
                            {--quantity=1 : Item quantity for the generated order}';

    protected $description = 'Create a real Midtrans Sandbox transaction for QRIS and/or bank transfer';

    public function handle(PaymentTransactionService $transactions): int
    {
        if (! app(MidtransGateway::class)->isConfigured()) {
            $this->error('PAYMENT_SERVER_KEY belum diisi. Jalankan `php artisan payments:key` lebih dulu.');

            return self::FAILURE;
        }

        if (! config('payments.sandbox')) {
            $this->error('PAYMENT_SANDBOX=false. Perintah ini hanya boleh berjalan pada mode sandbox.');

            return self::FAILURE;
        }

        $methods = match ($this->option('method')) {
            'qris' => [PaymentMethod::Qris],
            'bank_transfer' => [PaymentMethod::BankTransfer],
            default => [PaymentMethod::Qris, PaymentMethod::BankTransfer],
        };

        $failures = 0;

        foreach ($methods as $method) {
            $failures += $this->runOne($method, $transactions) ? 0 : 1;
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function runOne(PaymentMethod $method, PaymentTransactionService $transactions): bool
    {
        $this->newLine();
        $this->line(" ▸ {$method->label()}");

        try {
            $order = $this->makeOrder((int) $this->option('quantity'));

            $result = $transactions->initiate($order, $method);
            $payment = $result['payment'];

            $this->row('Order', $order->number);
            $this->row('Provider', (string) $payment->provider);
            $this->row('Reference', (string) $payment->provider_reference);
            $this->row('Provider status', (string) $payment->provider_status);
            $this->row('Amount', number_format((float) $payment->amount, 0, ',', '.').' '.config('payments.currency'));
            $this->row('Local status', $payment->status->value.' ('.$payment->status->label().')');

            if ($method === PaymentMethod::Qris) {
                $this->row('QR image', $payment->qr_image ?? 'tidak dikembalikan provider');
            } else {
                $this->row('Virtual account', $payment->virtual_account_number ?? 'tidak dikembalikan provider');
                $this->row('Bank', $payment->virtual_account_bank ?? '-');
            }

            $this->row('Expires at', $payment->expires_at?->toDateTimeString() ?? '-');
            $this->row('Order status', $order->fresh()->status->value);
            $this->row('Production row', $order->fresh()->production ? 'created' : 'none (payment not confirmed)');

            $this->assertStillPending($order);

            $this->info(' Transaksi sandbox berhasil dibuat dan masih PENDING. Selesaikan pembayaran lewat simulasi Midtrans.');
            $this->line(' Notification URL untuk akun ini: '.config('payments.public_url').route('payments.webhook.midtrans', absolute: false));

            return true;
        } catch (GatewayException $exception) {
            // Provider errors are surfaced verbatim so an unactivated payment
            // method is obvious instead of being mistaken for success.
            $this->error(' Provider menolak permintaan:');
            $this->line('  '.$exception->getMessage());

            return false;
        } catch (Throwable $exception) {
            $this->error(' Gagal: '.$exception->getMessage());

            return false;
        }
    }

    /** A confirmed order must never be produced by transaction creation alone. */
    private function assertStillPending(Order $order): void
    {
        $fresh = $order->fresh(['payment', 'production']);

        if ($fresh->payment->status !== PaymentStatus::Pending
            || $fresh->status !== OrderStatus::PendingPayment
            || $fresh->paid_at !== null
            || $fresh->production !== null) {
            $this->error(' Invariant dilanggar: transaksi tidak boleh lunas sebelum ada konfirmasi provider.');
        }
    }

    /** Build a throwaway order from the demo catalog. */
    private function makeOrder(int $quantity): Order
    {
        $customer = Customer::whereHas('user', fn ($query) => $query->where('email', 'customer@kilatprint.test'))->first()
            ?? Customer::query()->first();

        if (! $customer) {
            throw new \RuntimeException('Tidak ada customer. Jalankan `php artisan db:seed` terlebih dahulu.');
        }

        $product = Product::where('status', 'active')->firstOrFail();

        return DB::transaction(function () use ($customer, $product, $quantity) {
            $address = $customer->addresses()->orderByDesc('is_primary')->first()
                ?? $customer->addresses()->create([
                    'label' => 'Alamat sandbox',
                    'recipient' => $customer->user->name,
                    'phone' => $customer->user->phone ?? '081200000000',
                    'address' => 'Jalan Sandbox Test No. 1',
                    'district' => 'Nginden',
                    'city' => 'Surabaya',
                    'province' => 'Jawa Timur',
                    'postal_code' => '60111',
                    'country' => 'Indonesia',
                    'is_primary' => true,
                ]);

            $cart = Cart::firstOrCreate(['customer_id' => $customer->id]);
            $cart->items()->delete();
            $cart->items()->create([
                'product_id' => $product->id,
                'quantity' => max(1, $quantity),
                'price_at_addition' => 0,
            ]);

            return app(OrderService::class)->checkout($customer, [
                'name' => $customer->user->name,
                'phone' => $customer->user->phone ?? '081200000000',
                'email' => $customer->user->email,
                'shipping_method' => 'delivery',
                'address_id' => $address->id,
                'payment_method' => PaymentMethod::Qris->value,
            ]);
        });
    }

    private function row(string $label, string $value): void
    {
        $this->line('   <fg=gray>'.str_pad($label, 17).'</> '.$value);
    }
}
