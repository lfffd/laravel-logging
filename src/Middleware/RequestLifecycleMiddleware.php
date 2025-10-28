<?php

namespace Superlog\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Superlog\Logger\StructuredLogger;
use Superlog\Utils\RequestTimer;

class RequestLifecycleMiddleware
{
    protected StructuredLogger $logger;

    public function __construct(StructuredLogger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Handle an incoming request
     * 
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $traceId = $request->header(config('superlog.trace_id_header', 'X-Trace-Id'))
            ?? request()->header('X-Trace-Id');

        // If no trace ID is provided, generate a permanent one
        if (empty($traceId)) {
            // Log blank line separator when generating new trace ID
            $this->logSeparator();
            
            $traceId = \Illuminate\Support\Str::uuid()->toString();
        }

        // Initialize request context
        $this->logger->initializeRequest(
            $request->method(),
            $request->path(),
            $request->ip(),
            $traceId
        );

        // Log startup
        $this->logStartup($request);

        // Start timer
        $timer = new RequestTimer();

        try {
            $response = $next($request);
            
            // Register terminate callback to flush buffer when response is sent
            $response->onAfterSend(function () use ($timer) {
                $this->logShutdown($timer->elapsed());
                $this->logger->flushBuffer();
            });
            
            return $response;
        } catch (\Exception $e) {
            // On exception, flush buffer and log shutdown
            $this->logShutdown($timer->elapsed());
            $this->logger->flushBuffer();
            throw $e;
        } finally {
            // Also call flushBuffer in finally to ensure it's called
            // (in case response doesn't have onAfterSend or terminate not called)
        }
    }

    /**
     * Log a blank line separator
     */
    protected function logSeparator(): void
    {
        $logChannel = $this->logger->getLogChannel() ?? 'superlog';
        \Illuminate\Support\Facades\Log::channel($logChannel)->info('');
    }

    /**
     * Log request startup
     */
    protected function logStartup(Request $request): void
    {
        // Get session ID if available
        $sessionId = session()->getId() ?? null;
        
        // If we have a session ID, update the trace ID to a permanent one
        if ($sessionId) {
            // Get the correlation context from the container (singleton)
            $correlation = app()->make('Superlog\Utils\CorrelationContext');
            $currentTraceId = $correlation->getTraceId();
            
            // If the current trace ID is temporary, replace it with a permanent one
            if (strpos($currentTraceId, 'tmp/') === 0) {
                $permanentTraceId = \Illuminate\Support\Str::uuid()->toString();
                $correlation->setTraceId($permanentTraceId);
                
                // Log the transition
                $this->logger->log('info', 'GENERAL', "Session identification from {$currentTraceId}");
            }
        }
        
        $startupData = [
            'method' => $request->method(),
            'path' => $request->path(),
            'full_url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'query_string' => $request->getQueryString(),
            'user_id' => auth()->id() ?? null,
            'tenant_id' => $this->getTenantId() ?? null,
            'session_id' => $sessionId,
            'payload' => $request->getContent(),
        ];

        $this->logger->logStartup($startupData);
    }

    /**
     * Log request shutdown
     */
    protected function logShutdown(float $durationMs): void
    {
        $responseStatus = 0;
        $responseBytes = 0;

        // Use http_response_code() to get the response status
        $responseStatus = http_response_code() ?: 0;
        
        // Try to get response bytes from output buffer
        if (ob_get_level() > 0) {
            $responseBytes = ob_get_length() ?: 0;
        }

        $shutdownData = [
            'request_ms' => $durationMs,
            'response_status' => $responseStatus,
            'response_bytes' => $responseBytes,
            'queue_jobs_dispatched' => $this->countQueueJobsDispatched(),
        ];

        $this->logger->logShutdown($shutdownData);
    }

    /**
     * Try to get tenant ID (framework-agnostic)
     */
    protected function getTenantId(): ?string
    {
        // Try common tenant resolution methods
        if (function_exists('tenant') && $tenant = tenant()) {
            return $tenant->getKey() ?? null;
        }

        if (app()->has('tenant') && $t = app('tenant')) {
            return $t->getKey() ?? null;
        }

        return null;
    }

    /**
     * Count jobs dispatched during request (framework-agnostic estimate)
     */
    protected function countQueueJobsDispatched(): int
    {
        // This would require middleware integration with queue system
        // For now, return 0 as a placeholder
        return 0;
    }
}