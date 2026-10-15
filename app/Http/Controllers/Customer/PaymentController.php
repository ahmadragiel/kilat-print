<?php

namespace App\Http\Controllers\Customer;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Payment\GatewayException;
use App\Services\Payment\PaymentGatewayFactory;
use App\Services\Payment\PaymentStatusPoller;
use App\Services\Payment\PaymentTransactionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Customer facing payment page.
 *
 * The page is strictly read-only apart from asking the server to re-read the
 * transaction from the provider. A customer can never post a status into this
 * flow, and the "success" tick only appears once the backend stored PAID.
 */
class PaymentController extends Controller
{
    public function show(Request $request, Order $order, PaymentGatewayFactory $gateways): View|RedirectResponse
    {
        $this->authorize('view', $order);

        $payment = $order->payment;
        if (! $payment) {
            return redirect()->route('customer.orders.show', $order)
                ->with('warning', 'Pesanan ini belum memiliki transaksi pembayaran.');
        }

        // Only a transaction that can still be paid gets the payment page. A paid,
        // failed, expired or timed-out transaction belongs on the order page,
        // otherwise the customer would see a QR or virtual account that can no
        // longer settle.
        if (! $payment->isAwaitingPayment()) {
            return redirect()->route('customer.orders.show', $order);
        }

        $gateway = $gateways->gateway();

        return view('customer.payments.show', [
            'order' => $order->load(['items', 'address']),
            'payment' => $payment,
            'gatewayName' => $gateway->name(),
            'gatewayReady' => $gateway->isConfigured(),
        ]);
    }

    /**
     * Polling endpoint used by the payment page after the customer scans the QR.
     *
     * It only reports the state this application has recorded. Any provider read
     * happens server to server and at most once every few seconds, so nothing a
     * browser posts can move a transaction to PAID.
     */
    public function status(Request $request, Order $order, PaymentStatusPoller $poller): JsonResponse
    {
        $this->authorize('view', $order);

        $payment = $order->payment;
        abort_if(! $payment, 404, 'Transaksi pembayaran tidak ditemukan.');

        return response()->json($poller->current($payment));
    }

    /**
     * Ask the provider for the current transaction state.
     *
     * The browser only triggers this; the answer and the resulting status always
     * come from the payment provider.
     */
    public function refresh(
        Request $request,
        Order $order,
        PaymentTransactionService $transactions,
    ): RedirectResponse {
        $this->authorize('view', $order);

        $payment = $order->payment;
        abort_if(! $payment, 404, 'Transaksi pembayaran tidak ditemukan.');

        try {
            $result = $transactions->refresh($payment);
        } catch (GatewayException $exception) {
            report($exception);

            return back()->with('warning', 'Status pembayaran belum dapat diperiksa. Silakan coba lagi.');
        }

        $payment = $result['payment'];

        if ($payment->status === PaymentStatus::Paid) {
            return back()->with('success', 'Pembayaran berhasil dikonfirmasi.');
        }

        if ($payment->status->isFinal()) {
            return back()->with('warning', $payment->failure_reason ?? 'Pembayaran belum berhasil.');
        }

        return back()->with('info', 'Pembayaran belum terdeteksi. Selesaikan pembayaran terlebih dahulu.');
    }
}
