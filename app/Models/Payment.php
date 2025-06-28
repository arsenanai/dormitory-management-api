<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model {
	use HasFactory;

	protected $fillable = [ 
		'name',
		'surname',
		'contract_date',
		'contract_number',
		'amount',
	];

	protected $casts = [ 
		'contract_date' => 'datetime',
	];
}