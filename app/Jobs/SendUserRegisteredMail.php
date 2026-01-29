<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\UserRegisteredMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

final class SendUserRegisteredMail implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $locale = 'en',
    ) {
    }

    public function handle(): void
    {
        $key = 'mail_sent:user.registered:' . $this->user->id;
        if (Cache::has($key)) {
            Log::info('Skip duplicate user.registered send (already sent)', [ 'user_id' => $this->user->id ]);
            return;
        }

        $email = $this->user->email;
        if ($email === null || $email === '' || ! filter_var(trim($email), FILTER_VALIDATE_EMAIL)) {
            Log::info('Mail skipped: no valid recipient', [ 'event' => 'user.registered', 'user_id' => $this->user->id ]);
            return;
        }

        try {
            Mail::to($email)->send(new UserRegisteredMail($this->user, $this->locale));
            Cache::add($key, true, 120);
        } catch (\Throwable $e) {
            Log::error('Mail send failed', [
                'event'      => 'user.registered',
                'user_id'    => $this->user->id,
                'exception'  => $e::class,
                'message'    => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
