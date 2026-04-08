<?php

namespace App\Services\PaymentGateway;

use App\Models\Transaction;

interface PaymentGatewayInterface
{
    /**
     * Get the gateway identifier name (e.g. 'bank_check', 'kaspi', 'stripe')
     */
    public function getName(): string;

    /**
     * Initiate a payment. Returns gateway-specific data (redirect URL, QR code, etc.)
     * @param Transaction $transaction  The transaction record
     * @param array $metadata           Additional data (return URLs, etc.)
     * @return array  Gateway response (e.g. ['redirect_url' => '...'] or ['status' => 'pending'])
     */
    public function initiatePayment(Transaction $transaction, array $metadata = []): array;

    /**
     * Process a callback/webhook from the gateway to confirm payment.
     * @param array $payload  Raw webhook/callback data from gateway
     * @return Transaction  Updated transaction with confirmed status
     */
    public function handleCallback(array $payload): Transaction;

    /**
     * Check the current status of a transaction with the gateway.
     * @param Transaction $transaction
     * @return string  Status string (completed, pending, failed, etc.)
     */
    public function checkStatus(Transaction $transaction): string;

    /**
     * Initiate a refund for a completed transaction.
     * @param Transaction $transaction
     * @param float|null $amount  Partial refund amount, null = full refund
     * @return array  Gateway refund response
     */
    public function refund(Transaction $transaction, ?float $amount = null): array;
}
