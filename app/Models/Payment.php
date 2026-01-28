<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Services\PaymentCalculationService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property float $amount
 * @property int|null $payment_type_id
 * @property \Carbon\Carbon|null $date_from
 * @property \Carbon\Carbon|null $date_to
 * @property string|null $deal_number
 * @property \Carbon\Carbon|null $deal_date
 * @property string|null $payment_check
 * @property PaymentStatus $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User $user
 * @property-read PaymentType|null $type
 */
class Payment extends Model
{
    use HasFactory;

    protected $table = 'payments';

    protected $fillable = [
        'user_id',
        'amount',
        'payment_type_id',
        'date_from',
        'date_to',
        'deal_number',
        'deal_date',
        'payment_check',
        'status',
    ];

    protected $casts = [
        'amount'     => 'decimal:2',
        'date_from'  => 'date',
        'date_to'    => 'date',
        'deal_date'  => 'date',
        'status'     => PaymentStatus::class,
    ];

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    /**
     * Get the user that owns the payment.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the payment type configuration.
     *
     * @return BelongsTo<PaymentType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(PaymentType::class, 'payment_type_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Create a payment for a user based on PaymentType configuration.
     * This method handles the calculation and date range setup.
     *
     * @param User $user The user to create payment for
     * @param PaymentType $paymentType The payment type configuration
     * @param Carbon|null $dateFrom Optional start date (defaults to now)
     * @param Carbon|null $dateTo Optional end date (calculated based on frequency if not provided)
     * @return self
     */
    public static function createForUser(
        User $user,
        PaymentType $paymentType,
        ?Carbon $dateFrom = null,
        ?Carbon $dateTo = null
    ): self {
        $calculationService = app(PaymentCalculationService::class);
        $amount = $calculationService->calculateAmount($user, $paymentType);

        $dateFrom = $dateFrom ?? Carbon::now();
        $dateTo = $dateTo ?? self::calculateEndDate($dateFrom, $paymentType);

        $dealNumber = self::generateDealNumber($user, $paymentType);

        return self::create([
            'user_id'         => $user->id,
            'payment_type_id' => $paymentType->id,
            'amount'          => $amount,
            'status'          => PaymentStatus::Pending,
            'date_from'       => $dateFrom,
            'date_to'         => $dateTo,
            'deal_date'       => $dateFrom->copy()->addDays(10), // Default 10 days deadline
            'deal_number'     => $dealNumber,
        ]);
    }

    /**
     * Calculate end date based on payment type frequency.
     */
    private static function calculateEndDate(Carbon $dateFrom, PaymentType $paymentType): Carbon
    {
        return match ($paymentType->frequency) {
            'monthly'     => $dateFrom->copy()->endOfMonth(),
            'semesterly'  => $dateFrom->copy()->addMonths(5)->endOfMonth(),
            'once'        => $dateFrom->copy()->addDays(30), // Default 30 days for one-time
            default       => $dateFrom->copy()->addMonth(),
        };
    }

    /**
     * Generate a unique deal number for the payment.
     */
    private static function generateDealNumber(User $user, PaymentType $paymentType): string
    {
        $prefix = strtoupper(substr($paymentType->name, 0, 3));
        $userId = str_pad((string) $user->id, 4, '0', STR_PAD_LEFT);
        $timestamp = now()->format('Ymd');

        return sprintf('%s-%s-%s-%s', $prefix, $userId, $timestamp, strtoupper(substr(uniqid(), -6)));
    }
}
