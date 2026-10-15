<?php

namespace App\Services\Payment;

use App\Enums\PaymentMethod;
use App\Models\Order;

/**
 * Contract every payment driver implements.
 *
 * A driver may only describe how to pay and report provider status. There is
 * deliberately no `markPaid()` on this interface: payment status is owned by
 * {@see PaymentSettlementService} and is only ever written from a verified
 * provider confirmation.
 */
interface PaymentGateway
{
    /** Stable driver identifier, e.g. `midtrans`. */
    public function name(): string;

    public function isConfigured(): bool;

    /**
     * Create one payable transaction for an order.
     *
     * @throws GatewayException when the provider rejects the request or is unreachable.
     */
    public function createTransaction(Order $order, PaymentMethod $method): GatewayTransaction;

    /**
     * Ask the provider for the current state of a transaction.
     *
     * @throws GatewayException when the provider cannot be reached or reports an error.
     */
    public function fetchStatus(string $reference): GatewayStatus;
}
