<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model {
	use HasFactory;

	protected $fillable = [ 
		'from',
		'to',
		'subject',
		'date_time',
		'content',
	];

	protected $casts = [ 
		'date_time' => 'datetime',
	];
}
