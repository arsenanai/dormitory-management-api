<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
		'blood_type',
		'violations',
		'emergency_contact_name',
		'emergency_contact_phone',
		'emergency_contact_type',
		'emergency_contact_email',
		'emergency_contact_relationship',
		'medical_conditions',
		'dietary_restrictions',
		'program',
		'year_level',
		'nationality',
		'deal_number',
		'agree_to_dormitory_rules',
		'is_backup_list',
		'date_of_birth',
		'gender',
		'allergies',
		'files',
		'identification_type',
		'identification_number',
		'country',
		'region',
		'city',
	];

	protected $casts = [
		'enrollment_year'            => 'integer',
		'course'                     => 'string',
		'year_of_study'              => 'integer',
		'year_level'                 => 'integer',
		'agree_to_dormitory_rules'   => 'boolean',
		'registration_limit_reached' => 'boolean',
		'is_backup_list'             => 'boolean',
		'files'                      => 'array',
	];

	public function user(): BelongsTo {
		return $this->belongsTo( User::class);
	}
}
