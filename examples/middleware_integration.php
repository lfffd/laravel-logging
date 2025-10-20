<?php

/**
 * Example: Custom Middleware Integration with Superlog
 * 
 * This example shows how to capture custom middleware timing
 * and log it with Superlog
 */

namespace Examples;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Superlog\Facades\Superlog;
use Superlog\Utils\RequestTimer;

class CustomMiddlewareWithSuperlog
{
    /**
     * Handle an incoming request
     */
    public function handle(Request $request, Closure $next): Response
    {
        $timer = new RequestTimer();

        // Your middleware logic here...
        $response = $next($request);

        $duration = $timer->elapsed();

        // Log this middleware with Superlog
        Superlog::logMiddlewareEnd(
            'CustomMiddleware',
            $duration,
            $response->getStatusCode(),
            [
                'processed_items' => 5,
                'cache_hit' => true,
            ]
        );

        return $response;
    }
}

/**
 * Example: Custom Section Logging
 */
class CustomSectionExample
{
    public static function logPaymentProcessing()
    {
        $timer = new RequestTimer();

        try {
            // Payment processing logic
            $result = $this->processPayment();
            $duration = $timer->elapsed();

            Superlog::log('info', 'PAYMENT_PROCESSING', 'Payment processed successfully', [
                'payment_id' => $result['id'],
                'amount' => $result['amount'],
            ], [
                'duration_ms' => $duration,
                'gateway' => 'stripe',
                'status' => 'approved',
            ]);

        } catch (\Exception $e) {
            $duration = $timer->elapsed();

            Superlog::log('error', 'PAYMENT_ERROR', 'Payment processing failed', [
                'error' => $e->getMessage(),
            ], [
                'duration_ms' => $duration,
                'gateway' => 'stripe',
                'status' => 'failed',
            ]);

            throw $e;
        }
    }
}

/**
 * Example: Correlation Propagation to External Services
 */
class ExternalServiceCall
{
    public static function callExternalAPI()
    {
        $correlation = Superlog::getCorrelation();

        $client = \Http::withHeaders([
            'X-Trace-Id' => $correlation->getTraceId(),
            'X-Request-Path' => $correlation->getPath(),
        ]);

        $timer = new RequestTimer();

        try {
            $response = $client->get('https://api.example.com/data');
            $duration = $timer->elapsed();

            Superlog::logHttpOut(
                'GET',
                'https://api.example.com/data',
                $response->status(),
                $duration,
                [
                    'response_size_kb' => strlen($response->body()) / 1024,
                    'timeout' => false,
                ]
            );

            return $response;

        } catch (\Exception $e) {
            $duration = $timer->elapsed();

            Superlog::log('error', 'API_CALL_FAILED', 'External API call failed', [
                'url' => 'https://api.example.com/data',
                'error' => $e->getMessage(),
            ], [
                'duration_ms' => $duration,
                'attempt' => 1,
            ]);

            throw $e;
        }
    }
}

/**
 * Example: Database Query Monitoring
 */
class DatabaseMonitoring
{
    public static function captureQueryStats()
    {
        \DB::enableQueryLog();

        // Your database queries
        $users = \User::all();
        $posts = \Post::all();

        $queries = \DB::getQueryLog();
        \DB::disableQueryLog();

        // Calculate metrics
        $totalTime = 0;
        $slowQueries = [];

        foreach ($queries as $query) {
            $totalTime += $query['time'];
            if ($query['time'] > 100) { // More than 100ms
                $slowQueries[] = [
                    'query' => $query['query'],
                    'time' => $query['time'],
                ];
            }
        }

        Superlog::logDatabase([
            'query_count' => count($queries),
            'total_query_ms' => $totalTime,
            'slowest_query_ms' => max(array_column($queries, 'time')),
            'slow_queries' => $slowQueries,
        ]);
    }
}

/**
 * Example: Cache Performance Tracking
 */
class CacheTracking
{
    public static function trackCacheOperations()
    {
        $stats = [
            'hits' => 0,
            'misses' => 0,
            'sets' => 0,
        ];

        $timer = new RequestTimer();

        // Simulate cache operations
        for ($i = 0; $i < 100; $i++) {
            $key = "cache_key_$i";
            $value = \Cache::get($key);

            if ($value) {
                $stats['hits']++;
            } else {
                $stats['misses']++;
                \Cache::set($key, "value_$i", 3600);
                $stats['sets']++;
            }
        }

        $duration = $timer->elapsed();

        Superlog::logCache([
            'hits' => $stats['hits'],
            'misses' => $stats['misses'],
            'sets' => $stats['sets'],
            'duration_ms' => $duration,
        ]);
    }
}

/**
 * Example: Manual Request Initialization (for CLI/Jobs)
 */
class QueueJobLogging
{
    public static function processQueueJob()
    {
        // Initialize manual request context for queue job
        Superlog::initializeRequest(
            'QUEUE',
            'ProcessOrderJob',
            '127.0.0.1',
            null // New trace ID will be generated
        );

        $timer = new RequestTimer();

        try {
            // Job logic here...
            \Log::info('Processing order', ['section' => 'QUEUE_JOB']);

            $duration = $timer->elapsed();

            Superlog::logShutdown([
                'request_ms' => $duration,
                'response_status' => 200,
                'response_bytes' => 0,
                'queue_jobs_dispatched' => 2,
            ]);

        } catch (\Exception $e) {
            Superlog::log('error', 'QUEUE_ERROR', 'Queue job failed', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}