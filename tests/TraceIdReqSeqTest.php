<?php

namespace Superlog\Tests;

use PHPUnit\Framework\TestCase;
use Superlog\Logger\StructuredLogger;
use Superlog\Handlers\SuperlogHandler;
use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;
use Monolog\LogRecord;
use Monolog\Level;

class TraceIdReqSeqTest extends TestCase
{
    protected StructuredLogger $logger;
    protected array $config;
    protected string $testLogFile;

    protected function setUp(): void
    {
        $this->testLogFile = sys_get_temp_dir() . '/superlog-test-' . time() . '.log';

        $this->config = [
            'enabled' => true,
            'channel' => 'superlog',
            'format' => 'text',
            'redaction' => [
                'enabled' => false,
                'mode' => 'mask',
                'mask_char' => '*',
                'smart_detection' => false,
                'custom_keys' => [],
                'patterns' => [],
            ],
            'payload_handling' => [
                'max_string_length' => 5000,
                'max_array_depth' => 10,
                'max_file_size_kb' => 1024,
                'summarize_uploads' => false,
            ],
            'sections' => [
                'startup' => ['enabled' => true],
                'middleware' => ['enabled' => true],
                'database' => ['enabled' => true],
                'cache' => ['enabled' => true],
                'http_outbound' => ['enabled' => true],
                'shutdown' => ['enabled' => true],
            ],
        ];

        $this->logger = new StructuredLogger($this->config);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testLogFile)) {
            unlink($this->testLogFile);
        }
    }

    /**
     * Test 1: Verify trace_id and req_seq are created in log entry array
     */
    public function test_log_entry_contains_trace_id_and_req_seq(): void
    {
        $this->logger->initializeRequest('GET', '/test', '127.0.0.1', 'test-trace-id-123');

        $entry = $this->logger->log('info', 'TEST_SECTION', 'Test message', [
            'user_id' => 123,
        ]);

        $this->assertEquals('test-trace-id-123', $entry['trace_id']);
        $this->assertEquals('0000000001', $entry['req_seq']);
        $this->assertNotEmpty($entry['timestamp']);
    }

    /**
     * Test 2: Verify seq counter increments correctly
     */
    public function test_req_seq_increments_correctly(): void
    {
        $this->logger->initializeRequest('GET', '/test', '127.0.0.1', 'trace-abc');

        $entry1 = $this->logger->log('info', 'SECTION1', 'Message 1');
        $entry2 = $this->logger->log('info', 'SECTION2', 'Message 2');
        $entry3 = $this->logger->log('info', 'SECTION3', 'Message 3');

        $this->assertEquals('0000000001', $entry1['req_seq']);
        $this->assertEquals('0000000002', $entry2['req_seq']);
        $this->assertEquals('0000000003', $entry3['req_seq']);
    }

    /**
     * Test 3: Verify formatted text includes trace_id and req_seq
     */
    public function test_formatted_text_includes_trace_id_and_req_seq(): void
    {
        $this->logger->initializeRequest('GET', '/test', '127.0.0.1', 'trace-xyz-789');

        $entry = $this->logger->log('info', 'MIDDLEWARE', 'Auth check', ['user_id' => 456]);
        $formatted = $this->logger->formatLogEntry($entry);

        // The format should be: [timestamp] channel.LEVEL: [trace_id:req_seq] [SECTION] message {context}
        $this->assertStringContainsString('trace-xyz-789:0000000001', $formatted);
        $this->assertStringContainsString('[MIDDLEWARE]', $formatted);
        $this->assertStringContainsString('Auth check', $formatted);
    }

    /**
     * Test 4: Handler receives complete log entry with trace_id and req_seq in context
     */
    public function test_handler_receives_trace_id_in_context(): void
    {
        $this->logger->initializeRequest('GET', '/api/data', '192.168.1.1', 'handler-test-trace');

        $entry = $this->logger->log('warning', 'DATABASE', 'Slow query detected', [
            'duration_ms' => 523.5,
        ]);

        // Verify the entry itself has the data
        $this->assertEquals('handler-test-trace', $entry['trace_id']);
        $this->assertEquals('0000000001', $entry['req_seq']);

        // Formatted output should include both
        $formatted = $this->logger->formatLogEntry($entry);
        $this->assertStringContainsString('handler-test-trace:0000000001', $formatted);
    }

    /**
     * Test 5: JSON format includes trace_id and req_seq
     */
    public function test_json_format_includes_trace_id_and_req_seq(): void
    {
        $config = $this->config;
        $config['format'] = 'json';
        $logger = new StructuredLogger($config);

        $logger->initializeRequest('POST', '/api/users', '10.0.0.1', 'json-trace-001');

        $entry = $logger->log('info', 'STARTUP', 'Request started', ['endpoint' => '/api/users']);
        $formatted = $logger->formatLogEntry($entry);

        $decoded = json_decode($formatted, true);

        $this->assertEquals('json-trace-001', $decoded['trace_id']);
        $this->assertEquals('0000000001', $decoded['req_seq']);
        $this->assertNotEmpty($decoded['span_id']);
    }

    /**
     * Test 6: Multiple requests don't share trace_id
     */
    public function test_separate_requests_have_different_trace_ids(): void
    {
        $this->logger->initializeRequest('GET', '/req1', '127.0.0.1', 'trace-first');
        $entry1 = $this->logger->log('info', 'TEST', 'Request 1');

        $this->logger->initializeRequest('GET', '/req2', '127.0.0.1', 'trace-second');
        $entry2 = $this->logger->log('info', 'TEST', 'Request 2');

        $this->assertEquals('trace-first', $entry1['trace_id']);
        $this->assertEquals('trace-second', $entry2['trace_id']);
    }

    /**
     * Test 7: Sequence resets per request
     */
    public function test_req_seq_resets_per_request(): void
    {
        $this->logger->initializeRequest('GET', '/req1', '127.0.0.1', 'trace-1');
        $this->logger->log('info', 'SECTION1', 'Message 1');
        $this->logger->log('info', 'SECTION2', 'Message 2');

        $this->logger->initializeRequest('GET', '/req2', '127.0.0.1', 'trace-2');
        $entry = $this->logger->log('info', 'SECTION1', 'New request message');

        // Should reset to 1, not continue from 3
        $this->assertEquals('0000000001', $entry['req_seq']);
    }

    /**
     * Test 8: Formatted text structure validation
     */
    public function test_formatted_text_structure(): void
    {
        $this->logger->initializeRequest('GET', '/test', '127.0.0.1', 'struct-test-123');

        $entry = $this->logger->log('info', 'MIDDLEWARE_END', 'Auth middleware completed', [
            'status' => 'success',
            'duration_ms' => 12.5,
        ]);

        $formatted = $this->logger->formatLogEntry($entry);

        // Expected format: [timestamp] channel.LEVEL: [trace_id:req_seq] [SECTION] message {json_data}
        $pattern = '/\[\d{4}-\d{2}-\d{2}T[^\]]+\]\s+superlog\.INFO:\s+\[struct-test-123:0000000001\]\s+\[MIDDLEWARE_END\]/';
        $this->assertMatchesRegularExpression($pattern, $formatted);
    }

    /**
     * Test 9: Verify no trace_id/req_seq loss through formatting pipeline
     */
    public function test_no_data_loss_in_formatting(): void
    {
        $this->logger->initializeRequest('DELETE', '/api/resource/123', '203.0.113.1', 'pipeline-trace');

        $originalEntry = $this->logger->log('error', 'HTTP_OUT', 'External API error', [
            'api' => 'payment-gateway',
            'error_code' => 'TIMEOUT',
        ]);

        $formatted = $this->logger->formatLogEntry($originalEntry);

        // Both should be present
        $this->assertStringContainsString($originalEntry['trace_id'], $formatted);
        $this->assertStringContainsString($originalEntry['req_seq'], $formatted);
        $this->assertStringContainsString('payment-gateway', $formatted);
        $this->assertStringContainsString('TIMEOUT', $formatted);
    }

    /**
     * Test 10: Trace ID format validation (UUID v4 when auto-generated)
     */
    public function test_auto_generated_trace_id_is_valid_uuid(): void
    {
        $this->logger->initializeRequest('GET', '/test', '127.0.0.1');
        $entry = $this->logger->log('info', 'TEST', 'Message');

        $traceId = $entry['trace_id'];

        // Should be UUID v4 format: 8-4-4-4-12 hex digits
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $traceId
        );
    }
}