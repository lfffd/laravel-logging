<?php

namespace Superlog\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void initializeRequest(string $method, string $path, string $ip, ?string $traceId = null)
 * @method static array log(string $level, string $section, string $message, array $context = [], array $metrics = [])
 * @method static array logStartup(array $requestData)
 * @method static array logMiddlewareStart(string $middlewareName, string $requestId)
 * @method static array logMiddlewareEnd(string $middlewareName, float $durationMs, int $responseStatus, array $extraMetrics = [])
 * @method static array logDatabase(array $dbStats)
 * @method static array logHttpOut(string $method, string $url, int $status, float $durationMs, array $extraMetrics = [])
 * @method static array logCache(array $cacheStats)
 * @method static array logShutdown(array $shutdownData)
 * @method static \Superlog\Utils\CorrelationContext getCorrelation()
 * @method static string formatLogEntry(array $entry)
 *
 * @see \Superlog\Logger\StructuredLogger
 */
class Superlog extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Superlog\Logger\StructuredLogger::class;
    }
}