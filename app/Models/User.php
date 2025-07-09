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
		'name', 'first_name', 'last_name', 'email', 'phone_numbers', 'room_id', 'password', 'status', 'role_id'
	];

	protected $casts = [ 
		'phone_numbers' => 'array',
		'role_id'       => 'integer',
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
	// ...existing code...

	public function role() {
		return $this->belongsTo( Role::class);
	}

	public function hasRole( string $roleName ): bool {
		return $this->role && $this->role->name === $roleName;
	}

	public function dormitory() {
		return $this->hasOne( Dormitory::class, 'admin_id' );
	}

	public function payments() {
		return $this->hasMany( \App\Models\SemesterPayment::class, 'user_id' );
	}

	public function room() {
		return $this->belongsTo( Room::class);
	}

	public function city() {
		return $this->belongsTo( City::class);
	}

	public function studentProfile() {
		return $this->hasOne( StudentProfile::class);
	}

	public function guestProfile() {
		return $this->hasOne( GuestProfile::class);
	}

	public function semesterPayments() {
		return $this->hasMany( SemesterPayment::class);
	}

	public function currentSemesterPayment() {
		return $this->hasOne( SemesterPayment::class)
			->where( 'semester', SemesterPayment::getCurrentSemester() );
	}

	public function canAccessDormitory() {
		if ( $this->hasRole( 'student' ) ) {
			$currentPayment = $this->currentSemesterPayment;
			return $currentPayment && $currentPayment->canAccessDormitory();
		}

		if ( $this->hasRole( 'guest' ) ) {
			$guestProfile = $this->guestProfile;
			return $guestProfile && $guestProfile->isCurrentlyAuthorized();
		}

		return true; // Admin, sudo, visitor roles have access
	}
}