<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use App\Services\ConfigurationService;
use App\Services\MailTemplateService;
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

    public int $timeout = 30;

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
        $locale = in_array(app()->getLocale(), [ 'en', 'kk', 'ru' ], true) ? app()->getLocale() : 'en';
        $service = app(MailTemplateService::class);
        $template = $service->getTemplate('payment_status_changed', $locale);
        if ($template !== null) {
            $amountFormatted = $this->formatAmount();
            $adminEmail = $this->resolveAdminEmail();
            $context = $service->contextForPaymentStatusChanged(
                $this->user,
                $this->payment,
                $amountFormatted,
                $this->currentStatus->value,
                $adminEmail,
            );
            $subject = $service->resolvePlaceholders($template['subject'], $context);
            return new Envelope(subject: (string) $subject);
        }
        $appName = (string) config('app.name');
        return new Envelope(subject: 'Payment Status Update – ' . $appName);
    }

    public function content(): Content
    {
        $locale = in_array(app()->getLocale(), [ 'en', 'kk', 'ru' ], true) ? app()->getLocale() : 'en';
        $service = app(MailTemplateService::class);
        $template = $service->getTemplate('payment_status_changed', $locale);
        if ($template !== null) {
            $amountFormatted = $this->formatAmount();
            $adminEmail = $this->resolveAdminEmail();
            $context = $service->contextForPaymentStatusChanged(
                $this->user,
                $this->payment,
                $amountFormatted,
                $this->currentStatus->value,
                $adminEmail,
            );
            $body = $service->resolvePlaceholders($template['body'], $context);
            return new Content(htmlString: $body);
        }
        $currencyCode = $this->getCurrencyCode();
        $symbol = self::CURRENCY_SYMBOLS[ $currencyCode ] ?? $currencyCode;
        $amountFormatted = number_format((float) $this->payment->amount, 2) . ' ' . $symbol;
        $userDormitoryId = $this->user->dormitory_id;
        $dormitory = $userDormitoryId !== null
            ? ($this->user->dormitory ?? \App\Models\Dormitory::with('admin')->find($userDormitoryId))
            : null;
        /** @var \App\Models\User|null $adminUser */
        $adminUser = $dormitory instanceof \App\Models\Dormitory ? $dormitory->admin : null;
        $adminEmail = $adminUser?->email;

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

    private function formatAmount(): string
    {
        $currencyCode = $this->getCurrencyCode();
        $symbol = self::CURRENCY_SYMBOLS[ $currencyCode ] ?? $currencyCode;
        return number_format((float) $this->payment->amount, 2) . ' ' . $symbol;
    }

    private function getCurrencyCode(): string
    {
        try {
            return strtoupper(
                (string) (app(ConfigurationService::class)->getConfiguration('currency_symbol') ?? 'USD')
            );
        } catch (\Throwable $e) {
            return 'USD';
        }
    }

    private function resolveAdminEmail(): ?string
    {
        $userDormitoryId = $this->user->dormitory_id;
        $dormitory = $userDormitoryId !== null
            ? ($this->user->dormitory ?? \App\Models\Dormitory::with('admin')->find($userDormitoryId))
            : null;
        /** @var \App\Models\User|null $adminUser */
        $adminUser = $dormitory instanceof \App\Models\Dormitory ? $dormitory->admin : null;
        return $adminUser?->email;
    }
}
