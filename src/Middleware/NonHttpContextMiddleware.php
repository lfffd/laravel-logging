<?php

namespace Superlog\Middleware;

use Closure;
use Illuminate\Support\Str;
use Superlog\Utils\CorrelationContext;

class NonHttpContextMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // Check if we're in a non-HTTP context (webhook, queue job, console)
        $isNonHttpContext = app()->runningInConsole() || 
                           (isset($_SERVER['SCRIPT_NAME']) && Str::contains($_SERVER['SCRIPT_NAME'], 'artisan'));
        
        // If we're in a non-HTTP context, ensure we have a correlation context with trace ID
        if ($isNonHttpContext) {
            $this->initializeNonHttpContext();
        }
        
        return $next($request);
    }
    
    /**
     * Initialize correlation context for non-HTTP contexts.
     *
     * @return void
     */
    protected function initializeNonHttpContext()
    {
        // Get the existing correlation context (already registered as a singleton)
        $correlation = app()->make(CorrelationContext::class);
        
        // The trace ID should already be set in the register method of SuperlogServiceProvider
        // But we'll check just in case
        if (!$correlation->getTraceId()) {
            // Generate a temporary trace ID until we can get the real one from the session
            $traceId = 'tmp/' . Str::uuid()->toString();
            $correlation->setTraceId($traceId);
        }
        
        // Update context information if needed
        if ($correlation->getMethod() === 'UNKNOWN') {
            $correlation->setMethod('CLI');
        }
        
        if ($correlation->getPath() === '/') {
            $correlation->setPath(implode(' ', $_SERVER['argv'] ?? ['unknown']));
        }
        
        if ($correlation->getClientIp() === 'UNKNOWN') {
            $correlation->setClientIp('127.0.0.1');
        }
    }
}