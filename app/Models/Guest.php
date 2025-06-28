<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Guest extends Model {
	use HasFactory;

	protected $fillable = [ 
		'name',
		'surname',
		'enter_date',
		'exit_date',
		'telephone',
		'room',
		'payment',
	];

	protected $casts = [ 
		'enter_date' => 'datetime',
		'exit_date'  => 'datetime',
	];
}
