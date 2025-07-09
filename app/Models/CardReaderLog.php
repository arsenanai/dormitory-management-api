<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CardReaderLog extends Model {
	/** @use HasFactory<\Database\Factories\CardReaderLogFactory> */
	use HasFactory;

	protected $fillable = [ 
		'user_id',
		'card_number',
		'entry_time',
		'exit_time',
		'location',
		'action',
	];

	protected $casts = [ 
		'entry_time' => 'datetime',
		'exit_time'  => 'datetime',
	];

	/**
	 * Get the user associated with this card reader log.
	 */
	public function user() {
		return $this->belongsTo( User::class);
	}

	/**
	 * Check if the user is currently inside
	 */
	public function isCurrentlyInside() {
		return $this->action === 'entry' && is_null( $this->exit_time );
	}
}
