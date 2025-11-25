<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuestProfile extends Model {
	use HasFactory;

	protected $fillable = [
		'user_id',
		'purpose_of_visit',
		'host_name',
		'host_contact',
		'visit_start_date',
		'visit_end_date',
		'daily_rate',
		'identification_type',
		'identification_number',
		'emergency_contact_name',
		'emergency_contact_phone',
		'is_approved',
		'reminder',
		'bed_id',
	];

	protected $casts = [
		'is_approved' => 'boolean',
	];

	public function user(): BelongsTo {
		return $this->belongsTo( User::class);
	}

	public function bed(): BelongsTo {
		return $this->belongsTo( Bed::class);
	}
}
