<?php

namespace Superlog\Logger;

use Ramsey\Uuid\Uuid;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Superlog\Utils\CorrelationContext;
use Superlog\Processors\RedactionProcessor;
use Superlog\Processors\PayloadProcessor;

class StructuredLogger
{
    protected array $config;
    protected CorrelationContext $correlation;
    protected RedactionProcessor $redactor;
    protected PayloadProcessor $payloadProcessor;
    protected array $sections = [];
    protected int $sequenceCounter = 0;
    protected ?string $logChannel = null;

    public function __construct(array $config, ?string $logChannel = null)
    {
        $this->config = $config;
        
        // Use the existing CorrelationContext instance from the container
        if (app()->has(CorrelationContext::class)) {
            $this->correlation = app()->make(CorrelationContext::class);
        } else {
            // Fallback to a new instance if not registered (should never happen)
            $this->correlation = new CorrelationContext();
        }
        
        $this->redactor = new RedactionProcessor($config['redaction']);
        $this->payloadProcessor = new PayloadProcessor($config['payload_handling']);
        $this->logChannel = $logChannel ?? 'superlog';
    }

    /**
     * Initialize a new request context
     */
    public function initializeRequest(string $method, string $path, string $ip, ?string $traceId = null): void
    {
        // If a trace ID is provided and it's not a temporary ID, use it
        if ($traceId && strpos($traceId, 'tmp/') !== 0) {
            $this->correlation->setTraceId($traceId);
        } else if (!$traceId) {
            // If no trace ID is provided, generate a permanent one
            $traceId = Uuid::uuid4()->toString();
            $this->correlation->setTraceId($traceId);
        }
        // If it's a temporary ID, we keep it until a permanent one is set

        $this->correlation->setMethod($method);
        $this->correlation->setPath($path);
        $this->correlation->setClientIp($ip);
        
        // Load sequence counter from session and increment
        $sessionKey = 'superlog_req_seq';
        $this->sequenceCounter = (int) session($sessionKey, 0);
        $this->sequenceCounter++;
        session([$sessionKey => $this->sequenceCounter]);
        
        $this->sections = [];
    }

    /**
     * Log a structured message with context
     */
    public function log(
        string $level,
        string $section,
        string $message,
        array $context = [],
        array $metrics = []
    ): array {
        if (!$this->config['enabled']) {
            return [];
        }

        $requestSeq = str_pad((string) $this->sequenceCounter, 10, '0', STR_PAD_LEFT);

        $spanId = $this->correlation->getOrCreateSpanId($section);

        $logEntry = [
            'timestamp' => now()->format('Y-m-d\TH:i:s.uP'),
            'trace_id' => $this->correlation->getTraceId(),
            'req_seq' => $requestSeq,
            'span_id' => $spanId,
            'level' => strtoupper($level),
            'section' => "[$section]",
            'message' => $message,
            'context' => $context,
        ];

        if (!empty($metrics)) {
            $logEntry['metrics'] = $metrics;
        }

        // Add correlation data
        $logEntry['correlation'] = $this->correlation->toArray();

        // Apply redaction
        if ($this->config['redaction']['enabled']) {
            $logEntry = $this->redactor->process($logEntry);
        }

        // Process payloads
        $logEntry = $this->payloadProcessor->process($logEntry);

        // Write to Laravel's logging system
        $this->writeToLog($level, $logEntry);

        return $logEntry;
    }

    /**
     * Write the log entry to Laravel's logging system
     */
    protected function writeToLog(string $level, array $logEntry): void
    {
        try {
            // Pass the entire log entry as context so the handler can access it
            Log::channel($this->logChannel)->log(
                strtolower($logEntry['level']),
                $logEntry['message'],
                [
                    'section' => $logEntry['section'],
                    'trace_id' => $logEntry['trace_id'],
                    'req_seq' => $logEntry['req_seq'],
                    'span_id' => $logEntry['span_id'],
                    'context' => $logEntry['context'],
                    'metrics' => $logEntry['metrics'] ?? [],
                    'correlation' => $logEntry['correlation'],
                    '_superlog_entry' => $logEntry,  // Pass complete entry for the handler
                ]
            );
        } catch (\Exception $e) {
            // Silently fail if logging fails to prevent breaking the application
        }
    }

    /**
     * Log startup section
     */
    public function logStartup(array $requestData): array
    {
        // First log entry: URL
        $url = $requestData['full_url'] ?? '';
        $this->log('info', 'STARTUP', 'URL: ' . $url, [], []);

        // Second log entry: Full request details
        $metrics = [
            'method' => $requestData['method'],
            'path' => $requestData['path'],
            'full_url' => $requestData['full_url'] ?? null,
            'ip' => $requestData['ip'],
            'user_id' => $requestData['user_id'] ?? null,
            'tenant_id' => $requestData['tenant_id'] ?? null,
            'session_id' => $requestData['session_id'] ?? null,
        ];

        if ($this->config['sections']['startup']['capture_user_agent']) {
            $metrics['user_agent'] = $requestData['user_agent'] ?? null;
        }

        if ($this->config['sections']['startup']['capture_query_string']) {
            $metrics['query_string'] = $requestData['query_string'] ?? null;
        }

        $metrics['request_payload_size'] = strlen($requestData['payload'] ?? '') ?? 0;

        return $this->log('info', 'STARTUP', 'Request initiated', [], $metrics);
    }

    /**
     * Log middleware start
     */
    public function logMiddlewareStart(string $middlewareName, string $requestId): array
    {
        return $this->log(
            'info',
            'MIDDLEWARE-START',
            "[MIDDLEWARE START] $middlewareName",
            ['request_id' => $requestId],
            []
        );
    }

