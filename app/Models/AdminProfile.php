<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string|null $position
 * @property string|null $department
 * @property string|null $office_phone
 * @property string|null $office_location
 * @property int|null $dormitory_id
 *
 * @template TFactory of \Illuminate\Database\Eloquent\Factories\Factory
 * @extends \Illuminate\Database\Eloquent\Model<TFactory>
 */
class AdminProfile extends Model
{
    /** @extends HasFactory<AdminProfile> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'position',
        'department',
        'office_phone',
        'office_location',
        'dormitory_id',
        // Add more admin-specific fields as needed
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<User, $this>
     */
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Dormitory, $this>
     */
    public function dormitory(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Dormitory::class);
    }
}
