<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @use HasFactory<\Database\Factories\BedFactory> */
class Bed extends Model
{
    use HasFactory;

    protected $fillable = [
        'bed_number',
        'status',
        'user_id',
        'room_id',
        'reserved_for_staff',
        'is_occupied',
    ];

    protected $casts = [
        'status'             => 'string',
        'reserved_for_staff' => 'boolean',
        'is_occupied'        => 'boolean',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<GuestProfile, $this>
     */
    public function guestProfiles(): HasMany
    {
        return $this->hasMany(GuestProfile::class);
    }
}
