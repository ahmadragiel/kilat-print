<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payment\Drivers\MidtransGateway;
use App\Services\Payment\GatewayException;
use App\Services\Payment\PaymentStatusPoller;
use App\Services\Payment\PaymentTransactionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Sandbox helper for exercising the QRIS and virtual account flows by hand.
 *
 * The whole controller is refused unless the gateway points at the Midtrans test
 * environment, so none of it can ever touch a live merchant account.
 *
 * Nothing here writes a payment status directly. "Cek ke penyedia" reads Midtrans
 * and feeds the result through the normal settlement path, and "Paksa kedaluwarsa"
 * calls Midtrans' own expire endpoint so the provider itself moves the
 * transaction and then notifies us like it would in production.
 */
class PaymentSandboxController extends Controller
{
    public function index(Request $request, PaymentStatusPoller $poller): View
    {
        $this->guardSandbox($poller);

        $pending = Payment::query()
            ->where('provider', 'midtrans')
            ->whereIn('status', [PaymentStatus::Pending->value])
            ->with(['order.items'])
            ->latest()
            ->limit(25)
            ->get();

        return view('admin.payment-sandbox', [
            'pending' => $pending,
            'simulatorUrl' => PaymentStatusPoller::simulatorUrl(),
            'baseUrl' => app(MidtransGateway::class)->baseUrl(),
        ]);
    }

    /** Ask Midtrans for the real state of a transaction. */
    public function check(Request $request, Payment $payment, PaymentTransactionService $transactions): RedirectResponse
    {
        $this->guardSandbox(app(PaymentStatusPoller::class));

        try {
            $result = $transactions->refresh($payment);
        } catch (GatewayException $exception) {
            return back()->with('warning', 'Penyedia tidak dapat dibaca: '.$exception->getMessage());
        }

        $payment = $result['payment'];

        $messages = [
            PaymentStatus::Paid->value => 'Penyedia mengonfirmasi pembayaran berhasil.',
            PaymentStatus::Failed->value => 'Penyedia melaporkan pembayaran gagal.',
            PaymentStatus::Expired->value => 'Penyedia melaporkan transaksi kedaluwarsa.',
            PaymentStatus::Cancelled->value => 'Transaksi dibatalkan.',
        ];

        return back()->with(
            $result['ignored_reason'] ? 'warning' : 'success',
            $messages[$payment->status->value]
                ?? 'Transaksi masih menunggu pembayaran di penyedia.',
        );
    }

    /**
     * Let Midtrans expire the transaction.
     *
     * This is a real provider call: Midtrans moves the state and notifies the
     * webhook, which is exactly how an abandoned payment ends in production.
     */
    public function expire(Request $request, Payment $payment, PaymentTransactionService $transactions): RedirectResponse
    {
        $this->guardSandbox(app(PaymentStatusPoller::class));

        if (! filled($payment->provider_reference)) {
            return back()->with('warning', 'Transaksi ini belum dibuat di penyedia.');
        }

        $gateway = app(MidtransGateway::class);

        try {
            $response = Http::asJson()
                ->withBasicAuth((string) config('payments.server_key'), '')
                ->timeout(20)
                ->post($gateway->baseUrl().'/v2/'.urlencode($payment->provider_reference).'/expire');
        } catch (\Throwable $exception) {
            return back()->with('warning', 'Gagal menghubungi penyedia: '.$exception->getMessage());
        }

        if ($response->failed()) {
            return back()->with('warning', 'Penyedia menolak: HTTP '.$response->status().' '.$response->body());
        }

        // Read the authoritative state back and run it through the normal path.
        try {
            $transactions->refresh($payment);
        } catch (GatewayException $exception) {
            return back()->with('warning', 'Status tidak dapat dipastikan: '.$exception->getMessage());
        }

        return back()->with('success', 'Penyedia telah menandai transaksi sebagai kedaluwarsa.');
    }

    /** Refuse every action unless the gateway points at the sandbox. */
    private function guardSandbox(PaymentStatusPoller $poller): void
    {
        abort_unless(
            $poller->isSandbox(),
            403,
            'Halaman ini hanya boleh dipakai pada mode sandbox. Nonaktifkan produksi terlebih dahulu.',
        );
    }
}
