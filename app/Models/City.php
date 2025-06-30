<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class City extends Model {
	use HasFactory;

	protected $fillable = [ 
		'name',
		'language',
		'region_id',
	];

	/**
	 * Relationship: The city belongs to a region.
	 */
	public function region(): BelongsTo {
		return $this->belongsTo( Region::class);
	}
}
