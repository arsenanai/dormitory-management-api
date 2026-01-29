<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Message;
use App\Models\User;
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

    public function __construct(
        public readonly User $recipient,
        public readonly Message $message,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New Message: ' . \Illuminate\Support\Str::limit($this->message->title ?? 'Notification', 50),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.message-sent',
            with: [
                'recipient' => $this->recipient,
                'message' => $this->message,
            ],
        );
    }
}
