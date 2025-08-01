<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SemesterPayment extends Model {
	use HasFactory;

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
		'contract_number',
		'contract_date',
		'payment_date',
		'payment_method',
		'receipt_file',
	];

	protected $casts = [ 
		'year'                      => 'integer',
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

	protected $appends = ['status'];

	public function user() {
		return $this->belongsTo( User::class);
	}

	public function paymentApprover() {
		return $this->belongsTo( User::class, 'payment_approved_by' );
	}

	public function dormitoryApprover() {
		return $this->belongsTo( User::class, 'dormitory_approved_by' );
	}

	public function canAccessDormitory() {
		return $this->payment_approved && $this->dormitory_access_approved;
	}

	public function isCurrentSemester() {
		$currentSemester = $this->getCurrentSemester();
		return $this->semester === $currentSemester;
	}

	public static function getCurrentSemester() {
		$currentMonth = now()->month;
		$currentYear = now()->year;

		if ( $currentMonth >= 8 ) {
			return $currentYear . '-fall';
		} elseif ( $currentMonth >= 1 && $currentMonth <= 5 ) {
			return $currentYear . '-spring';
		} else {
			return $currentYear . '-summer';
		}
	}

	public function scopeCurrentSemester( $query ) {
		return $query->where( 'semester', self::getCurrentSemester() );
	}

	public function scopeApproved( $query ) {
		return $query->where( 'payment_approved', true )
			->where( 'dormitory_access_approved', true );
	}

	public function getStatusAttribute() {
		return match($this->payment_status) {
			'approved' => 'completed',
			'rejected' => 'failed', 
			default => $this->payment_status
		};
	}
}
