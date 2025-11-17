<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoomType extends Model {
	use HasFactory;

	protected $fillable = [ 
		'name',
		'minimap',
		'beds',
		'photos',
		'capacity', 
		'daily_rate',
		'semester_rate',
	];

	protected $casts = [ 
		'beds'          => 'array',
		'photos'        => 'array',
		'daily_rate'    => 'decimal:2',
		'semester_rate' => 'decimal:2',
	];
}
