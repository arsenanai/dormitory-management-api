<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'sender_id',
        'title',
        'content',
        'recipient_type',
        'dormitory_id',
        'room_id',
        'recipient_ids',
        'status',
        'sent_at',
        'receiver_id',
        'read_at',
        'type',
    ];

    protected $casts = [
        'sent_at'       => 'datetime',
        'read_at'       => 'datetime',
        'recipient_ids' => 'array',
    ];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function dormitory()
    {
        return $this->belongsTo(Dormitory::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }
}
