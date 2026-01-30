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
        return new Envelope(
            subject: 'Account Status Update – Dormitory Management System',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.user-status-changed',
            with: [
                'user'          => $this->user,
                'currentStatus' => $this->currentStatus,
            ],
        );
    }
}
