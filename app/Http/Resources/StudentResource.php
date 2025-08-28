<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource {
	/**
	 * Transform the resource into an array.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray( Request $request ): array {
		return [ 
			'id'               => $this->id,
			'name'             => $this->name,
			'firstName'        => $this->first_name,
			'lastName'         => $this->last_name,
			'email'            => $this->email,
			'emailVerifiedAt'  => $this->email_verified_at,
			'phoneNumbers'     => $this->phone_numbers,
			'roomId'           => $this->room_id,
			'roleId'           => $this->role_id,
			'createdAt'        => $this->created_at,
			'updatedAt'        => $this->updated_at,
			'deletedAt'        => $this->deleted_at,
			'studentId'        => $this->student_id,
			'birthDate'        => $this->birth_date,
			'bloodType'        => $this->blood_type,
			'course'           => $this->course,
			'faculty'          => $this->faculty,
			'specialty'        => $this->specialty,
			'enrollmentYear'   => $this->enrollment_year,
			'graduationYear'   => $this->graduation_year,
			'gender'           => $this->gender,
			'emergencyContact' => $this->emergency_contact,
			'emergencyPhone'   => $this->emergency_phone,
			'violations'       => $this->violations,
			'status'           => $this->status,
			'yearOfStudy'      => $this->year_of_study,
			'phone'            => $this->phone,
			'hasMealPlan'      => $this->has_meal_plan,
			'iin'              => $this->iin,
			'dateOfBirth'      => $this->date_of_birth,
			'cityId'           => $this->city_id,
			'files'            => $this->files,
			'cardNumber'       => $this->card_number,
			'dormitoryId'      => $this->dormitory_id,

			// Include related data with camelCase
			'role'             => $this->whenLoaded( 'role', function () {
				return [ 
					'id'        => $this->role->id,
					'name'      => $this->role->name,
					'createdAt' => $this->role->created_at,
					'updatedAt' => $this->role->updated_at,
				];
			} ),

			'studentProfile'   => $this->whenLoaded( 'studentProfile', function () {
				return [ 
					'id'                           => $this->studentProfile->id,
					'userId'                       => $this->studentProfile->user_id,
					'iin'                          => $this->studentProfile->iin,
					'studentId'                    => $this->studentProfile->student_id,
					'faculty'                      => $this->studentProfile->faculty,
					'specialist'                   => $this->studentProfile->specialist,
					'course'                       => $this->studentProfile->course,
					'yearOfStudy'                  => $this->studentProfile->year_of_study,
					'enrollmentYear'               => $this->studentProfile->enrollment_year,
					'enrollmentDate'               => $this->studentProfile->enrollment_date,
					'bloodType'                    => $this->studentProfile->blood_type,
					'violations'                   => $this->studentProfile->violations,
					'parentName'                   => $this->studentProfile->parent_name,
					'parentPhone'                  => $this->studentProfile->parent_phone,
					'parentEmail'                  => $this->studentProfile->parent_email,
					'guardianName'                 => $this->studentProfile->guardian_name,
					'guardianPhone'                => $this->studentProfile->guardian_phone,
					'mentorName'                   => $this->studentProfile->mentor_name,
					'mentorEmail'                  => $this->studentProfile->mentor_email,
					'emergencyContactName'         => $this->studentProfile->emergency_contact_name,
					'emergencyContactPhone'        => $this->studentProfile->emergency_contact_phone,
					'emergencyContactRelationship' => $this->studentProfile->emergency_contact_relationship,
					'medicalConditions'            => $this->studentProfile->medical_conditions,
					'dietaryRestrictions'          => $this->studentProfile->dietary_restrictions,
					'program'                      => $this->studentProfile->program,
					'yearLevel'                    => $this->studentProfile->year_level,
					'nationality'                  => $this->studentProfile->nationality,
					'dealNumber'                   => $this->studentProfile->deal_number,
					'agreeToDormitoryRules'        => $this->studentProfile->agree_to_dormitory_rules,
					'hasMealPlan'                  => $this->studentProfile->has_meal_plan,
					'registrationLimitReached'     => $this->studentProfile->registration_limit_reached,
					'isBackupList'                 => $this->studentProfile->is_backup_list,
					'dateOfBirth'                  => $this->studentProfile->date_of_birth,
					'gender'                       => $this->studentProfile->gender,
					'files'                        => $this->studentProfile->files,
					'cityId'                       => $this->studentProfile->city_id,
					'createdAt'                    => $this->studentProfile->created_at,
					'updatedAt'                    => $this->studentProfile->updated_at,
					'country'                      => $this->studentProfile->country,
					'region'                       => $this->studentProfile->region,
					'city'                         => $this->studentProfile->city,
				];
			} ),

			'room'             => $this->whenLoaded( 'room', function () {
				return [ 
					'id'          => $this->room->id,
					'number'      => $this->room->number,
					'floor'       => $this->room->floor,
					'notes'       => $this->room->notes,
					'dormitoryId' => $this->room->dormitory_id,
					'roomTypeId'  => $this->room->room_type_id,
					'createdAt'   => $this->room->created_at,
					'updatedAt'   => $this->room->updated_at,
					'isOccupied'  => $this->room->is_occupied,
					'quota'       => $this->room->quota,
					'dormitory'   => $this->room->relationLoaded( 'dormitory' ) ? [ 
						'id'          => $this->room->dormitory->id,
						'name'        => $this->room->dormitory->name,
						'address'     => $this->room->dormitory->address,
						'description' => $this->room->dormitory->description,
						'gender'      => $this->room->dormitory->gender,
						'capacity'    => $this->room->dormitory->capacity,
						'phone'       => $this->room->dormitory->phone,
						'adminId'     => $this->room->dormitory->admin_id,
						'createdAt'   => $this->room->dormitory->created_at,
						'updatedAt'   => $this->room->dormitory->updated_at,
					] : null,
				];
			} ),
		];
	}
}