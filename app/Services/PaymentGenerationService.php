<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class PaymentGenerationService
{
    public function __construct(
        protected PaymentCalculationService $paymentCalculationService,
        protected ConfigurationService $configurationService
    ) {
    }

    /**
     * Generate pending payments for new semester and new month triggers.
     * This method should be called by a scheduled command (e.g., daily or monthly).
     */
    public function generatePendingPayments(): void
    {
        // Check if it's a new semester
        if ($this->isNewSemester()) {
            $this->generateForTriggerEvent('new_semester');
        }

        // Check if it's a new month (for monthly payments like catering)
        if ($this->isNewMonth()) {
            $this->generateForTriggerEvent('new_month');
        }
    }

    /**
     * Generate payments for a specific trigger event.
     */
    public function generateForTriggerEvent(string $triggerEvent): void
    {
        // Get payment types that match the trigger event OR have no trigger_event (applies to all)
        $paymentTypes = PaymentType::where(function ($query) use ($triggerEvent) {
            $query->where('trigger_event', $triggerEvent)
                  ->orWhereNull('trigger_event');
        })->get();

        if ($paymentTypes->isEmpty()) {
            Log::info("No payment types found for trigger event: {$triggerEvent}");
            return;
        }

        User::with(['room.roomType', 'guestProfile', 'role'])
            ->whereNotNull('room_id')
            ->chunk(100, function ($users) use ($triggerEvent) {
                foreach ($users as $user) {
                    $user->createPaymentsForTriggerEvent($triggerEvent);
                }
            });

        Log::info("Generated payments for trigger event: {$triggerEvent}");
    }

    /**
     * Check if we're in a new semester period.
     * A new semester is detected if:
     * - It's the first day of a semester (based on configuration)
     * - Or we haven't generated payments for this semester yet
     */
    private function isNewSemester(): bool
    {
        try {
            $paymentSettings = $this->configurationService->getPaymentSettings();
            $semesterConfig = $paymentSettings['semester_config'] ?? [];

            if (empty($semesterConfig)) {
                return false;
            }

            $now = Carbon::now();
            $currentSemester = $this->getCurrentSemester($semesterConfig);

            if (!$currentSemester) {
                return false;
            }

            $semesterStart = $this->getSemesterStartDate($currentSemester, $semesterConfig);

            // Check if today is within the first few days of the semester
            // (to allow for some flexibility in when the command runs)
            $daysSinceStart = $now->diffInDays($semesterStart);

            // Consider it a new semester if we're within the first 7 days
            return $daysSinceStart <= 7 && $daysSinceStart >= 0;
        } catch (\Exception $e) {
            Log::error("Error checking for new semester: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if we're in a new month.
     * A new month is detected if it's the first day of the month.
     */
    private function isNewMonth(): bool
    {
        return Carbon::now()->day === 1;
    }

    /**
     * Get the current semester based on configuration.
     */
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

    /**
     * Get the start date of a semester.
     */
    private function getSemesterStartDate(string $semester, array $semesterConfig): Carbon
    {
        $now = Carbon::now();
        $config = $semesterConfig[$semester] ?? [];

        return Carbon::create(
            $now->year,
            (int) ($config['start_month'] ?? 9),
            (int) ($config['start_day'] ?? 1)
        );
    }
}
