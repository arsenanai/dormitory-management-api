<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

/**
 * @mixin \App\Models\Transaction
 */
class TransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'userId' => $this->user_id,
            'amount' => $this->amount,
            'paymentMethod' => $this->payment_method,
            'paymentCheck' => $this->payment_check,
            'gatewayTransactionId' => $this->gateway_transaction_id,
            'gatewayResponse' => $this->gateway_response,
            'status' => $this->status->value,
            'createdAt' => $this->created_at ? Carbon::parse($this->created_at)->format('Y-m-d H:i:s') : null,
            'updatedAt' => $this->updated_at ? Carbon::parse($this->updated_at)->format('Y-m-d H:i:s') : null,
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                    'role' => $this->user->role?->name,
                ];
            }),
            'payments' => $this->whenLoaded('payments', function () {
                return $this->payments->map(function ($payment) {
                    return [
                        'id' => $payment->id,
                        'amount' => $payment->amount,
                        'pivotAmount' => $payment->pivot->amount,
                        'status' => $payment->status->value,
                        'paymentType' => $payment->type?->name,
                    ];
                });
            }),
        ];
    }
}
