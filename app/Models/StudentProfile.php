<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentProfile extends Model {
	use HasFactory;

	protected $fillable = [ 
		'user_id',
		'iin',
		'student_id',
		'faculty',
		'specialist',
		'course',
		'year_of_study',
		'enrollment_year',
		'enrollment_date',
		'blood_type',
		'violations',
		'parent_name',
		'parent_phone',
		'parent_email',
		'guardian_name',
		'guardian_phone',
		'mentor_name',
		'mentor_email',
		'emergency_contact_name',
		'emergency_contact_phone',
		'emergency_contact_relationship',
		'medical_conditions',
		'dietary_restrictions',
		'program',
		'year_level',
		'nationality',
		'deal_number',
		'agree_to_dormitory_rules',
		'has_meal_plan',
		'registration_limit_reached',
		'is_backup_list',
		'date_of_birth',
		'gender',
		'files',
		'city_id', // Keep for backward compatibility
		'country',
		'region',
		'city',
	];

	protected $casts = [ 
		'enrollment_date'            => 'date',
		'course'                     => 'string',
		'year_of_study'              => 'integer',
		'year_level'                 => 'integer',
		'agree_to_dormitory_rules'   => 'boolean',
		'has_meal_plan'              => 'boolean',
		'registration_limit_reached' => 'boolean',
		'is_backup_list'             => 'boolean',
	];

	public function user() {
		return $this->belongsTo( User::class);
	}

	public function semesterPayments() {
		return $this->hasMany( SemesterPayment::class, 'user_id', 'user_id' );
	}

	public function getCurrentSemesterPayment() {
		$currentSemester = $this->getCurrentSemester();
		return $this->semesterPayments()
			->where( 'semester', $currentSemester )
			->first();
	}

	public function hasCurrentSemesterAccess() {
		$currentPayment = $this->getCurrentSemesterPayment();
		return $currentPayment &&
			$currentPayment->payment_approved &&
			$currentPayment->dormitory_access_approved;
	}

	private function getCurrentSemester() {
		$currentMonth = now()->month;
		$currentYear = now()->year;

		if ( $currentMonth >= 8 ) {
			return $currentYear . '-fall';
		} elseif ( $currentMonth >= 1 && $currentMonth <= 5 ) {
			return $currentYear . '-spring';
		} else {
			return $currentYear . '-summer';
		}
	}
}
