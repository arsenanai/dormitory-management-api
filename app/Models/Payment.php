<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model {
	use HasFactory;

	protected $fillable = [ 
		'user_id',
		'transaction_id',
		'name',
		'surname',
		'contract_date',
		'contract_number',
		'amount',
		'payment_date',
		'payment_method',
		'receipt_file',
		'status',
	];

	protected $casts = [ 
		'contract_date' => 'datetime',
		'payment_date'  => 'datetime',
	];

	public function user() {
		return $this->belongsTo( User::class);
	}
}