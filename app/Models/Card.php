<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Card extends Model {
	use HasFactory;

	protected $fillable = [ 
		'value',
		'description',
		'bg_class',
		'text_class',
		'icon',
		'icon_bg_class',
		'icon_text_class',
	];

	protected $casts = [ 
		'value' => 'integer',
	];
}
