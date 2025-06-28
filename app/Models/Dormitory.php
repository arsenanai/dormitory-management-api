<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Dormitory extends Model {
	use HasFactory;

	protected $fillable = [ 
		'name',
		'capacity',
		'gender',
		'admin_id',
		'registered',
		'free_beds',
		'rooms',
	];

	protected $casts = [ 
		'capacity'   => 'integer',
		'registered' => 'integer',
		'free_beds'  => 'integer',
		'rooms'      => 'integer',
	];

	public function admin() {
		return $this->belongsTo( User::class, 'admin_id' );
	}
}
