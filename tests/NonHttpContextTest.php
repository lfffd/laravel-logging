<?php

namespace Superlog\Tests;

use Illuminate\Support\Str;
use Orchestra\Testbench\TestCase;
use Superlog\Facades\Superlog;
use Superlog\SuperlogServiceProvider;
use Superlog\Utils\CorrelationContext;

class NonHttpContextTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [SuperlogServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('superlog.enabled', true);
        $app['config']->set('superlog.non_http_context.enabled', true);
        $app['config']->set('superlog.non_http_context.prefix_trace_id', true);
    }

    /** @test */
    public function it_generates_trace_id_for_cli_context()
    {
        // Since PHPUnit runs in CLI, this should automatically get a CLI trace ID
        $correlation = app(CorrelationContext::class);
        
        $traceId = $correlation->getTraceId();
        
        $this->assertNotNull($traceId);
        $this->assertStringStartsWith('cli_', $traceId);
    }

    /** @test */
    public function it_logs_with_trace_id_in_cli_context()
    {
        // Create a test log
        $logEntry = Superlog::log('info', 'TEST', 'Test message in CLI context');
        
        $this->assertArrayHasKey('trace_id', $logEntry);
        $this->assertStringStartsWith('cli_', $logEntry['trace_id']);
    }

    /** @test */
    public function it_maintains_same_trace_id_across_multiple_logs()
    {
        // First log
        $log1 = Superlog::log('info', 'TEST', 'First test message');
        
        // Second log
        $log2 = Superlog::log('info', 'TEST', 'Second test message');
        
        // Both should have the same trace ID
        $this->assertEquals($log1['trace_id'], $log2['trace_id']);
    }

    /** @test */
    public function it_uses_different_span_ids_for_different_sections()
    {
        // Log with section A
        $logA = Superlog::log('info', 'SECTION_A', 'Test message A');
        
        // Log with section B
        $logB = Superlog::log('info', 'SECTION_B', 'Test message B');
        
        // Same trace ID
        $this->assertEquals($logA['trace_id'], $logB['trace_id']);
        
        // Different span IDs
        $this->assertNotEquals($logA['span_id'], $logB['span_id']);
    }

    /** @test */
    public function it_formats_logs_with_trace_id_in_header()
    {
        // Get the logger
        $logger = app('Superlog\Logger\StructuredLogger');
        
        // Create a log entry
        $logEntry = Superlog::log('info', 'TEST', 'Test message for formatting');
        
        // Format the entry
        $formatted = $logger->formatLogEntry($logEntry);
        
        // Check that the trace ID is in the formatted output
        $this->assertStringContainsString($logEntry['trace_id'], $formatted);
    }
}