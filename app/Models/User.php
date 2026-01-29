<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\PaymentStatus;
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

    /*
    |--------------------------------------------------------------------------
    | Payment Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Create pending payments for this user based on configured PaymentTypes for a specific trigger event.
     * This method finds all PaymentTypes that match the user's role and the trigger event,
     * then creates pending payments for each.
     *
     * @param string $triggerEvent The trigger event (registration, new_semester, new_month, new_booking, room_type_change)
     * @return array<Payment> Array of created Payment models
     */
    public function createPaymentsForTriggerEvent(string $triggerEvent): array
    {
        if (!$this->room_id) {
            return [];
        }

        $roleName = $this->role?->name;
        if (!$roleName) {
            return [];
        }

        // Get payment types that match the trigger event OR have no trigger_event (applies to all)
        $paymentTypes = PaymentType::forRole($roleName)
            ->where(function ($query) use ($triggerEvent) {
                $query->where('trigger_event', $triggerEvent)
                      ->orWhereNull('trigger_event');
            })
            ->get();

        $createdPayments = [];

        foreach ($paymentTypes as $paymentType) {
            // Check if payment already exists for this type and period
            if ($this->hasExistingPaymentForType($paymentType, $triggerEvent)) {
                continue;
            }

            $payment = Payment::createForUser($this, $paymentType);
            $createdPayments[] = $payment;
        }

        return $createdPayments;
    }

    /**
     * Check if user already has a payment for the given PaymentType and trigger event.
     */
    private function hasExistingPaymentForType(PaymentType $paymentType, string $triggerEvent): bool
    {
        $query = $this->payments()
            ->where('payment_type_id', $paymentType->id)
            ->where('status', PaymentStatus::Pending);

        // For monthly payments, check current month
        if ($paymentType->isMonthly()) {
            $query->whereMonth('created_at', now()->month)
                  ->whereYear('created_at', now()->year);
        }
        // For semesterly payments, check if exists in current semester period
        elseif ($paymentType->frequency === 'semesterly') {
            // Check if payment exists in the last 6 months (semester period)
            $query->where('created_at', '>=', now()->subMonths(6));
        }
        // For one-time payments, check if any exists
        else {
            // For registration/booking, only create once per user
            if (in_array($triggerEvent, ['registration', 'new_booking'])) {
                return $query->exists();
            }
        }

        return $query->exists();
    }

    /**
     * Whether this student has any completed (paid) semester-rent payment that overlaps
     * the current period. Used to warn when changing room type (e.g. standard ↔ lux).
     */
    public function hasPaidSemesterRentPayment(): bool
    {
        $today = now()->copy()->startOfDay();

        return $this->payments()
            ->where('status', PaymentStatus::Completed)
            ->whereHas('type', function ($q) {
                $q->where('target_role', 'student')
                  ->where('frequency', 'semesterly');
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('date_to')
                  ->orWhere('date_to', '>=', $today);
            })
            ->exists();
    }

    /**
     * Whether this guest has any completed (paid) stay payment overlapping their
     * visit period. Used to warn when changing room type (e.g. standard ↔ lux).
     */
    public function hasPaidStayPayment(): bool
    {
        $profile = $this->guestProfile;
        if (!$profile || !$profile->visit_start_date || !$profile->visit_end_date) {
            return false;
        }

        $start = \Carbon\Carbon::parse($profile->visit_start_date)->copy()->startOfDay();
        $end = \Carbon\Carbon::parse($profile->visit_end_date)->copy()->endOfDay();

        return $this->payments()
            ->where('status', PaymentStatus::Completed)
            ->whereHas('type', function ($q) {
                $q->where('target_role', 'guest');
            })
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('date_from', [ $start, $end ])
                  ->orWhereBetween('date_to', [ $start, $end ])
                  ->orWhere(function ($q2) use ($start, $end) {
                      $q2->where('date_from', '<=', $start)
                         ->where('date_to', '>=', $end);
                  });
            })
            ->exists();
    }
}
