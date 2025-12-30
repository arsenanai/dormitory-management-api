<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $token,
        public string $email,
    ) {
    }

    public function build()
    {
        $resetUrl = config('app.spa_url') . '/reset-password-form?token=' . $this->token . '&email=' . urlencode($this->email);
        return $this->subject('Password Reset Mail')
            ->markdown('emails.password.reset')
            ->with([
                'resetUrl' => $resetUrl,
                'token' => $this->token,
                'email' => $this->email,
            ]);
    }
}
