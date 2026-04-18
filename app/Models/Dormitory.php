<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Dormitory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'capacity',
        'gender',
        'admin_id',
        'address',
        'description',
        'phone',
        'reception_phone',
        'medical_phone',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'admin_id' => 'integer',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<User, $this>
     */
    public function admin(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<Room>
     */
    public function rooms(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Room::class, 'dormitory_id');
    }
}
