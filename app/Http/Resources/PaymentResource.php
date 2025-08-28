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
			'id'                      => $this->id,
			'userId'                  => $this->user_id,
			'semester'                => $this->semester,
			'year'                    => $this->year,
			'semesterType'            => $this->semester_type,
			'amount'                  => $this->amount,
			'paymentApproved'         => $this->payment_approved,
			'dormitoryAccessApproved' => $this->dormitory_access_approved,
			'paymentApprovedAt'       => $this->payment_approved_at,
			'dormitoryApprovedAt'     => $this->dormitory_approved_at,
			'paymentApprovedBy'       => $this->payment_approved_by,
			'dormitoryApprovedBy'     => $this->dormitory_approved_by,
			'dueDate'                 => $this->due_date,
			'paidDate'                => $this->paid_date,
			'paymentNotes'            => $this->payment_notes,
			'dormitoryNotes'          => $this->dormitory_notes,
			'paymentStatus'           => $this->payment_status,
			'dormitoryStatus'         => $this->dormitory_status,
			'receiptFile'             => $this->receipt_file,
			'contractNumber'          => $this->contract_number,
			'contractDate'            => $this->contract_date,
			'paymentDate'             => $this->payment_date,
			'paymentMethod'           => $this->payment_method,
			'createdAt'               => $this->created_at,
			'updatedAt'               => $this->updated_at,

			// Compatibility aliases for frontend
			'payment_type'            => $this->semester_type,
			'payment_date'            => $this->paid_date,
			'status'                  => $this->payment_status,
			'description'             => $this->payment_notes,

			// Include related data
			'user'                    => $this->whenLoaded( 'user', function () {
				return [ 
					'id'           => $this->user->id,
					'name'         => $this->user->name,
					'firstName'    => $this->user->first_name,
					'lastName'     => $this->user->last_name,
					'email'        => $this->user->email,
					'phoneNumbers' => $this->user->phone_numbers,
				];
			} ),
		];
	}
}