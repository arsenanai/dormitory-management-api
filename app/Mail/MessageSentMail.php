<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Message;
use App\Models\User;
use App\Services\MailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class MessageSentMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public int $timeout = 30;

    public int $tries = 3;

    public function __construct(
        public readonly User $recipient,
        public readonly Message $message,
    ) {
    }

    public function envelope(): Envelope
    {
        $locale = in_array(app()->getLocale(), [ 'en', 'kk', 'ru' ], true) ? app()->getLocale() : 'en';
        $service = app(MailTemplateService::class);
        $template = $service->getTemplate('message_sent', $locale);
        if ($template !== null) {
            $context = $service->contextForMessageSent($this->recipient, $this->message, null);
            $subject = $service->resolvePlaceholders($template['subject'], $context);
            return new Envelope(subject: $subject);
        }
        return new Envelope(
            subject: 'New Message: ' . \Illuminate\Support\Str::limit($this->message->title ?? 'Notification', 50),
        );
    }

    public function content(): Content
    {
        $locale = in_array(app()->getLocale(), [ 'en', 'kk', 'ru' ], true) ? app()->getLocale() : 'en';
        $service = app(MailTemplateService::class);
        $template = $service->getTemplate('message_sent', $locale);
        if ($template !== null) {
            $context = $service->contextForMessageSent($this->recipient, $this->message, null);
            $body = $service->resolvePlaceholders($template['body'], $context);
            return new Content(htmlString: $body);
        }
        return new Content(
            view: 'emails.message-sent',
            with: [
                'recipient' => $this->recipient,
                'message'   => $this->message,
            ],
        );
    }
}
