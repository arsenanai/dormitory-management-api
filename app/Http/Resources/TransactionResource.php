<?php

namespace App\Http\Resources;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Transaction
 */
class TransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'userId'               => $this->user_id,
            'amount'               => $this->amount,
            'paymentMethod'        => $this->payment_method,
            'paymentCheck'          => $this->payment_check,
            'gatewayTransactionId' => $this->gateway_transaction_id,
            'gatewayResponse'      => $this->gateway_response,
            'status'               => $this->status->value ?? null,
            'createdAt'            => $this->created_at,
            'updatedAt'            => $this->updated_at,
        ];
    }
}
