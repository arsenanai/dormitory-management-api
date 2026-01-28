<?php

namespace App\Mail;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class PaymentCreatedMail extends Mailable
{
    use Queueable;

    public function __construct(
        private User $user,
        private Payment $payment
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Payment Created - Dormitory Management System',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-created',
            with: [
                'user' => $this->user,
                'payment' => $this->payment,
            ],
        );
    }
}
