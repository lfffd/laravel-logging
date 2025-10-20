<?php

namespace Superlog\Handlers;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use Superlog\Logger\StructuredLogger;

class SuperlogHandler extends AbstractProcessingHandler
{
    protected StructuredLogger $logger;

    public function __construct(StructuredLogger $logger, $level = \Monolog\Level::Debug)
    {
        parent::__construct($level);
        $this->logger = $logger;
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
            // Output will be handled by the stream handler or other handlers in the stack
            echo $formatted . PHP_EOL;
        }
    }
}