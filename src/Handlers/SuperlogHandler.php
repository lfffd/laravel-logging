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
            
            // Build log entry directly (mimics what StructuredLogger.log() does)
            $logEntry = [
                'level' => $record->level->getName(),
                'section' => $section,
                'message' => $record->message,
                'context' => $cleanContext,
                'metrics' => $metrics,
                'timestamp' => $record->datetime->format('Y-m-d\TH:i:s.uP'),
                'trace_id' => $context['trace_id'] ?? 'unknown',
                'req_seq' => $context['req_seq'] ?? '0',
                'span_id' => $context['span_id'] ?? 'unknown',
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