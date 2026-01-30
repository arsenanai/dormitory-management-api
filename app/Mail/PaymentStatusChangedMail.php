<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use App\Services\ConfigurationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class PaymentStatusChangedMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /**
     * Number of seconds the job can run before timing out.
     * Prevents the job from hanging indefinitely if SMTP is slow/unreachable.
     */
    public int $timeout = 30;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 3;

    private const CURRENCY_SYMBOLS = [
        'USD' => '$',
        'KZT' => '₸',
        'RUB' => '₽',
        'EUR' => '€',
        'GBP' => '£',
    ];

    public function __construct(
        public readonly User $user,
        public readonly Payment $payment,
        public readonly PaymentStatus $currentStatus,
        public readonly ?PaymentStatus $oldStatus = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Payment Status Update – Dormitory Management System',
        );
    }

    public function content(): Content
    {
        try {
            $currencyCode = strtoupper(
                (string) (app(ConfigurationService::class)->getConfiguration('currency_symbol') ?? 'USD')
            );
        } catch (\Throwable $e) {
            $currencyCode = 'USD';
        }
        $symbol = self::CURRENCY_SYMBOLS[ $currencyCode ] ?? $currencyCode;
        $amountFormatted = number_format((float) $this->payment->amount, 2) . ' ' . $symbol;

        $dormitory = $this->user->dormitory_id
            ? $this->user->dormitory ?? \App\Models\Dormitory::with('admin')->find($this->user->dormitory_id)
            : null;
        $adminEmail = $dormitory?->admin?->email;

        return new Content(
            view: 'emails.payment-status-changed',
            with: [
                'user'            => $this->user,
                'payment'         => $this->payment,
                'currentStatus'   => $this->currentStatus->value,
                'amountFormatted' => $amountFormatted,
                'adminEmail'      => $adminEmail,
            ],
        );
    }
}
