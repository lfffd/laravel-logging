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
        // Since PHPUnit runs in CLI, this should automatically get a temporary trace ID
        $correlation = app(CorrelationContext::class);
        
        $traceId = $correlation->getTraceId();
        
        $this->assertNotNull($traceId);
        $this->assertStringStartsWith('tmp/', $traceId);
    }

    /** @test */
    public function it_logs_with_trace_id_in_cli_context()
    {
        // Create a test log
        $logEntry = Superlog::log('info', 'TEST', 'Test message in CLI context');
        
        $this->assertArrayHasKey('trace_id', $logEntry);
        $this->assertStringStartsWith('tmp/', $logEntry['trace_id']);
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
    
    /** @test */
    public function it_maintains_consistent_trace_id_across_different_instances()
    {
        // Get the correlation context from the container
        $correlation1 = app(CorrelationContext::class);
        $traceId1 = $correlation1->getTraceId();
        
        // Get another instance from the container
        $correlation2 = app(CorrelationContext::class);
        $traceId2 = $correlation2->getTraceId();
        
        // Both should have the same trace ID (singleton)
        $this->assertEquals($traceId1, $traceId2);
        
        // Create a log entry
        $logEntry = Superlog::log('info', 'TEST', 'Test message for consistency check');
        
        // The log entry should have the same trace ID
        $this->assertEquals($traceId1, $logEntry['trace_id']);
        
        // Get the trace ID from the logger
        $logger = app('Superlog\Logger\StructuredLogger');
        $traceId3 = $logger->getCorrelation()->getTraceId();
        
        // It should be the same as the others
        $this->assertEquals($traceId1, $traceId3);
    }
    
    /** @test */
    public function it_updates_trace_id_when_permanent_id_is_set()
    {
        // Get the correlation context from the container (singleton)
        $correlation = app(CorrelationContext::class);
        
        // Get the initial temporary trace ID
        $tempTraceId = $correlation->getTraceId();
        $this->assertStringStartsWith('tmp/', $tempTraceId);
        
        // Create a log with the temporary ID
        $logEntry1 = Superlog::log('info', 'TEST', 'Test message with temporary ID');
        $this->assertEquals($tempTraceId, $logEntry1['trace_id']);
        
        // Set a permanent trace ID
        $permanentTraceId = 'permanent_' . \Illuminate\Support\Str::uuid()->toString();
        $correlation->setTraceId($permanentTraceId);
        
        // Create another log - should use the permanent ID
        $logEntry2 = Superlog::log('info', 'TEST', 'Test message with permanent ID');
        $this->assertEquals($permanentTraceId, $logEntry2['trace_id']);
        
        // All subsequent logs should use the permanent ID
        $logEntry3 = Superlog::log('info', 'TEST', 'Another test message');
        $this->assertEquals($permanentTraceId, $logEntry3['trace_id']);
        
        // Get a new instance of the correlation context from the container
        // It should have the same trace ID (singleton)
        $newCorrelation = app(CorrelationContext::class);
        $this->assertEquals($permanentTraceId, $newCorrelation->getTraceId());
    }
}