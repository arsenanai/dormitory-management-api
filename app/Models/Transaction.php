<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TransactionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property int $user_id
 * @property float $amount
 * @property string $payment_method
 * @property string|null $payment_check
 * @property string|null $gateway_transaction_id
 * @property array|null $gateway_response
 * @property TransactionStatus $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Payment> $payments
 */
class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'amount',
        'payment_method',
        'payment_check',
        'gateway_transaction_id',
        'gateway_response',
        'status',
    ];

    protected $casts = [
        'amount'           => 'decimal:2',
        'gateway_response' => 'array',
        'status'           => TransactionStatus::class,
    ];

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    /**
     * Get the user that owns the transaction.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the payments covered by this transaction.
     *
     * @return BelongsToMany<Payment, $this>
     */
    public function payments(): BelongsToMany
    {
        return $this->belongsToMany(Payment::class, 'payment_transaction')
                     ->withPivot('amount')
                     ->withTimestamps();
    }
}
