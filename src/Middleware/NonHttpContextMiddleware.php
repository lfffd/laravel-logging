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
        // Create a correlation context if it doesn't exist
        if (!app()->has(CorrelationContext::class)) {
            $correlation = new CorrelationContext();
            app()->instance(CorrelationContext::class, $correlation);
        } else {
            $correlation = app()->make(CorrelationContext::class);
        }
        
        // Generate a trace ID if one doesn't exist
        if (!$correlation->getTraceId()) {
            // Generate a trace ID with a prefix to identify the context type
            $prefix = app()->runningInConsole() ? 'cli' : 'job';
            $traceId = $prefix . '_' . Str::uuid()->toString();
            $correlation->setTraceId($traceId);
        }
        
        // Set some basic context information
        $correlation->setMethod('CLI');
        $correlation->setPath(implode(' ', $_SERVER['argv'] ?? ['unknown']));
        $correlation->setClientIp('127.0.0.1');
    }
}