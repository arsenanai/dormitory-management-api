<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
// use Illuminate\Database\Eloquent\Relations\HasMany;
// use App\Models\BedHistoryEntry;

class Bed extends Model {
	use HasFactory;

	protected $fillable = [ 
		'number',
		'status',
		'user_id',
		'room_id',
		'is_occupied',
		'reserved_for_staff',
	];

	protected $casts = [ 
		'status'             => 'string',
		'is_occupied'        => 'boolean',
		'reserved_for_staff' => 'boolean',
	];

	/**
	 * Relationship: The bed belongs to a user (optional).
	 */
	public function user(): BelongsTo {
		return $this->belongsTo( User::class);
	}

	/**
	 * Relationship: The bed belongs to a room.
	 */
	public function room(): BelongsTo {
		return $this->belongsTo( Room::class);
	}

	/**
	 * Relationship: The bed has a history of usage entries.
	 */
	// public function history(): HasMany {
	// 	return $this->hasMany( BedHistoryEntry::class);
	// }
}
