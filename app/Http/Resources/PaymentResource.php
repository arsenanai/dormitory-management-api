<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'userId'          => $this->user_id,
            'amount'          => $this->amount,
            'paidAmount'      => $this->paid_amount ?? 0,
            'remainingAmount' => $this->remainingAmount(),
            'paymentType'     => $this->type?->name,
            'dateFrom'        => $this->date_from,
            'dateTo'          => $this->date_to,
            'dealNumber'      => $this->deal_number,
            'dealDate'        => $this->deal_date,
            'status'          => $this->status->value ?? null,
            'createdAt'       => $this->created_at,
            'updatedAt'       => $this->updated_at,
        ];
    }
}
