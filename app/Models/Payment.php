<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Enums\PaymentStatus;

class Payment extends Model {
	use HasFactory;

	protected $table = 'payments';

	protected $fillable = [ 
		'user_id',
		'amount',
		'date_from',
		'date_to',
		'deal_number',
		'deal_date',
		'payment_check',
		'status',
	];

	protected $casts = [ 
		'amount'                    => 'decimal:2',
		'date_from'                 => 'date',
		'date_to'                   => 'date',
		'deal_date'                 => 'date',
		'status'					=> PaymentStatus::class
	];

	/**
	 * Get the user that owns the payment.
	 */
	public function user(): BelongsTo {
		return $this->belongsTo( User::class);
	}
}
