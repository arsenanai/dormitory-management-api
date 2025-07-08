<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable {
	/** @use HasFactory<\Database\Factories\UserFactory> */
	use HasFactory, Notifiable, HasApiTokens;

	protected $fillable = [ 
		'iin', 'student_id', 'name', 'first_name', 'last_name', 'date_of_birth', 'faculty', 'specialist',
		'course', 'year_of_study', 'enrollment_year', 'gender', 'blood_type', 'violations', 'parent_name',
		'parent_phone', 'mentor_name', 'mentor_email', 'email', 'phone', 'emergency_contact', 'phone_numbers',
		'room_id', 'password', 'deal_number', 'city_id', 'files', 'agree_to_dormitory_rules', 'status',
		'has_meal_plan', 'role_id'
	];

	protected $casts = [ 
		'phone_numbers'            => 'array',
		'files'                    => 'array',
		'role_id'                  => 'integer',
		'agree_to_dormitory_rules' => 'boolean',
	];

	protected $hidden = [ 
		'password',
		'remember_token',
	];

	/**
	 * The attributes that should be cast.
	 *
	 * @return array<string, string>
	 */
	protected function casts(): array {
		return [ 
			'email_verified_at' => 'datetime',
			'password'          => 'hashed',
			'phone_numbers'     => 'array',
			'files'             => 'array',
			'enrollment_year'   => 'integer',
		];
	}

	public function role() {
		return $this->belongsTo( Role::class);
	}

	public function hasRole( string $roleName ): bool {
		return $this->role?->name === $roleName;
	}

	// for admins
	public function dormitory() {
		return $this->hasOne( Dormitory::class, 'admin_id' );
	}

	public function payments() {
		return $this->hasMany( Payment::class);
	}

	public function room() {
		return $this->belongsTo( Room::class);
	}

	public function city() {
		return $this->belongsTo( City::class);
	}
}