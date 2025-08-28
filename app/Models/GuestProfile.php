<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GuestProfile extends Model {
	use HasFactory;

	protected $fillable = [ 
		'user_id',
		'purpose_of_visit',
		'host_name',
		'host_contact',
		'visit_start_date',
		'visit_end_date',
		'identification_type',
		'identification_number',
		'emergency_contact_name',
		'emergency_contact_phone',
		'is_approved',
		'daily_rate',
		'reminder',
	];

	protected $casts = [ 
		'visit_start_date' => 'datetime',
		'visit_end_date'   => 'datetime',
		'is_approved'      => 'boolean',
		'daily_rate'       => 'decimal:2',
	];

	public function user() {
		return $this->belongsTo( User::class);
	}

	public function isCurrentlyAuthorized() {
		$now = now()->toDateString();
		return $this->is_approved &&
			$this->visit_start_date <= $now &&
			$this->visit_end_date >= $now;
	}

	public function getTotalStayDays() {
		if ( ! $this->visit_start_date || ! $this->visit_end_date ) {
			return 0;
		}

		return $this->visit_start_date->diffInDays( $this->visit_end_date ) + 1;
	}

	public function getTotalCost() {
		return $this->getTotalStayDays() * $this->daily_rate;
	}
}
