<?php

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Models\StudentProfile;
use App\Services\ConfigurationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckStudentPaymentStatusCommand extends Command
{
    protected $signature = 'students:check-payment-status';
    protected $description = 'Check student payment status and update to pending if overdue or missing current semester payment';

    public function __construct(private ConfigurationService $configurationService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Checking student payment status...');

        try {
            $updatedCount = 0;
            // Only check students with assigned rooms (payments are only generated for students with rooms)
            $students = StudentProfile::with(['user', 'user.payments.type'])
                ->whereHas('user', function ($query) {
                    $query->where('status', 'active')
                          ->whereNotNull('room_id');
                })
                ->get();

            foreach ($students as $student) {
                if ($this->updateStudentStatus($student)) {
                    $updatedCount++;
                }
            }

            $this->info("Updated {$updatedCount} students to pending status");
            Log::info("Student payment status check completed: {$updatedCount} students updated to pending");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Failed to check student payment status: {$e->getMessage()}");
            Log::error("Student payment status check failed: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function updateStudentStatus(StudentProfile $student): bool
    {
        if (!$student->user || $student->user->status !== 'active') {
            return false;
        }

        if ($this->hasOverduePayments($student)) {
            $user = $student->user;
            $oldStatus = $user->status;
            $user->update(['status' => 'pending']);
            \Illuminate\Support\Facades\Event::dispatch(new \App\Events\MailEventOccurred('user.status_changed', [
                'user' => $user->fresh(['role']), 'old_status' => $oldStatus, 'new_status' => 'pending',
            ]));
            Log::info("Student {$user->id} (profile {$student->id}) status changed to pending due to overdue payments");
            return true;
        }

        if (! $this->hasPaidCurrentSemester($student)) {
            $user = $student->user;
            $oldStatus = $user->status;
            $user->update(['status' => 'pending']);
            \Illuminate\Support\Facades\Event::dispatch(new \App\Events\MailEventOccurred('user.status_changed', [
                'user' => $user->fresh(['role']), 'old_status' => $oldStatus, 'new_status' => 'pending',
            ]));
            Log::info("Student {$user->id} (profile {$student->id}) status changed to pending due to missing current semester payment");
            return true;
        }

        return false;
    }

    private function hasOverduePayments(StudentProfile $student): bool
    {
        $pendingPayments = $student->user->payments()
            ->where('status', PaymentStatus::Pending)
            ->get();

        if ($pendingPayments->isEmpty()) {
            return false;
        }

        $deadlineDays = (int) ($this->configurationService->getConfiguration('payment_deadline_days') ?? 10);

        foreach ($pendingPayments as $payment) {
            if (!$payment->date_from) {
                continue;
            }

            $deadline = $payment->date_from->copy()->addDays($deadlineDays);
            if (Carbon::now()->greaterThan($deadline)) {
                return true;
            }
        }

        return false;
    }

    private function hasPaidCurrentSemester(StudentProfile $student): bool
    {
        $paymentSettings = $this->configurationService->getPaymentSettings();
        $semesterConfig = $paymentSettings['semester_config'] ?? [];

        if (empty($semesterConfig)) {
            // If semester config is not set, skip this check
            return true;
        }

        $currentSemester = $this->getCurrentSemester($semesterConfig);
        if (!$currentSemester) {
            // If we're not in a semester period, skip this check
            return true;
        }

        $semesterStart = $this->getSemesterStartDate($currentSemester, $semesterConfig);
        $today = Carbon::now()->startOfDay();

        // Check if we're past the semester start date (allow some grace period)
        // Only check if we're at least a few days into the semester
        if ($today->lessThan($semesterStart->copy()->addDays(3))) {
            // Too early in the semester, don't check yet
            return true;
        }

        // Check if student has a completed semester payment that covers the current date
        // A payment covers the current semester if:
        // 1. It's a semesterly payment or triggered by new_semester
        // 2. It's completed
        // 3. Its date range includes today's date
        $hasPaidSemester = $student->user->payments()
            ->where('status', PaymentStatus::Completed)
            ->whereHas('type', function ($query) {
                $query->where('target_role', 'student')
                      ->where(function ($q) {
                          $q->where('frequency', 'semesterly')
                            ->orWhere('trigger_event', 'new_semester');
                      });
            })
            ->where(function ($query) use ($today) {
                // Payment covers today's date
                $query->where('date_from', '<=', $today)
                      ->where(function ($q) use ($today) {
                          $q->whereNull('date_to')
                            ->orWhere('date_to', '>=', $today);
                      });
            })
            ->exists();

        return $hasPaidSemester;
    }

    private function getCurrentSemester(array $semesterConfig): ?string
    {
        $now = Carbon::now();

        try {
            $fallStart = Carbon::create(
                $now->year,
                (int) ($semesterConfig['fall']['start_month'] ?? 9),
                (int) ($semesterConfig['fall']['start_day'] ?? 1)
            );
            $fallEnd = Carbon::create(
                $now->year,
                (int) ($semesterConfig['fall']['end_month'] ?? 12),
                (int) ($semesterConfig['fall']['end_day'] ?? 31)
            );

            $springStart = Carbon::create(
                $now->year,
                (int) ($semesterConfig['spring']['start_month'] ?? 1),
                (int) ($semesterConfig['spring']['start_day'] ?? 1)
            );
            $springEnd = Carbon::create(
                $now->year,
                (int) ($semesterConfig['spring']['end_month'] ?? 5),
                (int) ($semesterConfig['spring']['end_day'] ?? 31)
            );

            if ($now->between($fallStart, $fallEnd)) {
                return 'fall';
            } elseif ($now->between($springStart, $springEnd)) {
                return 'spring';
            }
        } catch (\Exception $e) {
            Log::error("Error determining semester: " . $e->getMessage());
        }

        return null;
    }

    private function getSemesterStartDate(string $semester, array $semesterConfig): Carbon
    {
        $now = Carbon::now();
        $config = $semesterConfig[$semester] ?? [];

        return Carbon::create(
            $now->year,
            (int) ($config['start_month'] ?? 9),
            (int) ($config['start_day'] ?? 1)
        )->startOfDay();
    }

    private function getSemesterEndDate(string $semester, array $semesterConfig): Carbon
    {
        $now = Carbon::now();
        $config = $semesterConfig[$semester] ?? [];

        return Carbon::create(
            $now->year,
            (int) ($config['end_month'] ?? 12),
            (int) ($config['end_day'] ?? 31)
        )->endOfDay();
    }
}
