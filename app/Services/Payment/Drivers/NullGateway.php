<?php

namespace App\Services\Payment\Drivers;

use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Services\Payment\GatewayException;
use App\Services\Payment\GatewayStatus;
use App\Services\Payment\GatewayTransaction;
use App\Services\Payment\PaymentGateway;

/**
 * Placeholder driver used while provider credentials are still missing.
 *
 * It refuses every transaction instead of pretending a payment succeeded, which
 * keeps the failure loud and obvious in development.
 */
class NullGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'null';
    }

    public function isConfigured(): bool
    {
        return false;
    }

    public function createTransaction(Order $order, PaymentMethod $method): GatewayTransaction
    {
        throw GatewayException::notConfigured($this->name());
    }

    public function fetchStatus(string $reference): GatewayStatus
    {
        throw GatewayException::notConfigured($this->name());
    }
}
