<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Events\MailEventOccurred;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

final class TriggerMailEventsCommand extends Command
{
    protected $signature = 'mail:trigger-events
                            {--verify : Print verification grep command and recent log lines}';

    protected $description = 'Trigger user.registered, user.status_changed, payment.status_changed and log mails (MAIL_MAILER=log, queue=sync)';

    public function handle(): int
    {
        Config::set('queue.default', 'sync');
        Cache::flush();

        $user = User::whereNotNull('email')->where('email', '!=', '')->first();
        if (! $user) {
            $this->error('No user with email found. Run migrations + seed.');

            return self::FAILURE;
        }

        $this->info("Using user: {$user->email} (id={$user->id})");

        event(new MailEventOccurred('user.registered', ['user' => $user, 'locale' => 'en']));
        $this->line('Dispatched user.registered');

        event(new MailEventOccurred('user.status_changed', [
            'user'       => $user,
            'old_status' => 'pending',
            'new_status' => 'active',
        ]));
        $this->line('Dispatched user.status_changed');

        $payment = Payment::with('user')->whereHas('user')->first();
        if ($payment && $payment->user) {
            event(new MailEventOccurred('payment.status_changed', [
                'payment'    => $payment,
                'old_status' => PaymentStatus::Pending,
                'new_status' => PaymentStatus::Completed,
            ]));
            $this->line("Dispatched payment.status_changed (payment id={$payment->id})");
        } else {
            $this->warn('No payment with user found; skipped payment.status_changed');
        }

        $this->newLine();
        $this->info('Done. Mails are written to storage/logs/laravel.log (MAIL_MAILER=log).');

        if ($this->option('verify')) {
            $this->newLine();
            $this->line('Verify with: tail -400 storage/logs/laravel.log | grep -E "To:|Subject:|Current status:|Deal number:"');
            $logPath = storage_path('logs/laravel.log');
            if (is_readable($logPath)) {
                $content = @file($logPath);
                $chunk = is_array($content) ? array_slice($content, -80) : [];
                $tail = implode('', $chunk);
                $all = explode("\n", $tail);
                $lines = array_values(array_filter($all, fn (string $s): bool =>
                    str_contains($s, 'To:') || str_contains($s, 'Subject:')
                    || str_contains($s, 'Current status:') || str_contains($s, 'Deal number:')));
                if ($lines !== []) {
                    $this->newLine();
                    $this->line('Recent mail-related log lines:');
                    $this->line(implode("\n", $lines));
                }
            }
        }

        return self::SUCCESS;
    }
}
