<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Superlog\Facades\Superlog;
use Symfony\Component\HttpFoundation\Response;

/**
 * CORRECT WAY TO USE SUPERLOG IN MIDDLEWARE
 * 
 * This shows how middleware should integrate with Superlog
 * to properly capture trace_id and req_seq in logs.
 */
class ExampleMiddlewareWithSuperlog
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // IMPORTANT: Initialize Superlog at the start of request
        // This creates trace_id (UUID) and resets req_seq to 0
        Superlog::initializeRequest(
            $request->getMethod(),
            $request->getPathInfo(),
            $request->ip(),
            // Optional: pass existing trace_id from header for correlation
            $request->header('X-Trace-ID')
        );

        // Log request startup
        Superlog::logStartup([
            'method' => $request->getMethod(),
            'path' => $request->getPathInfo(),
            'full_url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_id' => auth()->id(),
            'user_agent' => $request->header('User-Agent'),
            'query_string' => $request->getQueryString(),
            'payload' => '',
        ]);

        // Your middleware logic here
        $startTime = microtime(true);
        
        // Log middleware entry if needed
        Superlog::log('debug', 'MIDDLEWARE', 'MyMiddleware processing started');

        // Call next middleware
        $response = $next($request);

        // Log middleware completion with timing and status
        $duration = (microtime(true) - $startTime) * 1000;
        
        Superlog::logMiddlewareEnd(
            static::class,
            $duration,
            $response->getStatusCode(),
            [
                'response_size_bytes' => strlen($response->getContent()),
            ]
        );

        return $response;
    }
}

/**
 * IMPORTANT NOTES:
 * 
 * 1. INITIALIZATION IS KEY
 *    - Call Superlog::initializeRequest() at the start of request handling
 *    - This MUST happen before any logging
 *    - It creates a unique trace_id (UUID v4)
 * 
 * 2. USE PROPER LOGGING METHODS
 *    - Superlog::log()              - Generic logging
 *    - Superlog::logStartup()       - Request beginning
 *    - Superlog::logMiddlewareEnd() - Middleware completion with timing
 *    - Superlog::logDatabase()      - Database operations
 *    - Superlog::logHttpOut()       - Outbound HTTP calls
 *    - Superlog::logShutdown()      - Request completion
 * 
 * 3. AVOID COMMON MISTAKES
 *    - ✗ Don't use Log::info() directly (bypasses Superlog)
 *    - ✗ Don't skip initializeRequest()
 *    - ✗ Don't create multiple trace_ids per request
 *    - ✓ Do use Superlog facade consistently
 *    - ✓ Do initialize ONCE per request
 *    - ✓ Do pass trace_ids from external sources (for correlation)
 * 
 * 4. TRACE_ID AND REQ_SEQ
 *    - trace_id: Unique UUID per request (same throughout request lifecycle)
 *    - req_seq: Incremental counter per log line within a request
 *              Starts at 0000000001, increments to 0000000002, etc.
 * 
 * Expected log output:
 * [2025-10-20T10:47:15.123456Z] superlog.INFO: [abc-def-ghi:0000000001] [STARTUP] GET /api/users ...
 * [2025-10-20T10:47:15.234567Z] superlog.DEBUG: [abc-def-ghi:0000000002] [MIDDLEWARE] MyMiddleware processing ...
 * [2025-10-20T10:47:15.345678Z] superlog.INFO: [abc-def-ghi:0000000003] [MIDDLEWARE-END] MyMiddleware ...
 * 
 * Notice:
 *   - Same trace_id throughout: abc-def-ghi
 *   - Incrementing req_seq: 0000000001 → 0000000002 → 0000000003
 *   - ISO8601 timestamps
 *   - "superlog" channel (not "local")
 */