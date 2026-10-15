<?php

namespace App\Services\Payment;

use App\Services\Payment\Drivers\MidtransGateway;
use App\Services\Payment\Drivers\NullGateway;
use Illuminate\Contracts\Container\Container;
use RuntimeException;

/**
 * Resolves the configured payment driver.
 *
 * The concrete driver is bound in the service provider, so tests can swap the
 * contract binding without touching configuration or the network.
 */
class PaymentGatewayFactory
{
    public function __construct(
        private readonly Container $container,
        private readonly PaymentSettings $settings,
    ) {}

    public function driver(?string $name = null): PaymentGateway
    {
        if ($name === null) {
            return $this->container->make(PaymentGateway::class);
        }

        return match ($name) {
            'midtrans' => $this->container->make(MidtransGateway::class),
            'null', '' => $this->container->make(NullGateway::class),
            default => throw new RuntimeException("Unsupported payment driver [{$name}]."),
        };
    }

    /** The driver configured for this environment. */
    public function gateway(): PaymentGateway
    {
        return $this->driver();
    }
}
