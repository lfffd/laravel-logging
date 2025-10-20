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

    public function __construct(StructuredLogger $logger, $level = \Monolog\Level::Debug)
    {
        parent::__construct($level);
        $this->logger = $logger;
    }

    /**
     * Set a stream handler for writing logs to a file or stream
     */
    public function setStreamHandler(StreamHandler $handler): self
    {
        $this->streamHandler = $handler;
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
            $section = $context['section'] ?? 'GENERAL';
            $metrics = $context['metrics'] ?? [];
            
            // Remove Superlog-specific keys from context
            $cleanContext = $context;
            unset($cleanContext['section'], $cleanContext['metrics'], $cleanContext['_superlog_entry']);
            
            $logEntry = $this->logger->log(
                $record->level->getName(),
                $section,
                $record->message,
                $cleanContext,
                $metrics
            );
        }

        // Format and output
        if (!empty($logEntry)) {
            $formatted = $this->logger->formatLogEntry($logEntry);
            
            // Write to stream handler if available
            if ($this->streamHandler) {
                // Write directly to the stream to avoid duplicate processing through the handler pipeline
                fwrite($this->streamHandler->getStream(), $formatted . "\n");
            } else {
                // Fallback to echo if no stream handler
                echo $formatted . PHP_EOL;
            }
        }
    }
}