<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminProfile extends Model {
	use HasFactory;

	protected $fillable = [ 
		'user_id',
		'position',
		'department',
		'office_phone',
		'office_location',
		// Add more admin-specific fields as needed
	];

	public function user() {
		return $this->belongsTo( User::class);
	}
}