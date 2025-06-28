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
		'iin', 'name', 'faculty', 'specialist', 'enrollment_year', 'gender', 'email',
		'phone_numbers', 'room_id', 'password', 'deal_number', 'city_id', 'files',
		'agree_to_dormitory_rules', 'status', 'role_id'
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
}