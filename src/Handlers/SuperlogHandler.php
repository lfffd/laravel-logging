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
        $context = $record->extra ?? [];
        
        // Merge context and context array
        if (isset($record->context)) {
            $context = array_merge($context, $record->context);
        }

        // Extract section from context if available
        $section = $context['section'] ?? 'GENERAL';
        unset($context['section']);

        // Extract metrics if available
        $metrics = $context['metrics'] ?? [];
        unset($context['metrics']);

        // Log using structured logger
        $logEntry = $this->logger->log(
            $record->level->getName(),
            $section,
            $record->message,
            $context,
            $metrics
        );

        // Format and output
        if (!empty($logEntry)) {
            $formatted = $this->logger->formatLogEntry($logEntry);
            
            // Write to stream handler if available
            if ($this->streamHandler) {
                $this->streamHandler->write(
                    new LogRecord(
                        $record->datetime,
                        $record->channel,
                        $record->level,
                        $formatted,
                        $record->extra,
                        $record->context
                    )
                );
            } else {
                // Fallback to echo if no stream handler
                echo $formatted . PHP_EOL;
            }
        }
    }
}