<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\PaymentGenerationService;
use Illuminate\Console\Command;

class GeneratePaymentsCommand extends Command
{
    /** @var string */
    protected $signature = 'payments:generate
                            {--trigger= : Force a specific trigger (new_semester, new_month, or both). Skips date checks.}';

    /** @var string */
    protected $description = 'Generate pending payments based on payment type configurations';

    public function handle(PaymentGenerationService $service): int
    {
        $this->info('Starting payment generation...');

        $trigger = $this->option('trigger');

        if ($trigger !== null && $trigger !== '') {
            $this->runTrigger($service, $trigger);
        } else {
            $service->generatePendingPayments();
        }

        $this->info('Generation complete.');

        return self::SUCCESS;
    }

    private function runTrigger(PaymentGenerationService $service, string $trigger): void
    {
        $valid = [ 'new_semester', 'new_month', 'both' ];
        $t = strtolower($trigger);

        if (! in_array($t, $valid, true)) {
            $this->error("Invalid --trigger. Use one of: " . implode(', ', $valid));
            return;
        }

        if ($t === 'both') {
            $service->generateForTriggerEvent('new_semester');
            $service->generateForTriggerEvent('new_month');
            $this->info('Ran triggers: new_semester, new_month');
            return;
        }

        $service->generateForTriggerEvent($t);
        $this->info("Ran trigger: {$t}");
    }
}
