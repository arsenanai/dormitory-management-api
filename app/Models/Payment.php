<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model {
	use HasFactory;

	protected $table = 'semester_payments';

	protected $fillable = [ 
		'user_id',
		'semester',
		'year',
		'semester_type',
		'amount',
		'payment_approved',
		'dormitory_access_approved',
		'payment_approved_at',
		'dormitory_approved_at',
		'payment_approved_by',
		'dormitory_approved_by',
		'due_date',
		'paid_date',
		'payment_notes',
		'dormitory_notes',
		'payment_status',
		'dormitory_status',
		'receipt_file',
		'contract_number',
		'contract_date',
		'payment_date',
		'payment_method',
	];

	protected $casts = [ 
		'amount'                    => 'decimal:2',
		'payment_approved'          => 'boolean',
		'dormitory_access_approved' => 'boolean',
		'payment_approved_at'       => 'datetime',
		'dormitory_approved_at'     => 'datetime',
		'due_date'                  => 'date',
		'paid_date'                 => 'date',
		'contract_date'             => 'date',
		'payment_date'              => 'date',
	];

	/**
	 * Get the user that owns the payment.
	 */
	public function user(): BelongsTo {
		return $this->belongsTo( User::class);
	}

	/**
	 * Get the user who approved the payment.
	 */
	public function paymentApprovedBy(): BelongsTo {
		return $this->belongsTo( User::class, 'payment_approved_by' );
	}

	/**
	 * Get the user who approved the dormitory access.
	 */
	public function dormitoryApprovedBy(): BelongsTo {
		return $this->belongsTo( User::class, 'dormitory_approved_by' );
	}

	/**
	 * Get the payment type (alias for semester_type for compatibility).
	 */
	public function getPaymentTypeAttribute(): string {
		return $this->semester_type;
	}

	/**
	 * Get the status (alias for payment_status for compatibility).
	 */
	public function getStatusAttribute(): string {
		return $this->payment_status;
	}

	/**
	 * Get the description (alias for payment_notes for compatibility).
	 */
	public function getDescriptionAttribute(): ?string {
		return $this->payment_notes;
	}
}
