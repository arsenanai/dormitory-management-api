<?php

namespace App\Services\PaymentGateway;

use App\Enums\TransactionStatus;
use App\Models\Transaction;

class BankCheckGateway implements PaymentGatewayInterface
{
    public function getName(): string
    {
        return 'bank_check';
    }

    public function initiatePayment(Transaction $transaction, array $metadata = []): array
    {
        // For bank check: transaction is created with status 'pending'
        // User will upload check file separately
        return ['status' => 'pending', 'message' => 'Upload bank check to proceed'];
    }

    public function handleCallback(array $payload): Transaction
    {
        // Manual verification by admin — this is called when admin approves
        $transaction = Transaction::findOrFail($payload['transaction_id']);
        $transaction->update(['status' => TransactionStatus::Completed]);
        return $transaction;
    }

    public function checkStatus(Transaction $transaction): string
    {
        return $transaction->status->value;
    }

    public function refund(Transaction $transaction, ?float $amount = null): array
    {
        // Manual refund process
        $transaction->update(['status' => TransactionStatus::Refunded]);
        return ['status' => 'refunded'];
    }
}
