<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PaymentType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PaymentType
 */
class PaymentTypeResource extends JsonResource
{
    /**
     * @return array{
     * id: int,
     * name: string,
     * frequency: string,
     * calculation_method: string,
     * fixed_amount: float|null,
     * target_role: string,
     * trigger_event: string|null,
     * created_at: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'name'               => $this->name,
            'frequency'          => $this->frequency,
            'calculation_method' => $this->calculation_method,
            'fixed_amount'       => $this->fixed_amount !== null ? (float) $this->fixed_amount : null,
            'target_role'        => $this->target_role,
            'trigger_event'      => $this->trigger_event,
            'created_at'         => $this->created_at?->toDateTimeString(),
        ];
    }
}
