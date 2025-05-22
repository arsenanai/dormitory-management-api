<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'iin',
        'name',
        'faculty',
        'specialist',
        'enrollment_year',
        'gender',
        'email',
        'phone_numbers',
        'dormitory',
        'room',
        'password',
        'deal_number',
        'country',
        'region',
        'city',
        'files',
        'agree_to_dormitory_rules',
        'add_to_reserve_list',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'phone_numbers' => 'array',
            'files' => 'array',
            'agree_to_dormitory_rules' => 'boolean',
            'add_to_reserve_list' => 'boolean',
            'enrollment_year' => 'string',
        ];
    }
}