    /**
     * Log middleware end with timing
     */
    public function logMiddlewareEnd(string $middlewareName, float $durationMs, int $responseStatus, array $extraMetrics = []): array
    {
        $metrics = array_merge([
            'duration_ms' => $durationMs,
            'response_status' => $responseStatus,
        ], $extraMetrics);

        return $this->log(
            'info',
            'MIDDLEWARE-END',
            "[MIDDLEWARE END] $middlewareName - SUCCESS",
            [],
            $metrics
        );
    }

    /**
     * Log database section with aggregated stats
     */
    public function logDatabase(array $dbStats): array
    {
        $metrics = [
            'query_count' => $dbStats['query_count'] ?? 0,
            'total_query_ms' => $dbStats['total_query_ms'] ?? 0,
            'slowest_query_ms' => $dbStats['slowest_query_ms'] ?? 0,
            'slow_queries_count' => count($dbStats['slow_queries'] ?? []),
        ];

        $context = [];
        if ($this->config['sections']['database']['capture_bindings'] && !empty($dbStats['slow_queries'])) {
            $context['slow_queries'] = array_slice($dbStats['slow_queries'], 0, 5); // Top 5
        }

        return $this->log('info', 'DATABASE', 'Database statistics', $context, $metrics);
    }

    /**
     * Log HTTP outbound call
     */
    public function logHttpOut(string $method, string $url, int $status, float $durationMs, array $extraMetrics = []): array
    {
        $metrics = array_merge([
            'method' => $method,
            'url' => $url,
            'status' => $status,
            'duration_ms' => $durationMs,
        ], $extraMetrics);

        return $this->log('info', 'HTTP-OUT', "HTTP $method $url - $status", [], $metrics);
    }

    /**
     * Log cache statistics
     */
    public function logCache(array $cacheStats): array
    {
        $metrics = [
            'hits' => $cacheStats['hits'] ?? 0,
            'misses' => $cacheStats['misses'] ?? 0,
            'sets' => $cacheStats['sets'] ?? 0,
            'duration_ms' => $cacheStats['duration_ms'] ?? 0,
        ];

        return $this->log('info', 'CACHE', 'Cache statistics', [], $metrics);
    }

    /**
     * Log shutdown section
     */
    public function logShutdown(array $shutdownData): array
    {
        $metrics = [
            'request_ms' => $shutdownData['request_ms'] ?? 0,
            'response_status' => $shutdownData['response_status'] ?? 0,
            'response_bytes' => $shutdownData['response_bytes'] ?? 0,
        ];

        if ($this->config['sections']['shutdown']['capture_memory_peak']) {
            $metrics['memory_peak_mb'] = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
        }

        if ($this->config['sections']['shutdown']['capture_included_files_count']) {
            $metrics['included_files_count'] = count(get_included_files());
        }

        if ($this->config['sections']['shutdown']['capture_opcache_status']) {
            $metrics['opcache_enabled'] = extension_loaded('Zend OPcache');
        }

        if (!empty($shutdownData['queue_jobs_dispatched'])) {
            $metrics['queue_jobs_dispatched'] = $shutdownData['queue_jobs_dispatched'];
        }

        return $this->log('info', 'SHUTDOWN', 'Request completed', [], $metrics);
    }

    /**
     * Get current correlation context
     */
    public function getCorrelation(): CorrelationContext
    {
        // Always return the singleton instance from the container
        if (app()->has(CorrelationContext::class)) {
            return app()->make(CorrelationContext::class);
        }
        
        // Fallback to the instance we have (should never happen)
        return $this->correlation;
    }

    /**
     * Get the log channel
     */
    public function getLogChannel(): ?string
    {
        return $this->logChannel;
    }

    /**
     * Format log entry for output
     */
    public function formatLogEntry(array $entry): string
    {
        if ($this->config['format'] === 'json') {
            return json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        // Text format with prefix
        return $this->formatAsText($entry);
    }

    /**
     * Format as human-readable text
     */
    protected function formatAsText(array $entry): string
    {
        // Skip empty entries (no message, no context, no metrics)
        if (empty($entry['message']) && empty($entry['context']) && empty($entry['metrics'])) {
            return '';
        }

        $timestamp = $entry['timestamp'];
        $level = $entry['level'];
        $section = $entry['section'];
        $message = $entry['message'];
        
        // Ensure we have valid trace_id and req_seq
        $traceId = $entry['trace_id'] ?? null;
        if (empty($traceId) || $traceId === 'unknown' || $traceId === 'N/A') {
            // Try to get from correlation context
            if (app()->has('Superlog\Utils\CorrelationContext')) {
                $correlation = app()->make('Superlog\Utils\CorrelationContext');
                $traceId = $correlation->getTraceId();
            } else {
                // Generate a new temporary UUID
                $traceId = 'tmp/' . \Ramsey\Uuid\Uuid::uuid4()->toString();
            }
        }
        
        $reqSeq = $entry['req_seq'] ?? '0000000000';
        if (empty($reqSeq) || $reqSeq === '0') {
            $reqSeq = str_pad((string) $this->sequenceCounter, 10, '0', STR_PAD_LEFT);
        }
        
        $context = json_encode($entry['context'] ?? [], JSON_UNESCAPED_SLASHES);
        $metrics = json_encode($entry['metrics'] ?? [], JSON_UNESCAPED_SLASHES);

        // Include trace_id and req_seq in the header for better traceability
        $header = "[$timestamp] {$this->config['channel']}.$level: [$traceId:$reqSeq] $section $message";

        if (empty($entry['context']) && empty($entry['metrics'])) {
            return $header;
        }

        $data = array_merge($entry['context'] ?? [], $entry['metrics'] ?? []);
        return $header . ' ' . json_encode($data, JSON_UNESCAPED_SLASHES);
    }
}