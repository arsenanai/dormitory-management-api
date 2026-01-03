<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use Notifiable;
    use HasApiTokens;
    use SoftDeletes;

    protected $fillable = [
        'name', 'first_name', 'last_name', 'email', 'email_verified_at', 'phone_numbers', 'password', 'status', 'role_id', 'remember_token', 'room_id', 'dormitory_id',
    ];

    protected $casts = [
        'id'                => 'int',
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
        'phone_numbers'     => 'array',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $with = [];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function hasRole(string $roleName): bool
    {
        return $this->role !== null && $this->role->name === $roleName;
    }

    public function adminDormitory(): HasOne
    {
        return $this->hasOne(Dormitory::class, 'admin_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function studentBed(): HasOne
    {
        return $this->hasOne(Bed::class, 'user_id');
    }

    public function bed(): HasOne
    {
        return $this->hasOne(Bed::class, 'user_id');
    }

    public function dormitory(): BelongsTo
    {
        return $this->belongsTo(Dormitory::class);
    }

    public function studentProfile(): HasOne
    {
        return $this->hasOne(StudentProfile::class, 'user_id');
    }

    public function guestProfile(): HasOne
    {
        return $this->hasOne(GuestProfile::class, 'user_id');
    }

    public function adminProfile(): HasOne
    {
        return $this->hasOne(AdminProfile::class, 'user_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'user_id');
    }

    public function currentSemesterPayment(): HasOne
    {
        return $this->hasOne(Payment::class, 'user_id')->latestOfMany();
    }
}
