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

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function rooms()
    {
        return $this->hasMany(Room::class, 'dormitory_id');
    }
}
