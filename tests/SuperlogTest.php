<?php

namespace Superlog\Tests;

use PHPUnit\Framework\TestCase;
use Superlog\Logger\StructuredLogger;
use Superlog\Utils\CorrelationContext;
use Superlog\Processors\RedactionProcessor;
use Superlog\Processors\PayloadProcessor;

class SuperlogTest extends TestCase
{
    protected StructuredLogger $logger;
    protected array $config;

    protected function setUp(): void
    {
        $this->config = [
            'enabled' => true,
            'channel' => 'testing',
            'format' => 'json',
            'redaction' => [
                'enabled' => true,
                'mode' => 'mask',
                'mask_char' => '*',
                'smart_detection' => true,
                'custom_keys' => [],
                'patterns' => [
                    'password', 'token', 'secret', 'api_key',
                    'email', 'phone', 'ssn', 'iban',
                ],
            ],
            'payload_handling' => [
                'max_string_length' => 5000,
                'max_array_depth' => 10,
                'max_file_size_kb' => 1024,
                'summarize_uploads' => true,
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

    public function test_initialize_request()
    {
        $this->logger->initializeRequest('GET', '/users', '127.0.0.1', 'test-trace-id');
        
        $correlation = $this->logger->getCorrelation();
        
        $this->assertEquals('test-trace-id', $correlation->getTraceId());
        $this->assertEquals('GET', $correlation->getMethod());
        $this->assertEquals('/users', $correlation->getPath());
        $this->assertEquals('127.0.0.1', $correlation->getClientIp());
    }

    public function test_log_creates_entry_with_correlation()
    {
        $this->logger->initializeRequest('POST', '/api/users', '192.168.1.1');
        
        $entry = $this->logger->log('info', 'TEST_SECTION', 'Test message', [
            'user_id' => 123,
        ], [
            'duration_ms' => 45.2,
        ]);

        $this->assertNotEmpty($entry);
        $this->assertEquals('INFO', $entry['level']);
        $this->assertEquals('[TEST_SECTION]', $entry['section']);
        $this->assertEquals('Test message', $entry['message']);
        $this->assertNotEmpty($entry['trace_id']);
        $this->assertEquals('0000000001', $entry['req_seq']);
        $this->assertNotEmpty($entry['span_id']);
    }

    public function test_log_startup()
    {
        $this->logger->initializeRequest('GET', '/dashboard', '192.168.1.100');
        
        $entry = $this->logger->logStartup([
            'method' => 'GET',
            'path' => '/dashboard',
            'full_url' => 'http://localhost/dashboard',
            'ip' => '192.168.1.100',
            'user_id' => 456,
            'tenant_id' => 'tenant-123',
            'session_id' => 'sess-789',
            'user_agent' => 'Mozilla/5.0...',
            'query_string' => 'page=1&sort=name',
            'payload' => '',
        ]);

        $this->assertEquals('INFO', $entry['level']);
        $this->assertEquals('[STARTUP]', $entry['section']);
        $this->assertEquals('GET', $entry['metrics']['method']);
        $this->assertEquals(456, $entry['metrics']['user_id']);
    }

    public function test_log_middleware_timing()
    {
        $this->logger->initializeRequest('GET', '/api/data', '127.0.0.1');
        
        $entry = $this->logger->logMiddlewareEnd(
            'AuthenticateWithApiKey',
            123.45,
            200,
            ['keys_checked' => 3]
        );

        $this->assertEquals('[MIDDLEWARE-END]', $entry['section']);
        $this->assertEquals(123.45, $entry['metrics']['duration_ms']);
        $this->assertEquals(200, $entry['metrics']['response_status']);
        $this->assertEquals(3, $entry['metrics']['keys_checked']);
    }

    public function test_log_database_stats()
    {
        $this->logger->initializeRequest('GET', '/users', '127.0.0.1');
        
        $entry = $this->logger->logDatabase([
            'query_count' => 5,
            'total_query_ms' => 234.5,
            'slowest_query_ms' => 89.2,
            'slow_queries' => [
                'SELECT * FROM users WHERE status = ?',
            ],
        ]);

        $this->assertEquals('[DATABASE]', $entry['section']);
        $this->assertEquals(5, $entry['metrics']['query_count']);
        $this->assertEquals(234.5, $entry['metrics']['total_query_ms']);
    }

    public function test_log_http_out()
    {
        $this->logger->initializeRequest('GET', '/orders', '127.0.0.1');
        
        $entry = $this->logger->logHttpOut(
            'POST',
            'https://payment-gateway.example.com/charge',
            200,
            567.8,
            ['retry_count' => 1, 'circuit_breaker' => 'closed']
        );

        $this->assertEquals('[HTTP-OUT]', $entry['section']);
        $this->assertEquals('POST', $entry['metrics']['method']);
        $this->assertEquals(200, $entry['metrics']['status']);
        $this->assertEquals(567.8, $entry['metrics']['duration_ms']);
    }

    public function test_correlation_trace_id_generation()
    {
        $correlation = new CorrelationContext();
        
        $traceId1 = $correlation->getTraceId();
        $this->assertNotEmpty($traceId1);
        
        // UUID v4 format: 8-4-4-4-12 hex digits
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $traceId1
        );
    }

    public function test_correlation_span_ids()
    {
        $correlation = new CorrelationContext();
        
        $spanId1 = $correlation->getOrCreateSpanId('STARTUP');
        $spanId2 = $correlation->getOrCreateSpanId('DATABASE');
        $spanId1Again = $correlation->getOrCreateSpanId('STARTUP');

        $this->assertNotEmpty($spanId1);
        $this->assertNotEmpty($spanId2);
        $this->assertEquals($spanId1, $spanId1Again); // Same section = same span_id
        $this->assertNotEquals($spanId1, $spanId2); // Different sections = different span_ids
    }

    public function test_redaction_masks_passwords()
    {
        $redactor = new RedactionProcessor($this->config['redaction']);
        
        $entry = [
            'context' => [
                'password' => 'secret123',
                'username' => 'john_doe',
            ],
        ];

        $redacted = $redactor->process($entry);
        
        $this->assertNotEquals('secret123', $redacted['context']['password']);
        $this->assertStringContainsString('*', $redacted['context']['password']);
    }

    public function test_redaction_masks_tokens()
    {
        $redactor = new RedactionProcessor($this->config['redaction']);
        
        $entry = [
            'context' => [
                'api_token' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...',
                'user_id' => 123,
            ],
        ];

        $redacted = $redactor->process($entry);
        
        $this->assertStringContainsString('*', $redacted['context']['api_token']);
        $this->assertEquals(123, $redacted['context']['user_id']); // Not redacted
    }

    public function test_payload_truncates_long_strings()
    {
        $processor = new PayloadProcessor($this->config['payload_handling']);
        
        $longString = str_repeat('a', 10000);
        
        $entry = [
            'context' => [
                'data' => $longString,
            ],
        ];

        $processed = $processor->process($entry);
        
        $this->assertLessThan(10000, strlen($processed['context']['data']));
        $this->assertStringContainsString('[TRUNCATED]', $processed['context']['data']);
    }

    public function test_log_format_json()
    {
        $this->logger->initializeRequest('GET', '/test', '127.0.0.1');
        
        $entry = $this->logger->log('info', 'TEST', 'Test message', [], ['test_metric' => 1]);
        $formatted = $this->logger->formatLogEntry($entry);
        
        $decoded = json_decode($formatted, true);
        $this->assertIsArray($decoded);
        $this->assertEquals('INFO', $decoded['level']);
        $this->assertEquals('Test message', $decoded['message']);
    }

    public function test_sequential_req_seq_numbering()
    {
        $this->logger->initializeRequest('GET', '/api', '127.0.0.1');
        
        $entry1 = $this->logger->log('info', 'SECTION1', 'Message 1');
        $entry2 = $this->logger->log('info', 'SECTION2', 'Message 2');
        $entry3 = $this->logger->log('info', 'SECTION3', 'Message 3');
        
        $this->assertEquals('0000000001', $entry1['req_seq']);
        $this->assertEquals('0000000002', $entry2['req_seq']);
        $this->assertEquals('0000000003', $entry3['req_seq']);
    }
}