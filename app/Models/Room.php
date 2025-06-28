<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model {
	use HasFactory;

	protected $fillable = [ 
		'number',
		'floor',
		'notes',
		'dormitory_id',
		'room_type_id',
	];

	protected $casts = [ 
		'floor' => 'integer',
	];

	public function dormitory(): BelongsTo {
		return $this->belongsTo( Dormitory::class);
	}

	public function roomType(): BelongsTo {
		return $this->belongsTo( RoomType::class);
	}

	public function beds(): HasMany {
		return $this->hasMany( Bed::class);
	}
}
