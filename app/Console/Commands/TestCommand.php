<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * Override Collision's test command so that --coverage outputs to terminal.
 * Collision runs PHPUnit with --no-output and --coverage-php, then renders via Termwind,
 * which may not display in some environments. This command runs PHPUnit directly with
 * --coverage-text when --coverage is passed so coverage is printed to the terminal.
 */
class TestCommand extends Command
{
    protected $signature = 'test
        {--without-tty : Disable output to TTY}
        {--compact : Indicates whether the compact printer should be used}
        {--coverage : Indicates whether code coverage information should be collected}
        {--min= : Indicates the minimum threshold enforcement for code coverage}
        {--p|parallel : Indicates if the tests should run in parallel}
        {--profile : Lists top 10 slowest tests}
        {--recreate-databases : Indicates if the test databases should be re-created}
        {--drop-databases : Indicates if the test databases should be dropped}
        {--without-databases : Indicates if database configuration should be performed}
    ';

    protected $description = 'Run the application tests';

    protected $hidden = false;

    public function __construct()
    {
        parent::__construct();
        $this->ignoreValidationErrors();
    }

    private const ARTISAN_OPTIONS = [
        '--coverage', '--compact', '--min', '--parallel', '-p', '--profile',
        '--without-tty', '--recreate-databases', '--drop-databases', '--without-databases',
    ];

    public function handle(): int
    {
        $argv = $_SERVER['argv'];
        $args = array_slice($argv, 2);

        $filtered = [];
        $skipNext = false;
        $wantsCoverage = false;
        foreach ($args as $arg) {
            if ($skipNext) {
                $skipNext = false;
                continue;
            }
            if ($arg === '--coverage') {
                $wantsCoverage = true;
                continue;
            }
            if ($arg === '--min' || str_starts_with($arg, '--min=')) {
                if ($arg === '--min') {
                    $skipNext = true;
                }
                continue;
            }
            if (in_array($arg, self::ARTISAN_OPTIONS, true)) {
                continue;
            }
            $filtered[] = $arg;
        }

        $config = base_path('phpunit.xml');
        if (! is_file($config)) {
            $config = base_path('phpunit.xml.dist');
        }

        $command = array_merge(
            [ PHP_BINARY, 'vendor/bin/phpunit', '--configuration=' . $config ],
            $filtered
        );
        if ($wantsCoverage) {
            $command[] = '--coverage-text';
        }

        $process = new Process($command, base_path(), null, null, null);
        $process->run(function (string $type, string $output): void {
            $this->output->write($output);
        });

        return $process->getExitCode();
    }
}
