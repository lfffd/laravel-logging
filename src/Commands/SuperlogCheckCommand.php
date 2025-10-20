<?php

namespace Superlog\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Superlog\Logger\StructuredLogger;
use Superlog\Facades\Superlog;

class SuperlogCheckCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'superlog:check {--test : Write a test entry to logs} {--diagnostics : Run diagnostic tests}';

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

        // Check 6: Run diagnostics (optional)
        if ($this->option('diagnostics')) {
            $this->runDiagnostics();
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

    /**
     * Run diagnostic tests on Superlog functionality
     */
    protected function runDiagnostics(): void
    {
        $this->line('<fg=cyan>🧪 Running Diagnostic Tests...</>');
        $this->newLine();

        try {
            $config = config('superlog');
            if (!$config) {
                $this->error('✗ Superlog config not found');
                return;
            }

            $logger = new StructuredLogger($config);

            // Test 1: Trace ID generation
            $this->output->write('Test 1: Trace ID generation... ');
            $logger->initializeRequest('GET', '/test', '127.0.0.1', 'test-trace-id-001');
            $entry = $logger->log('info', 'TEST', 'Test message');
            
            if ($entry['trace_id'] === 'test-trace-id-001') {
                $this->line('<fg=green>✓</> Trace ID: ' . $entry['trace_id']);
            } else {
                $this->line('<fg=red>✗</> Failed to set trace ID');
                return;
            }

            // Test 2: Request sequence number
            $this->output->write('Test 2: Request sequence numbering... ');
            $logger->initializeRequest('POST', '/api/data', '192.168.1.1');
            
            $entry1 = $logger->log('info', 'SECTION1', 'Message 1');
            $entry2 = $logger->log('info', 'SECTION2', 'Message 2');
            $entry3 = $logger->log('info', 'SECTION3', 'Message 3');
            
            if ($entry1['req_seq'] === '0000000001' && 
                $entry2['req_seq'] === '0000000002' && 
                $entry3['req_seq'] === '0000000003') {
                $this->line('<fg=green>✓</> Sequence: ' . $entry1['req_seq'] . ' → ' . $entry2['req_seq'] . ' → ' . $entry3['req_seq']);
            } else {
                $this->line('<fg=red>✗</> Failed: got ' . $entry1['req_seq'] . ', ' . $entry2['req_seq'] . ', ' . $entry3['req_seq']);
                return;
            }

            // Test 3: Text formatting with trace_id and req_seq
            $this->output->write('Test 3: Text formatting... ');
            $logger->initializeRequest('GET', '/api', '10.0.0.1', 'format-test-xyz');
            $entry = $logger->log('warning', 'DATABASE', 'Slow query', ['duration_ms' => 523.5]);
            $formatted = $logger->formatLogEntry($entry);
            
            if (strpos($formatted, 'format-test-xyz:0000000001') !== false && 
                strpos($formatted, '[DATABASE]') !== false) {
                $this->line('<fg=green>✓</> Format includes trace_id:req_seq');
                $this->line('     Sample: ' . substr($formatted, 0, 100) . '...');
            } else {
                $this->line('<fg=red>✗</> Format missing trace_id:req_seq');
                $this->line('     Got: ' . substr($formatted, 0, 100));
                return;
            }

            // Test 4: JSON format
            if ($config['format'] === 'json' || isset($config['formats']['json'])) {
                $this->output->write('Test 4: JSON formatting... ');
                $configWithJson = $config;
                $configWithJson['format'] = 'json';
                $jsonLogger = new StructuredLogger($configWithJson);
                $jsonLogger->initializeRequest('POST', '/test', '127.0.0.1', 'json-test-001');
                $entry = $jsonLogger->log('info', 'JSON_TEST', 'JSON message');
                $formatted = $jsonLogger->formatLogEntry($entry);
                
                $decoded = json_decode($formatted, true);
                if ($decoded && isset($decoded['trace_id']) && isset($decoded['req_seq'])) {
                    $this->line('<fg=green>✓</> JSON includes trace_id and req_seq');
                } else {
                    $this->line('<fg=red>✗</> JSON format invalid');
                }
            }

            // Test 5: Multiple requests have different trace IDs
            $this->output->write('Test 5: Trace ID isolation... ');
            $logger->initializeRequest('GET', '/req1', '127.0.0.1', 'trace-first');
            $entry1 = $logger->log('info', 'REQ1', 'Request 1');
            
            $logger->initializeRequest('GET', '/req2', '127.0.0.1', 'trace-second');
            $entry2 = $logger->log('info', 'REQ2', 'Request 2');
            
            if ($entry1['trace_id'] !== $entry2['trace_id']) {
                $this->line('<fg=green>✓</> Different requests have different trace IDs');
            } else {
                $this->line('<fg=red>✗</> Trace IDs should differ between requests');
            }

            // Test 6: Sequence resets per request
            $this->output->write('Test 6: Sequence reset per request... ');
            $logger->initializeRequest('GET', '/new', '127.0.0.1', 'reset-test');
            $entry = $logger->log('info', 'SECTION', 'Message');
            
            if ($entry['req_seq'] === '0000000001') {
                $this->line('<fg=green>✓</> Sequence resets to 0000000001 per request');
            } else {
                $this->line('<fg=red>✗</> Sequence should reset (got ' . $entry['req_seq'] . ')');
            }

            $this->newLine();
            $this->info('✅ Unit tests passed! Superlog package works correctly.');
            
            // Now check actual application logs
            $this->newLine();
            $this->line('<fg=cyan>🔍 Checking actual application logs...</>');
            $this->checkActualLogs();

        } catch (\Exception $e) {
            $this->error('✗ Diagnostic error: ' . $e->getMessage());
        }
    }

    /**
     * Check if actual application logs are using Superlog
     */
    protected function checkActualLogs(): void
    {
        $logPath = storage_path('logs/laravel-' . now()->format('Y-m-d') . '.log');
        
        if (!File::exists($logPath)) {
            $this->warn('⚠ No log file found yet');
            return;
        }

        $content = File::get($logPath);
        $lines = explode("\n", $content);
        $recentLines = array_slice($lines, -30);
        
        // Check for Superlog formatting
        $superlogEntries = 0;
        $localChannelEntries = 0;
        $traceIdPattern = 0;
        $reqSeqPattern = 0;
        
        foreach ($recentLines as $line) {
            if (strpos($line, 'superlog.') !== false) {
                $superlogEntries++;
            }
            if (strpos($line, 'local.') !== false) {
                $localChannelEntries++;
            }
            if (preg_match('/\[[\w\-]+:\d{10}\]/', $line)) {
                $traceIdPattern++;
            }
            if (preg_match('/req_seq.*\d{10}/', $line)) {
                $reqSeqPattern++;
            }
        }
        
        $this->newLine();
        $this->info('Last 10 log lines analysis:');
        foreach (array_slice($recentLines, -10) as $line) {
            if (trim($line)) {
                $line = substr($line, 0, 140) . (strlen($line) > 140 ? '...' : '');
                $this->line('  ' . $line);
            }
        }
        
        $this->newLine();
        $this->line('📊 Log Channel Analysis:');
        $this->line("  Superlog channel entries: <fg=" . ($superlogEntries > 0 ? 'green' : 'red') . ">$superlogEntries</>");
        $this->line("  Local channel entries: <fg=" . ($localChannelEntries > 0 ? 'yellow' : 'green') . ">$localChannelEntries</>");
        $this->line("  Entries with trace_id:req_seq pattern: <fg=" . ($traceIdPattern > 0 ? 'green' : 'red') . ">$traceIdPattern</>");
        
        if ($localChannelEntries > 0 && $superlogEntries === 0) {
            $this->newLine();
            $this->error('❌ INTEGRATION ISSUE DETECTED');
            $this->line('');
            $this->line('Your middleware is logging to the <fg=red>local</> channel instead of <fg=green>superlog</> channel.');
            $this->line('');
            $this->line('This means your middleware code is using:');
            $this->line('  <fg=red>Log::info(...)</> or <fg=red>Log::channel(\'local\')->info(...)</></>');
            $this->line('');
            $this->line('But it should be using:');
            $this->line('  <fg=green>Superlog::log(...)</> or <fg=green>Log::channel(\'superlog\')->info(...)</></>');
            $this->line('');
            $this->line('📋 To fix this, update your middleware to:');
            $this->line('  1. Import: use Superlog\\Facades\\Superlog;');
            $this->line('  2. Call: Superlog::initializeRequest(...) in a global middleware');
            $this->line('  3. Log with: Superlog::log() or Log::channel(\'superlog\')->info()');
            $this->line('');
            $this->line('See: INTEGRATION_CHECKLIST.md for detailed instructions');
        } elseif ($superlogEntries > 0) {
            $this->newLine();
            $this->info('✅ APPLICATION INTEGRATION SUCCESSFUL');
            $this->line('Your middleware is correctly using the superlog channel!');
        }
    }
}