<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource {
	/**
	 * Transform the resource into an array.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray( Request $request ): array {
		return [ 
			'id'            => $this->id,
			'userId'        => $this->user_id,
			'amount'        => $this->amount,
			'dateFrom'      => $this->date_from,
			'dateTo'        => $this->date_to,
			'dealNumber'    => $this->deal_number,
			'dealDate'      => $this->deal_date,
			'paymentCheck'  => $this->payment_check ? $this->payment_check : null,
			'createdAt'     => $this->created_at, // Payment Date
			'updatedAt'     => $this->updated_at,

			// Include related data
			'user'                 => $this->whenLoaded( 'user', function () {
				// Ensure $this->user is not null before accessing its properties
				if ( ! $this->user ) {
					return null;
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
				];
			}),
		];
	}
}