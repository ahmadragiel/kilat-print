<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payment\Drivers\MidtransGateway;
use App\Services\Payment\PaymentSettlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Midtrans transaction notification endpoint.
 *
 * The endpoint is public by design, so the notification is authenticated with
 * the `signature_key` Midtrans places in the body (SHA512 of order_id +
 * status_code + gross_amount + the server key) before any field is read. A
 * forged or tampered callback can therefore never move a transaction to PAID.
 *
 * Documentation: https://docs.midtrans.com/docs/https-notification-webhooks
 */
class MidtransNotificationController extends Controller
{
    public function __invoke(
        Request $request,
        MidtransGateway $gateway,
        PaymentSettlementService $settlement,
    ): JsonResponse {
        $payload = $request->json()->all();

        if (! $gateway->verifyNotificationSignature($payload)) {
            Log::warning('Rejected Midtrans notification with an invalid signature.', [
                'order_id' => $payload['order_id'] ?? null,
                'transaction_status' => $payload['transaction_status'] ?? null,
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => 'Invalid signature.'], 403);
        }

        $reference = (string) ($payload['order_id'] ?? '');
        $payment = Payment::query()
            ->where('provider', $gateway->name())
            ->where('provider_reference', $reference)
            ->first();

        if (! $payment) {
            Log::warning('Midtrans notification for an unknown transaction.', ['reference' => $reference]);

            return response()->json(['message' => 'Transaction not found.'], 404);
        }

        $result = $settlement->apply($payment, $gateway->toGatewayStatus($payload));

        return response()->json([
            'message' => $result['ignored_reason'] ?? 'ok',
            'status' => $result['payment']->status->value,
        ]);
    }
}
