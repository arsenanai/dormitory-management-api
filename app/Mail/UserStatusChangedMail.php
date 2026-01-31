<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use App\Services\MailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class UserStatusChangedMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public int $timeout = 30;

    public int $tries = 3;

    public function __construct(
        public readonly User $user,
        public readonly string $currentStatus,
    ) {
    }

    public function envelope(): Envelope
    {
        $locale = in_array(app()->getLocale(), [ 'en', 'kk', 'ru' ], true) ? app()->getLocale() : 'en';
        $service = app(MailTemplateService::class);
        $template = $service->getTemplate('user_status_changed', $locale);
        if ($template !== null) {
            $context = $service->contextForUserStatusChanged($this->user, $this->currentStatus);
            $subject = $service->resolvePlaceholders($template['subject'], $context);
            return new Envelope(subject: $subject);
        }
        return new Envelope(subject: 'Account Status Update – ' . config('app.name'));
    }

    public function content(): Content
    {
        $locale = in_array(app()->getLocale(), [ 'en', 'kk', 'ru' ], true) ? app()->getLocale() : 'en';
        $service = app(MailTemplateService::class);
        $template = $service->getTemplate('user_status_changed', $locale);
        if ($template !== null) {
            $context = $service->contextForUserStatusChanged($this->user, $this->currentStatus);
            $body = $service->resolvePlaceholders($template['body'], $context);
            return new Content(htmlString: $body);
        }
        return new Content(
            view: 'emails.user-status-changed',
            with: [
                'user'          => $this->user,
                'currentStatus' => $this->currentStatus,
            ],
        );
    }
}
