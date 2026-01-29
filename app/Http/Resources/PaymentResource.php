<?php

namespace App\Http\Resources;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read int $id
 * @property-read int $user_id
 * @property-read float $amount
 * @property-read string $date_from
 * @property-read string $date_to
 * @property-read string|null $deal_number
 * @property-read string|null $deal_date
 * @property-read string|null $payment_check
 * @property-read string $created_at
 * @property-read string $updated_at
 * @property-read User|null $user
 */
class PaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'userId'        => $this->user_id,
            'amount'        => $this->amount,
            'paymentType'   => $this->type?->name ?? null,
            'dateFrom'      => $this->date_from,
            'dateTo'        => $this->date_to,
            'dealNumber'    => $this->deal_number,
            'dealDate'      => $this->deal_date,
            'paymentCheck'  => $this->payment_check ? $this->payment_check : null,
            'status'        => $this->status->value,
            'createdAt'     => $this->created_at, // Payment Date
            'updatedAt'     => $this->updated_at,

            // Include related data
            'user'                 => $this->whenLoaded('user', function () {
                // Ensure $this->user is not null before accessing its properties
                if (! $this->user) {
                    return;
                }
                return [
                    'id'           => $this->user->id, // Safe because we checked $this->user
                    'name'         => $this->user->name, // Safe
                    'firstName'    => $this->user->first_name,
                    'lastName'     => $this->user->last_name,
                    'email'        => $this->user->email, // Safe
                    'phoneNumbers' => $this->user->phone_numbers, // Safe
                    'role'         => $this->when($this->user->relationLoaded('role') && $this->user->role, [
                        'id'   => $this->user->role->id,
                        'name' => $this->user->role->name,
                    ]),
                    'student_profile' => $this->when(
                        $this->user->relationLoaded('role') && $this->user->role && $this->user->role->name === 'student',
                        function () {
                            // Always include student_profile for students, even if IIN is empty
                            if ($this->user->relationLoaded('studentProfile') && $this->user->studentProfile) {
                                $iin = $this->user->studentProfile->iin ?? null;
                                // Return empty string as null for consistency
                                $iin = ($iin === '' || $iin === null) ? null : $iin;
                                return [
                                    'iin' => $iin,
                                ];
                            }
                            // Student profile doesn't exist yet, return null IIN
                            return [
                                'iin' => null,
                            ];
                        }
                    ),
                    'guest_profile' => $this->when(
                        $this->user->relationLoaded('role') && $this->user->role && $this->user->role->name === 'guest',
                        function () {
                            // Always include guest_profile for guests, even if identification_number is empty
                            if ($this->user->relationLoaded('guestProfile') && $this->user->guestProfile) {
                                $identificationType = $this->user->guestProfile->identification_type ?? null;
                                $identificationNumber = $this->user->guestProfile->identification_number ?? null;
                                // Return empty string as null for consistency
                                $identificationNumber = ($identificationNumber === '' || $identificationNumber === null) ? null : $identificationNumber;
                                return [
                                    'identification_type' => $identificationType,
                                    'identification_number' => $identificationNumber,
                                ];
                            }
                            // Guest profile doesn't exist yet, return null values
                            return [
                                'identification_type' => null,
                                'identification_number' => null,
                            ];
                        }
                    ),
                    'room' => $this->when(
                        $this->user->relationLoaded('room') && $this->user->room !== null,
                        function () {
                            if (!$this->user->room) {
                                return;
                            }
                            return [
                                'number' => $this->user->room->number ?? null,
                                'room_type' => $this->when($this->user->room && $this->user->room->relationLoaded('roomType') && $this->user->room->roomType, [
                                    'name' => $this->user->room->roomType->name ?? null,
                                ]),
                            ];
                        }
                    ),
                ];
            }),
        ];
    }
}
