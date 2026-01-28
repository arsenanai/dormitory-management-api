<?php

namespace App\Console\Commands;

use App\Models\StudentProfile;
use App\Services\ConfigurationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckStudentPaymentStatusCommand extends Command
{
    protected $signature = 'students:check-payment-status';
    protected $description = 'Check student payment status and update to pending if overdue';

    public function __construct(private ConfigurationService $configurationService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Checking student payment status...');

        try {
            $updatedCount = 0;
            $students = StudentProfile::with(['user', 'user.payments'])->get();

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
        $pendingPayments = $student->user->payments()
            ->where('status', \App\Enums\PaymentStatus::Pending)
            ->get();

        if ($pendingPayments->isEmpty()) {
            return false;
        }

        $hasOverdue = false;
        $deadlineDays = (int) ($this->configurationService->getConfiguration('payment_deadline_days') ?? 10);

        foreach ($pendingPayments as $payment) {
            $deadline = $payment->date_from->copy()->addDays($deadlineDays);
            if (\Carbon\Carbon::now()->greaterThan($deadline)) {
                $hasOverdue = true;
                break;
            }
        }

        // Only update if student user is currently active and has overdue payments
        // Note: status is on User model, not StudentProfile
        if ($student->user && $student->user->status === 'active' && $hasOverdue) {
            $student->user->update(['status' => 'pending']);

            Log::info("Student {$student->user->id} (profile {$student->id}) status changed to pending due to overdue payments");
            return true;
        }

        return false;
    }
}
