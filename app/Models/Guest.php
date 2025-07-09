<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Guest extends Model {
	use HasFactory;

	protected $fillable = [ 
		'name',
		'email',
		'phone',
		'room_id',
		'check_in_date',
		'check_out_date',
		'payment_status',
		'total_amount',
		'notes',
	];

	protected $casts = [ 
		'check_in_date'  => 'date',
		'check_out_date' => 'date',
		'total_amount'   => 'decimal:2',
	];

	/**
	 * Get the room that the guest is staying in.
	 */
	public function room() {
		return $this->belongsTo( Room::class);
	}
}
