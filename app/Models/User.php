<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\AdminProfile;

class User extends Authenticatable {
	/** @use HasFactory<\Database\Factories\UserFactory> */
	use HasFactory, Notifiable, HasApiTokens;

	protected $fillable = [
		'iin', 'name', 'first_name', 'last_name', 'email', 'email_verified_at', 'phone_numbers', 'room_id', 'dormitory_id', 'password', 'status', 'role_id', 'remember_token'
	];
	
	protected $casts = [
		'id'                => 'int',
		'email_verified_at' => 'datetime',
		'password'          => 'hashed',
		'phone_numbers'     => 'array',
	];

	protected $hidden = [
		'password',
		'remember_token',
	];

	protected $with = [];

	/**
	 * Get the user's phone numbers.
	 *
	 * @param  string|null  $value
	 * @return array
	 */
	public function getPhoneNumbersAttribute(?string $value): array
	{
		if (is_null($value)) {
			return [];
		}
		$decoded = json_decode($value, true);
		return is_array($decoded) ? $decoded : [];
	}

	public function role() {
		return $this->belongsTo( Role::class);
	}

	public function hasRole( string $roleName ): bool {
		return $this->role !== null && $this->role->name === $roleName;
	}

	public function adminDormitory() {
		return $this->hasOne(Dormitory::class, 'admin_id');
	}

	public function payments() {
		return $this->hasMany( Payment::class, 'user_id' );
	}

	public function room() {
		return $this->belongsTo( Room::class);
	}

	public function studentBed() {
		return $this->hasOne( Bed::class, 'user_id' );
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

	public function adminProfile() {
		return $this->hasOne( AdminProfile::class);
	}

	public function semesterPayments() {
		// This is now an alias for payments().
		return $this->hasMany( Payment::class);
	}

	public function currentSemesterPayment() {
		// This logic is deprecated as the new Payment model doesn't use semesters.
		// You might want to get the latest payment instead.
		return $this->hasOne( Payment::class)->latestOfMany();
	}

	public function canAccessDormitory() {
		if ( $this->hasRole( 'student' ) ) { // This logic needs to be re-evaluated
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