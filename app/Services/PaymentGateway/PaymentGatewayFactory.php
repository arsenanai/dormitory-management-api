<?php

namespace App\Services\PaymentGateway;

class PaymentGatewayFactory
{
    /**
     * Resolve a gateway by name.
     * @param string $gatewayName  e.g. 'bank_check', 'kaspi', 'stripe'
     * @return PaymentGatewayInterface
     */
    public static function make(string $gatewayName): PaymentGatewayInterface
    {
        return match ($gatewayName) {
            'bank_check' => new BankCheckGateway(),
            // Future gateways:
            // 'kaspi'   => new KaspiGateway(),
            // 'stripe'  => new StripeGateway(),
            default => throw new \InvalidArgumentException("Unknown payment gateway: {$gatewayName}"),
        };
    }
}
