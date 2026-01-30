<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\MailEventOccurred;
use App\Jobs\SendUserRegisteredMail;
use App\Mail\MessageSentMail;
use App\Mail\PaymentStatusChangedMail;
use App\Mail\UserRegisteredMail;
use App\Mail\UserStatusChangedMail;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

final class ProcessMailEvent
{
    /**
     * Build mailable and resolve recipient emails for the given mail event.
     *
     * @return array{0: Mailable|array<Mailable>, 1: array<string>} [mailable(s), recipient emails]
     */
    private function resolveMailableAndRecipients(MailEventOccurred $event): array
    {
        $payload = $event->payload;
        $eventName = $event->eventName;

        return match ($eventName) {
            'user.registered'        => $this->resolveUserRegistered($payload),
            'payment.status_changed' => $this->resolvePaymentStatusChanged($payload),
            'user.status_changed'    => $this->resolveUserStatusChanged($payload),
            'message.sent'           => $this->resolveMessageSent($payload),
            default                  => [ [], [] ],
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: array<Mailable>, 1: array<string>}
     */
    private function resolveUserRegistered(array $payload): array
    {
        $user = $payload['user'] ?? null;
        if (! $user instanceof \App\Models\User) {
            return [ [], [] ];
        }
        $locale = isset($payload['locale']) && in_array($payload['locale'], [ 'en', 'kk', 'ru' ], true)
            ? (string) $payload['locale']
            : 'en';

        return [
            [ new UserRegisteredMail($user, $locale) ],
            $this->emailsForUser($user),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: array<Mailable>, 1: array<string>}
     */
    private function resolvePaymentStatusChanged(array $payload): array
    {
        $payment = $payload['payment'] ?? null;
        $oldStatus = $payload['old_status'] ?? null;
        $newStatus = $payload['new_status'] ?? null;
        if (! $payment instanceof \App\Models\Payment
            || ! $oldStatus instanceof \App\Enums\PaymentStatus
            || ! $newStatus instanceof \App\Enums\PaymentStatus
        ) {
            return [ [], [] ];
        }
        $user = $payment->user;
        if (! $user instanceof \App\Models\User) {
            return [ [], [] ];
        }

        return [
            [ new PaymentStatusChangedMail($user, $payment, $newStatus, $oldStatus) ],
            $this->emailsForUser($user),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: array<Mailable>, 1: array<string>}
     */
    private function resolveUserStatusChanged(array $payload): array
    {
        $user = $payload['user'] ?? null;
        $newStatus = $payload['new_status'] ?? null;
        if (! $user instanceof \App\Models\User || ! is_string($newStatus)) {
            return [ [], [] ];
        }

        return [
            [ new UserStatusChangedMail($user, $newStatus) ],
            $this->emailsForUser($user),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: array<Mailable>, 1: array<string>}
     */
    private function resolveMessageSent(array $payload): array
    {
        $message = $payload['message'] ?? null;
        $recipients = $payload['recipients'] ?? null;
        if (! $message instanceof \App\Models\Message
            || (! $recipients instanceof Collection && ! is_array($recipients))
        ) {
            return [ [], [] ];
        }
        $mailables = [];
        $emails = [];

        foreach ($recipients as $recipient) {
            if (! $recipient instanceof \App\Models\User) {
                continue;
            }
            $addr = $this->normalizeEmail($recipient->email);
            if ($addr === null) {
                continue;
            }
            $mailables[] = new MessageSentMail($recipient, $message);
            $emails[] = $addr;
        }

        return [ $mailables, $emails ];
    }

    /**
     * @return array<string>
     */
    private function emailsForUser(\App\Models\User $user): array
    {
        $addr = $this->normalizeEmail($user->email);
        return $addr === null ? [] : [ $addr ];
    }

    private function normalizeEmail(?string $email): ?string
    {
        if ($email === null || $email === '') {
            return null;
        }
        $email = trim($email);
        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    private function handleUserRegistered(MailEventOccurred $event): void
    {
        $user = $event->payload['user'] ?? null;
        if (! $user instanceof \App\Models\User) {
            return;
        }
        $key = 'mail_queued:user.registered:' . $user->id;
        if (! Cache::add($key, true, 120)) {
            Log::info('Skip duplicate user.registered queue', [ 'user_id' => $user->id ]);
            return;
        }
        $locale = isset($event->payload['locale']) && in_array($event->payload['locale'], [ 'en', 'kk', 'ru' ], true)
            ? (string) $event->payload['locale']
            : 'en';
        SendUserRegisteredMail::dispatch($user, $locale);
    }

    public function handle(MailEventOccurred $event): void
    {
        $mailEvents = config('mail_events');
        $config = is_array($mailEvents) ? ($mailEvents[ $event->eventName ] ?? null) : null;
        if (! is_array($config)) {
            Log::warning('Mail event not configured', [
                'event' => $event->eventName,
            ]);
            return;
        }

        if ($event->eventName === 'user.registered') {
            $this->handleUserRegistered($event);
            return;
        }

        if ($event->eventName === 'user.status_changed') {
            $user = $event->payload['user'] ?? null;
            if ($user instanceof \App\Models\User) {
                $key = 'mail_queued:user.status_changed:' . $user->id;
                if (! Cache::add($key, true, 60)) {
                    Log::info('Skip duplicate user.status_changed mail', [ 'user_id' => $user->id ]);
                    return;
                }
            }
        }

        if ($event->eventName === 'payment.status_changed') {
            $payment = $event->payload['payment'] ?? null;
            if ($payment instanceof \App\Models\Payment) {
                $key = 'mail_queued:payment.status_changed:' . $payment->id;
                if (! Cache::add($key, true, 60)) {
                    Log::info('Skip duplicate payment.status_changed mail', [ 'payment_id' => $payment->id ]);
                    return;
                }
            }
        }

        try {
            [ $mailables, $emails ] = $this->resolveMailableAndRecipients($event);
        } catch (\Throwable $e) {
            $this->logMailFailure($event->eventName, null, $e);
            return;
        }

        if ($emails === []) {
            Log::info('Mail skipped: no valid recipient', [
                'event' => $event->eventName,
            ]);
            return;
        }

        /** @var array<Mailable> $mailables */
        $messages = is_array($mailables) ? $mailables : [ $mailables ];
        $numRecipients = count($emails);

        if (count($messages) === 1 && $numRecipients === 1) {
            $this->sendOne($messages[0], $emails[0], $event->eventName);
            return;
        }

        foreach ($messages as $i => $mailable) {
            $email = $emails[ $i ] ?? null;
            if ($email === null) {
                continue;
            }
            $this->sendOne($mailable, $email, $event->eventName);
        }
    }

    private function sendOne(Mailable $mailable, string $to, string $eventName): void
    {
        try {
            Mail::to($to)->queue($mailable);
        } catch (\Throwable $e) {
            $this->logMailFailure($eventName, $to, $e);
        }
    }

    private function logMailFailure(string $eventName, ?string $recipientEmail, \Throwable $e): void
    {
        Log::error('Mail send failed', [
            'event'     => $eventName,
            'recipient' => $recipientEmail !== null ? md5($recipientEmail) : null,
            'exception' => $e::class,
            'message'   => $e->getMessage(),
        ]);
    }
}
