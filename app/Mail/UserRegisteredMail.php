<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class UserRegisteredMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public int $timeout = 30;

    public int $tries = 3;

    public function __construct(
        public readonly User $user,
        public readonly string $preferredLocale = 'en',
    ) {
    }

    public function envelope(): Envelope
    {
        $subject = (string) __("emails.user_registered.subject", [], $this->preferredLocale);
        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        $name = $this->user->first_name
            ? trim($this->user->first_name . ' ' . ($this->user->last_name ?? ''))
            : $this->user->name;
        $displayName = (string) ($name ?: $this->user->email ?? '');
        $year = (string) date('Y');

        return new Content(
            view: 'emails.user-registered',
            with: [
                'user'            => $this->user,
                'locale'          => $this->preferredLocale,
                'title'           => (string) __("emails.user_registered.title", [], $this->preferredLocale),
                'systemName'      => (string) __("emails.user_registered.system_name", [], $this->preferredLocale),
                'greeting'        => (string) __("emails.user_registered.greeting", [ 'name' => $displayName ], $this->preferredLocale),
                'body'            => (string) __("emails.user_registered.body", [], $this->preferredLocale),
                'contactHint'     => (string) __("emails.user_registered.contact_hint", [], $this->preferredLocale),
                'footerAutomated' => (string) __("emails.user_registered.footer_automated", [], $this->preferredLocale),
                'footerCopyright' => (string) __("emails.user_registered.footer_copyright", [ 'year' => $year ], $this->preferredLocale),
            ],
        );
    }
}
