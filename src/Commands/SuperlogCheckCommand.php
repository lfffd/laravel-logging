<?php

namespace Superlog\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class SuperlogCheckCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'superlog:check {--test : Write a test entry to logs}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Check if Superlog is properly configured and optionally write a test entry';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔍 Checking Superlog Configuration...');
        $this->newLine();

        $allGood = true;

        // Check 1: Config published
        if (!$this->checkConfigPublished()) {
            $allGood = false;
        }
        $this->newLine();

        // Check 2: Channel configured
        if (!$this->checkChannelConfigured()) {
            $allGood = false;
        }
        $this->newLine();

        // Check 3: Superlog enabled
        if (!$this->checkSuperlogEnabled()) {
            $allGood = false;
        }
        $this->newLine();

        // Check 4: Log directory writable
        if (!$this->checkLogDirectory()) {
            $allGood = false;
        }
        $this->newLine();

        // Check 5: Write test entry (optional)
        if ($this->option('test')) {
            $this->writeTestEntry();
            $this->newLine();
        }

        $this->newLine();
        if ($allGood) {
            $this->info('✅ Superlog is properly configured!');
            return 0;
        } else {
            $this->error('❌ Some checks failed. Please see above for details.');
            return 1;
        }
    }

    /**
     * Check if config file is published.
     */
    protected function checkConfigPublished(): bool
    {
        $this->output->write('Checking config file... ');

        if (File::exists(config_path('superlog.php'))) {
            $this->line('<fg=green>✓</> Found at: ' . config_path('superlog.php'));
            return true;
        } else {
            $this->line('<fg=red>✗</> Not found. Run:');
            $this->line('  <fg=yellow>php artisan vendor:publish --provider="Superlog\SuperlogServiceProvider" --tag=config</>');
            return false;
        }
    }

    /**
     * Check if superlog channel is configured in logging.php.
     */
    protected function checkChannelConfigured(): bool
    {
        $this->output->write('Checking logging channel... ');

        $loggingConfig = config('logging');
        if (isset($loggingConfig['channels']['superlog'])) {
            $driver = $loggingConfig['channels']['superlog']['driver'] ?? null;
            if ($driver === 'superlog') {
                $this->line('<fg=green>✓</> Channel "superlog" is configured');
                return true;
            } else {
                $this->line('<fg=red>✗</> Channel exists but driver is not "superlog" (driver: ' . $driver . ')');
                return false;
            }
        } else {
            $this->line('<fg=red>✗</> Not found. Add to config/logging.php:');
            $this->line('  <fg=yellow>\'superlog\' => [');
            $this->line('      \'driver\' => \'superlog\',');
            $this->line('      \'name\' => \'superlog\',');
            $this->line('      \'level\' => \'debug\',');
            $this->line('  ],</>');
            return false;
        }
    }

    /**
     * Check if Superlog is enabled in config.
     */
    protected function checkSuperlogEnabled(): bool
    {
        $this->output->write('Checking if Superlog is enabled... ');

        $enabled = config('superlog.enabled', true);
        if ($enabled) {
            $this->line('<fg=green>✓</> Superlog is enabled');
            return true;
        } else {
            $this->line('<fg=red>✗</> Superlog is disabled in config/superlog.php');
            $this->line('  Set <fg=yellow>SUPERLOG_ENABLED=true</> in .env or modify config');
            return false;
        }
    }

    /**
     * Check if log directory is writable.
     */
    protected function checkLogDirectory(): bool
    {
        $this->output->write('Checking log directory... ');

        $logPath = storage_path('logs');
        if (!File::isDirectory($logPath)) {
            $this->line('<fg=red>✗</> Directory does not exist: ' . $logPath);
            return false;
        }

        if (!File::isWritable($logPath)) {
            $this->line('<fg=red>✗</> Directory is not writable: ' . $logPath);
            $this->line('  Try: <fg=yellow>chmod 775 ' . $logPath . '</>');
            return false;
        }

        $this->line('<fg=green>✓</> Directory is writable: ' . $logPath);
        return true;
    }

    /**
     * Write a test entry to the logs.
     */
    protected function writeTestEntry(): void
    {
        $this->line('📝 Writing test entries...');

        try {
            // Try to use the superlog channel if available
            $channel = 'superlog';
            if (isset(config('logging.channels')[$channel])) {
                Log::channel($channel)->info('🧪 Superlog Test Entry', [
                    'test_id' => uniqid('test_'),
                    'timestamp' => now()->toIso8601String(),
                    'message' => 'This is a test entry from superlog:check command',
                ]);
                $this->info('✓ Test entry written to superlog channel');
            } else {
                $this->warn('⚠ Superlog channel not available, writing to default channel');
                Log::info('🧪 Superlog Test Entry (default channel)', [
                    'test_id' => uniqid('test_'),
                    'timestamp' => now()->toIso8601String(),
                ]);
            }

            // Also write to the default log for verification
            $logFile = storage_path('logs/laravel-' . now()->format('Y-m-d') . '.log');
            if (File::exists($logFile)) {
                $this->info('✓ Log file found: ' . $logFile);
                $this->info('✓ Last 5 lines of log file:');
                $lines = array_slice(explode("\n", File::get($logFile)), -6, 5);
                foreach ($lines as $line) {
                    if (trim($line)) {
                        $this->line('  ' . substr($line, 0, 120) . (strlen($line) > 120 ? '...' : ''));
                    }
                }
            } else {
                $this->warn('⚠ Log file not created yet');
            }
        } catch (\Exception $e) {
            $this->error('✗ Error writing test entry: ' . $e->getMessage());
        }
    }
}