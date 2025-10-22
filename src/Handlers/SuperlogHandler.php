<?php

namespace Superlog\Handlers;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Handler\StreamHandler;
use Monolog\LogRecord;
use Monolog\Formatter\FormatterInterface;
use Superlog\Logger\StructuredLogger;

class SuperlogHandler extends AbstractProcessingHandler
{
    protected StructuredLogger $logger;
    protected ?StreamHandler $streamHandler = null;
    protected ?string $filePath = null;

    public function __construct(StructuredLogger $logger, $level = \Monolog\Level::Debug)
    {
        parent::__construct($level);
        $this->logger = $logger;
        
        // Initialize correlation context for non-HTTP contexts if needed
        $this->initializeNonHttpContextIfNeeded();
    }
    
    /**
     * Initialize correlation context for non-HTTP contexts if needed
     */
    protected function initializeNonHttpContextIfNeeded(): void
    {
        // Check if we're in a non-HTTP context
        $isNonHttpContext = app()->runningInConsole() || 
                           (isset($_SERVER['SCRIPT_NAME']) && str_contains($_SERVER['SCRIPT_NAME'], 'artisan'));
        
        // If we're in a non-HTTP context and non-HTTP context logging is enabled
        if ($isNonHttpContext && config('superlog.non_http_context.enabled', true)) {
            // Create a correlation context if it doesn't exist
            if (!app()->has('Superlog\Utils\CorrelationContext')) {
                $correlation = new \Superlog\Utils\CorrelationContext();
                
                // Generate a trace ID with optional prefix
                $traceId = \Ramsey\Uuid\Uuid::uuid4()->toString();
                if (config('superlog.non_http_context.prefix_trace_id', true)) {
                    $prefix = app()->runningInConsole() ? 'cli_' : 'job_';
                    $traceId = $prefix . $traceId;
                }
                
                $correlation->setTraceId($traceId);
                $correlation->setMethod('CLI');
                $correlation->setPath(implode(' ', $_SERVER['argv'] ?? ['unknown']));
                $correlation->setClientIp('127.0.0.1');
                
                app()->instance('Superlog\Utils\CorrelationContext', $correlation);
            }
        }
    }

    /**
     * Set a stream handler for writing logs to a file or stream
     */
    public function setStreamHandler(StreamHandler $handler, ?string $filePath = null): self
    {
        $this->streamHandler = $handler;
        $this->filePath = $filePath;
        return $this;
    }

    /**
     * Handle a log record
     */
    protected function write(LogRecord $record): void
    {
        $context = $record->context ?? [];
        
        // Check if this is already a formatted Superlog entry (passed from StructuredLogger)
        if (isset($context['_superlog_entry'])) {
            $logEntry = $context['_superlog_entry'];
        } else {
            // Fallback: construct from context (for non-Superlog logs)
            // Build the entry directly without calling log() to avoid circular logging
            $section = $context['section'] ?? 'GENERAL';
            $metrics = $context['metrics'] ?? [];
            
            // Remove Superlog-specific keys from context
            $cleanContext = $context;
            unset($cleanContext['section'], $cleanContext['metrics'], $cleanContext['_superlog_entry']);
            
            // Generate trace ID if not available
            $traceId = $context['trace_id'] ?? null;
            if (!$traceId) {
                // Try to get from the correlation context if available
                if (app()->has('Superlog\Utils\CorrelationContext')) {
                    $correlation = app()->make('Superlog\Utils\CorrelationContext');
                    $traceId = $correlation->getTraceId();
                } else {
                    // Generate a new UUID as trace ID
                    $traceId = \Ramsey\Uuid\Uuid::uuid4()->toString();
                }
            }
            
            // Generate span ID if not available
            $spanId = $context['span_id'] ?? null;
            if (!$spanId) {
                $spanId = \Ramsey\Uuid\Uuid::uuid4()->toString();
            }
            
            // Get request sequence number
            $reqSeq = $context['req_seq'] ?? '0';
            
            // Build log entry directly (mimics what StructuredLogger.log() does)
            $logEntry = [
                'level' => $record->level->getName(),
                'section' => $section,
                'message' => $record->message,
                'context' => $cleanContext,
                'metrics' => $metrics,
                'timestamp' => $record->datetime->format('Y-m-d\TH:i:s.uP'),
                'trace_id' => $traceId,
                'req_seq' => $reqSeq,
                'span_id' => $spanId,
            ];
            
            // Add correlation if available
            if (isset($context['correlation'])) {
                $logEntry['correlation'] = $context['correlation'];
            }
        }

        // Format and output
        if (!empty($logEntry)) {
            $formatted = $this->logger->formatLogEntry($logEntry);
            
            // Write directly to file to avoid duplicate processing
            if ($this->filePath) {
                // Ensure directory exists
                $dir = dirname($this->filePath);
                if (!is_dir($dir)) {
                    @mkdir($dir, 0755, true);
                }
                // Append to file
                file_put_contents($this->filePath, $formatted . "\n", FILE_APPEND);
            } else {
                // Fallback to echo if no file path
                echo $formatted . PHP_EOL;
            }
        }
    }
}