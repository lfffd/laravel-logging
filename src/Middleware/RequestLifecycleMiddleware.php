<?php

namespace Superlog\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\RedirectResponse;
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
     */
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        $traceId = $request->header(config('superlog.trace_id_header', 'X-Trace-Id'))
            ?? request()->header('X-Trace-Id');

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
            return $response;
        } finally {
            // Log shutdown
            $this->logShutdown($timer->elapsed());
        }
    }

    /**
     * Log request startup
     */
    protected function logStartup(Request $request): void
    {
        $startupData = [
            'method' => $request->method(),
            'path' => $request->path(),
            'full_url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'query_string' => $request->getQueryString(),
            'user_id' => auth()->id() ?? null,
            'tenant_id' => $this->getTenantId() ?? null,
            'session_id' => session()->getId() ?? null,
            'payload' => $request->getContent(),
        ];

        $this->logger->logStartup($startupData);
    }

    /**
     * Log request shutdown
     */
    protected function logShutdown(float $durationMs): void
    {
        $shutdownData = [
            'request_ms' => $durationMs,
            'response_status' => http_response_code(),
            'response_bytes' => ob_get_level() > 0 ? ob_get_length() : 0,
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