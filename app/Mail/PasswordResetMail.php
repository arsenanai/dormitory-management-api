<?php


namespace App\Mail;

use function url;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $token,
        public string $email
    ) {}

    public function build()
    {
        $resetUrl = url('/reset-password?token=' . $this->token . '&email=' . urlencode($this->email));
        return $this->subject('Password Reset Mail')
            ->markdown('emails.password.reset')
            ->with([
                'resetUrl' => $resetUrl,
                'token' => $this->token,
                'email' => $this->email,
            ]);
    }
}
