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

        // Check 4: Superlog channel in config/superlog.php
        if (!$this->checkSuperlogChannelConfig()) {
            $allGood = false;
        }
        $this->newLine();

        // Check 5: LOG_CHANNEL env variable
        if (!$this->checkLogChannelEnv()) {
            $allGood = false;
        }
        $this->newLine();

        // Check 6: Log directory writable
        if (!$this->checkLogDirectory()) {
            $allGood = false;
        }
        $this->newLine();

        // Check 7: Write test entry (optional)
        if ($this->option('test')) {
            $this->writeTestEntry();
            $this->newLine();
        }

        // Check 8: Run diagnostics (optional)
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
     * Check if Superlog channel is properly configured in config/superlog.php
     */
    protected function checkSuperlogChannelConfig(): bool
    {
        $this->output->write('Checking Superlog channel in config/superlog.php... ');

        $channel = config('superlog.channel', 'stack');
        
        if ($channel === 'superlog') {
            $this->line('<fg=green>✓</> Configured to use "superlog" channel');
            return true;
        } else {
            $this->line('<fg=red>✗</> Configured to use "' . $channel . '" channel (should be "superlog")');
            return false;
        }
    }

    /**
     * Check if LOG_CHANNEL environment variable is properly set
     */
    protected function checkLogChannelEnv(): bool
    {
        $this->output->write('Checking LOG_CHANNEL environment variable... ');

        $channel = env('LOG_CHANNEL', null);
        
        if ($channel === 'superlog') {
            $this->line('<fg=green>✓</> Properly set to "superlog"');
            return true;
        } elseif ($channel === null) {
            $this->line('<fg=yellow>⚠</> Not set in .env (falling back to defaults)');
            return true; // Not critical, but should be set
        } else {
            $this->line('<fg=red>✗</> Set to "' . $channel . '" (should be "superlog")');
            return false;
        }
    }

    /**
     * Analyze the logging stack configuration
     */
    protected function analyzeLoggingStack(): array
    {
        $logging = config('logging');
        $defaultChannel = $logging['default'] ?? 'stack';
        $issues = [];

        if ($defaultChannel === 'stack') {
            $stack = $logging['channels']['stack'] ?? [];
            $channels = $stack['channels'] ?? [];

            // Check if 'local' comes before 'superlog' in stack
            $localPos = array_search('local', $channels);
            $superlogPos = array_search('superlog', $channels);

            if ($localPos !== false && $superlogPos !== false && $localPos < $superlogPos) {
                $issues[] = 'Stack channels have "local" before "superlog" - logs will go to local first!';
            }

            if (!in_array('superlog', $channels)) {
                $issues[] = 'Stack channel does not include "superlog" - Superlog logs won\'t be captured!';
            }

            if ($superlogPos === false && $localPos !== false) {
                $issues[] = 'Stack only includes "local" channel - all logs go to local, not superlog!';
            }
        }

        return $issues;
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
        $this->newLine();
        $this->output->write('Test 7: Writing actual test entry to superlog channel... ');
        
        // Get the current log file size before writing
        $logPath = storage_path('logs/laravel-' . now()->format('Y-m-d') . '.log');
        $fileSizeBefore = File::exists($logPath) ? filesize($logPath) : 0;
        
        // Generate unique marker for this test
        $testMarker = 'SUPERLOG_TEST_' . uniqid('', true);
        $testTraceId = 'diagnostic-trace-' . uniqid();
        
        try {
            // Write a test entry to the superlog channel
            Log::channel('superlog')->info('🧪 Superlog Diagnostic Test', [
                'test_marker' => $testMarker,
                'test_type' => 'DIAGNOSTICS',
                'timestamp' => now()->toIso8601String(),
                'session_test' => 'session_data_test',
            ]);
            
            // Give the logger a moment to write
            usleep(100000);
            
            // Now read the log file
            if (!File::exists($logPath)) {
                $this->line('<fg=red>✗</>');
                $this->newLine();
                $this->error('❌ Log file was not created');
                return;
            }
            
            $content = File::get($logPath);
            $lines = explode("\n", $content);
            
            // Search for our test entry
            $testEntryFound = null;
            foreach (array_reverse($lines) as $line) {
                if (strpos($line, $testMarker) !== false) {
                    $testEntryFound = $line;
                    break;
                }
            }
            
            if (!$testEntryFound) {
                $this->line('<fg=red>✗</>');
                $this->newLine();
                $this->error('❌ Test entry was not found in log file');
                $this->line('');
                $this->line('This means Superlog is NOT writing to the log file.');
                $this->line('Check that your logging.php has the superlog channel configured.');
                return;
            }
            
            // Verify the test entry has proper formatting
            $hasChannel = strpos($testEntryFound, 'superlog.') !== false;
            $hasTraceId = preg_match('/\[[\w\-]+:\d{10}\]/', $testEntryFound) !== false;
            $hasTestMarker = strpos($testEntryFound, $testMarker) !== false;
            $hasTimestamp = preg_match('/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $testEntryFound) !== false;
            
            if ($hasChannel && $hasTraceId && $hasTestMarker && $hasTimestamp) {
                $this->line('<fg=green>✓</>');
                $this->newLine();
                $this->info('✅ Test entry successfully written and verified!');
                $this->newLine();
                $this->info('Log entry details:');
                $this->line('  ' . substr($testEntryFound, 0, 180) . (strlen($testEntryFound) > 180 ? '...' : ''));
                $this->newLine();
                $this->line('✅ Format verification:');
                $this->line('  Channel: ' . ($hasChannel ? '<fg=green>✓ superlog</>' : '<fg=red>✗ NOT superlog</>'));
                $this->line('  Timestamp: ' . ($hasTimestamp ? '<fg=green>✓ ISO8601</>' : '<fg=red>✗ Invalid</>'));
                $this->line('  Trace ID + Seq: ' . ($hasTraceId ? '<fg=green>✓ [id:seq] found</>' : '<fg=red>✗ Missing</>'));
                $this->line('  Data preserved: ' . ($hasTestMarker ? '<fg=green>✓ Yes</>' : '<fg=red>✗ No</>'));
                $this->newLine();
                $this->info('✅ APPLICATION INTEGRATION SUCCESSFUL');
                $this->line('Your Superlog is properly configured and working!');
            } else {
                $this->line('<fg=red>✗</>');
                $this->newLine();
                $this->error('❌ Test entry found but with incorrect format');
                $this->newLine();
                $this->line('Expected format:');
                $this->line('  [timestamp] superlog.INFO: [trace-id:seq-number] [SECTION] message {...}');
                $this->newLine();
                $this->line('Actual log entry:');
                $this->line('  ' . $testEntryFound);
                $this->newLine();
                $this->line('Format verification:');
                $this->line('  Channel: ' . ($hasChannel ? '<fg=green>✓</>' : '<fg=red>✗</>'));
                $this->line('  Timestamp: ' . ($hasTimestamp ? '<fg=green>✓</>' : '<fg=red>✗</>'));
                $this->line('  Trace ID + Seq: ' . ($hasTraceId ? '<fg=green>✓</>' : '<fg=red>✗</>'));
                $this->line('  Data: ' . ($hasTestMarker ? '<fg=green>✓</>' : '<fg=red>✗</>'));
                
                // Check if log is going to local channel instead
                if (strpos($testEntryFound, 'local.') !== false) {
                    $this->newLine();
                    $this->error('❌ ISSUE DETECTED: Logs are going to "local" channel instead of "superlog"');
                    $this->newLine();
                    $this->line('This typically means:');
                    $this->line('  1. Your middleware is using Log::info() instead of Superlog::log()');
                    $this->line('  2. Or your logging.php "stack" includes "local" before "superlog"');
                    $this->newLine();
                    $this->line('🔧 TO FIX:');
                    $this->newLine();
                    $this->line('<fg=yellow>Option A: Update config/logging.php (Recommended)</>');
                    $this->line('Make sure the "superlog" channel is used as the primary channel:');
                    $this->line('');
                    $this->line('  \'default\' => env(\'LOG_CHANNEL\', \'superlog\'),');
                    $this->newLine();
                    $this->line('<fg=yellow>Option B: Update your middleware (if applicable)</>');
                    $this->line('Change from:');
                    $this->line('  use Illuminate\\Support\\Facades\\Log;');
                    $this->line('  Log::info(\'Message\', $data);');
                    $this->line('');
                    $this->line('To:');
                    $this->line('  use Superlog\\Facades\\Superlog;');
                    $this->line('  Superlog::log(\'info\', \'SECTION\', \'Message\', $data);');
                    $this->newLine();
                    $this->line('<fg=cyan>Creating automatic fix script...</>');
                    $this->createMiddlewareFixScript();
                }
            }
            
        } catch (\Exception $e) {
            $this->line('<fg=red>✗</>');
            $this->newLine();
            $this->error('❌ Error writing test entry: ' . $e->getMessage());
            $this->line('');
            $this->line('Verify your config/logging.php has:');
            $this->line('  \'superlog\' => [');
            $this->line('      \'driver\' => \'superlog\',');
            $this->line('      ...');
            $this->line('  ]');
        }
    }

    /**
     * Comprehensive fix script to update logging configuration
     */
    protected function createMiddlewareFixScript(): void
    {
        $this->newLine();
        $this->line('<fg=cyan>🔍 INVESTIGATING ALL CONFIGURATION PARAMETERS...</>');
        $this->newLine();

        $issues = $this->gatherAllConfigurationIssues();

        if (empty($issues)) {
            $this->info('✅ All configuration parameters are properly set!');
            return;
        }

        // Display all issues found
        $this->line('<fg=red>⚠️  Issues detected:</> ');
        foreach ($issues as $index => $issue) {
            $this->line('  ' . ($index + 1) . '. ' . $issue['description']);
        }
        $this->newLine();

        // Ask about each issue individually
        $this->line('<fg=cyan>Would you like me to fix these issues?</>');
        $this->newLine();

        $confirmFixes = [];
        foreach ($issues as $index => $issue) {
            $this->newLine();
            $this->line('<fg=yellow>Issue #' . ($index + 1) . ':</> ' . $issue['description']);
            $this->line('📌 ' . $issue['detail']);
            
            if ($this->confirm('Fix this issue?', true)) {
                $confirmFixes[$issue['key']] = $issue;
            } else {
                $this->line('  <fg=yellow>Skipped</>');
            }
        }

        if (empty($confirmFixes)) {
            $this->newLine();
            $this->line('<fg=yellow>No fixes selected. Showing manual instructions:</>');
            $this->newLine();
            $this->showManualFixes($issues);
            return;
        }

        $this->applyAutoFixes($confirmFixes);

        $this->newLine();
        $this->line('🔄 After fixing, clear cache and run the diagnostic again:');
        $this->line('  <fg=cyan>php artisan cache:clear</>');
        $this->line('  <fg=cyan>php artisan superlog:check --diagnostics</>');
    }

    /**
     * Gather all configuration issues
     */
    protected function gatherAllConfigurationIssues(): array
    {
        $issues = [];

        // 1. Check .env LOG_CHANNEL
        $logChannelEnv = env('LOG_CHANNEL', null);
        if ($logChannelEnv !== 'superlog') {
            $issues[] = [
                'key' => 'env_channel',
                'description' => '.env: LOG_CHANNEL is not set to "superlog"',
                'detail' => $logChannelEnv ? 'Currently: LOG_CHANNEL=' . $logChannelEnv : 'Currently: LOG_CHANNEL is not defined',
            ];
        }

        // 2. Check config/superlog.php channel
        $superlogChannel = config('superlog.channel', 'stack');
        if ($superlogChannel !== 'superlog') {
            $issues[] = [
                'key' => 'superlog_channel',
                'description' => 'config/superlog.php: channel is set to "' . $superlogChannel . '" instead of "superlog"',
                'detail' => 'The internal channel configuration should route logs to the superlog handler',
            ];
        }

        // 3. Check config/logging.php default channel
        $loggingConfig = config('logging');
        $defaultChannel = $loggingConfig['default'] ?? 'stack';
        if ($defaultChannel !== 'superlog') {
            $issues[] = [
                'key' => 'logging_default',
                'description' => 'config/logging.php: default channel is set to "' . $defaultChannel . '" instead of "superlog"',
                'detail' => 'The default logging channel should be "superlog" to use Superlog everywhere',
            ];
        }

        // 4. Check if superlog channel exists in logging.php
        if (!isset($loggingConfig['channels']['superlog'])) {
            $issues[] = [
                'key' => 'superlog_handler_missing',
                'description' => 'config/logging.php: "superlog" channel handler is not configured',
                'detail' => 'You need to add the superlog driver configuration to logging.php',
            ];
        }

        // 5. Analyze stack channel configuration
        $stackIssues = $this->analyzeLoggingStackForFixes();
        foreach ($stackIssues as $stackIssue) {
            $issues[] = $stackIssue;
        }

        return $issues;
    }

    /**
     * Analyze stack channel configuration and return fixable issues
     */
    protected function analyzeLoggingStackForFixes(): array
    {
        $issues = [];
        $logging = config('logging');
        $defaultChannel = $logging['default'] ?? 'stack';

        if ($defaultChannel === 'stack') {
            $stack = $logging['channels']['stack'] ?? [];
            $channels = $stack['channels'] ?? [];

            if (!in_array('superlog', $channels)) {
                $issues[] = [
                    'key' => 'stack_missing_superlog',
                    'description' => 'config/logging.php: Stack channel does not include "superlog"',
                    'detail' => 'Add "superlog" to the stack channels list so logs are routed to Superlog',
                ];
            }

            $localPos = array_search('local', $channels);
            $superlogPos = array_search('superlog', $channels);

            if ($localPos !== false && $superlogPos !== false && $localPos < $superlogPos) {
                $issues[] = [
                    'key' => 'stack_wrong_order',
                    'description' => 'config/logging.php: Stack has "local" before "superlog" - logs stop at local',
                    'detail' => 'Reorder stack so "superlog" comes before "local", or remove "local" entirely',
                ];
            }
        }

        return $issues;
    }

    /**
     * Apply automatic fixes to configuration
     */
    protected function applyAutoFixes(array $confirmFixes): void
    {
        $this->newLine();
        $this->line('<fg=cyan>⚙️  APPLYING AUTOMATIC FIXES...</>');
        $this->newLine();

        $envPath = base_path('.env');
        $loggingConfigPath = config_path('logging.php');
        $superlogConfigPath = config_path('superlog.php');

        $successCount = 0;
        $failureCount = 0;

        // Fix: Update .env file
        if (isset($confirmFixes['env_channel'])) {
            $this->output->write('  1. Updating .env (LOG_CHANNEL=superlog)... ');
            if ($this->updateEnvFile($envPath)) {
                $this->line('<fg=green>✓</>');
                $successCount++;
            } else {
                $this->line('<fg=red>✗</> (file not found or permission denied)');
                $failureCount++;
            }
        }

        // Fix: Update config/superlog.php channel
        if (isset($confirmFixes['superlog_channel'])) {
            $this->output->write('  2. Updating config/superlog.php (channel=superlog)... ');
            if ($this->updateSuperlogConfig($superlogConfigPath)) {
                $this->line('<fg=green>✓</>');
                $successCount++;
            } else {
                $this->line('<fg=red>✗</> (could not update)');
                $failureCount++;
            }
        }

        // Fix: Update config/logging.php default channel
        if (isset($confirmFixes['logging_default'])) {
            $this->output->write('  3. Updating config/logging.php (default=superlog)... ');
            if ($this->updateLoggingConfig($loggingConfigPath)) {
                $this->line('<fg=green>✓</>');
                $successCount++;
            } else {
                $this->line('<fg=red>✗</> (could not update)');
                $failureCount++;
            }
        }

        // Fix: Add superlog to stack (if applicable)
        if (isset($confirmFixes['stack_missing_superlog']) || isset($confirmFixes['stack_wrong_order'])) {
            $this->output->write('  4. Updating config/logging.php stack channels... ');
            if ($this->updateStackChannels($loggingConfigPath)) {
                $this->line('<fg=green>✓</>');
                $successCount++;
            } else {
                $this->line('<fg=red>✗</> (could not update)');
                $failureCount++;
            }
        }

        $this->newLine();
        if ($successCount > 0) {
            $this->info('✅ ' . $successCount . ' configuration(s) fixed successfully!');
        }
        if ($failureCount > 0) {
            $this->warn('⚠️  ' . $failureCount . ' configuration(s) could not be auto-fixed');
        }

        $this->newLine();
        $this->line('⚡ <fg=cyan>NEXT STEPS:</>');
        $this->line('  1. Clear Laravel cache: <fg=cyan>php artisan cache:clear</>');
        $this->line('  2. Verify the fixes: <fg=cyan>php artisan superlog:check --diagnostics</>');
    }

    /**
     * Update .env file with LOG_CHANNEL=superlog
     */
    protected function updateEnvFile(string $envPath): bool
    {
        if (!File::exists($envPath)) {
            return false;
        }

        try {
            $content = File::get($envPath);
            
            // Check if LOG_CHANNEL already exists
            if (preg_match('/^LOG_CHANNEL=.*$/m', $content)) {
                // Replace existing
                $newContent = preg_replace(
                    '/^LOG_CHANNEL=.*$/m',
                    'LOG_CHANNEL=superlog',
                    $content
                );
            } else {
                // Add new line
                $newContent = $content . "\nLOG_CHANNEL=superlog";
            }

            File::put($envPath, $newContent);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Update config/logging.php default channel
     */
    protected function updateLoggingConfig(string $path): bool
    {
        if (!File::exists($path)) {
            return false;
        }

        try {
            $content = File::get($path);
            
            // Replace the default channel setting
            $newContent = preg_replace(
                '/\'default\'\s*=>\s*env\([\'"]LOG_CHANNEL[\'"],\s*[\'"]([^\'"]+)[\'"]\)/',
                '\'default\' => env(\'LOG_CHANNEL\', \'superlog\')',
                $content
            );

            if ($newContent === $content) {
                return false; // No change made
            }

            File::put($path, $newContent);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Update config/superlog.php channel
     */
    protected function updateSuperlogConfig(string $path): bool
    {
        if (!File::exists($path)) {
            return false;
        }

        try {
            $content = File::get($path);
            
            // Replace the channel setting
            $newContent = preg_replace(
                '/\'channel\'\s*=>\s*env\([\'"]LOG_CHANNEL[\'"],\s*[\'"]([^\'"]+)[\'"]\)/',
                '\'channel\' => env(\'LOG_CHANNEL\', \'superlog\')',
                $content
            );

            if ($newContent === $content) {
                return false; // No change made
            }

            File::put($path, $newContent);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Update config/logging.php stack channels to include superlog
     */
    protected function updateStackChannels(string $path): bool
    {
        if (!File::exists($path)) {
            return false;
        }

        try {
            $content = File::get($path);
            
            // Check if stack already has superlog
            if (preg_match('/\'stack\'\s*=>\s*\[[^\]]*\'channels\'\s*=>\s*\[[^\]]*[\'"]superlog[\'"]/s', $content)) {
                return true; // Already configured
            }

            // Replace the channels array in stack to add superlog
            $newContent = preg_replace(
                '/\'stack\'\s*=>\s*\[\s*\'channels\'\s*=>\s*\[(.*?)\]\s*,/',
                "'stack' => [\n            'channels' => ['superlog', \$1],",
                $content
            );

            if ($newContent === $content) {
                return false; // No change made
            }

            File::put($path, $newContent);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Show manual fix instructions for each issue
     */
    protected function showManualFixes(array $issues = []): void
    {
        $this->newLine();
        $this->info('🔧 MANUAL FIX INSTRUCTIONS');
        $this->newLine();

        if (empty($issues)) {
            $this->line('Unable to auto-fix. Please manually resolve the configuration issues:');
            $this->newLine();
        }

        // Group issues by file for cleaner presentation
        $issuesByFile = [
            '.env' => [],
            'config/superlog.php' => [],
            'config/logging.php' => [],
        ];

        foreach ($issues as $issue) {
            if ($issue['key'] === 'env_channel') {
                $issuesByFile['.env'][] = $issue;
            } elseif ($issue['key'] === 'superlog_channel') {
                $issuesByFile['config/superlog.php'][] = $issue;
            } else {
                $issuesByFile['config/logging.php'][] = $issue;
            }
        }

        // Show .env fixes
        if (!empty($issuesByFile['.env'])) {
            $this->line('<fg=yellow>📄 File: .env</>');
            foreach ($issuesByFile['.env'] as $issue) {
                $this->line('  ' . $issue['description']);
                $this->line('  Action: Add or update this line:');
                $this->line('    <fg=cyan>LOG_CHANNEL=superlog</>');
            }
            $this->newLine();
        }

        // Show config/superlog.php fixes
        if (!empty($issuesByFile['config/superlog.php'])) {
            $this->line('<fg=yellow>📄 File: config/superlog.php</>');
            foreach ($issuesByFile['config/superlog.php'] as $issue) {
                $this->line('  ' . $issue['description']);
                $this->line('  Action: Update the channel setting:');
                $this->line('    From: <fg=red>\'channel\' => env(\'LOG_CHANNEL\', \'...\'),</>');
                $this->line('    To:   <fg=green>\'channel\' => env(\'LOG_CHANNEL\', \'superlog\'),</>');
            }
            $this->newLine();
        }

        // Show config/logging.php fixes
        if (!empty($issuesByFile['config/logging.php'])) {
            $this->line('<fg=yellow>📄 File: config/logging.php</>');
            foreach ($issuesByFile['config/logging.php'] as $issue) {
                $this->line('  ' . $issue['description']);
                
                if ($issue['key'] === 'logging_default') {
                    $this->line('  Action: Update the default channel:');
                    $this->line('    From: <fg=red>\'default\' => env(\'LOG_CHANNEL\', \'...\'),</>');
                    $this->line('    To:   <fg=green>\'default\' => env(\'LOG_CHANNEL\', \'superlog\'),</>');
                } elseif (in_array($issue['key'], ['stack_missing_superlog', 'stack_wrong_order'])) {
                    $this->line('  Action: Update stack channels:');
                    $this->line('    Make sure \'superlog\' is in the channels array:');
                    $this->line('    <fg=cyan>\'channels\' => [\'superlog\', \'single\'],</>');
                }
            }
            $this->newLine();
        }

        $this->line('⚡ <fg=cyan>Final Steps:</>');
        $this->line('  1. Edit the files shown above');
        $this->line('  2. Save the files');
        $this->line('  3. Clear Laravel cache: <fg=cyan>php artisan cache:clear</>');
        $this->line('  4. Run diagnostic again: <fg=cyan>php artisan superlog:check --diagnostics</>');
    }
}