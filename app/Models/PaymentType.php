<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $frequency
 * @property string $calculation_method
 * @property float|null $fixed_amount
 * @property string $target_role
 * @property string|null $trigger_event
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static Builder|static forRole(string $role)
 * @method static Builder|static forTriggerEvent(string $event)
 */
class PaymentType extends Model
{
    /** @use HasFactory<\Database\Factories\PaymentTypeFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'frequency',
        'calculation_method',
        'fixed_amount',
        'target_role',
        'trigger_event',
    ];

    protected $casts = [
        'fixed_amount' => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isFixed(): bool
    {
        return $this->calculation_method === 'fixed';
    }

    public function isMonthly(): bool
    {
        return $this->frequency === 'monthly';
    }

    public function isForRegistration(): bool
    {
        return $this->trigger_event === 'registration';
    }

    public function isForNewSemester(): bool
    {
        return $this->trigger_event === 'new_semester';
    }

    public function isForNewMonth(): bool
    {
        return $this->trigger_event === 'new_month';
    }

    public function isForNewBooking(): bool
    {
        return $this->trigger_event === 'new_booking';
    }

    public function isForRoomTypeChange(): bool
    {
        return $this->trigger_event === 'room_type_change';
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * @param Builder<PaymentType> $query
     */
    public function scopeForRole(Builder $query, string $role): void
    {
        $query->where('target_role', $role);
    }

    /**
     * @param Builder<PaymentType> $query
     */
    public function scopeForTriggerEvent(Builder $query, string $event): void
    {
        $query->where('trigger_event', $event);
    }

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
