<?php

namespace Superlog\Handlers;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use Superlog\Jobs\ShipLogsJob;

class ExternalLogHandler extends AbstractProcessingHandler
{
    protected array $batch = [];
    protected int $batchSize;
    protected float $batchTimeout;
    protected float $lastFlush;

    public function __construct($level = \Monolog\Level::Debug)
    {
        parent::__construct($level);
        $this->batchSize = config('superlog.async_shipping.batch_size', 100);
        $this->batchTimeout = config('superlog.async_shipping.batch_timeout_ms', 5000) / 1000; // Convert to seconds
        $this->lastFlush = microtime(true);
    }

    /**
     * Handle a log record
     */
    protected function write(LogRecord $record): void
    {
        $this->batch[] = $record->toArray();

        // Check if we should flush
        if ($this->shouldFlush()) {
            $this->flush();
        }
    }

    /**
     * Determine if batch should be flushed
     */
    protected function shouldFlush(): bool
    {
        // Flush if batch is full
        if (count($this->batch) >= $this->batchSize) {
            return true;
        }

        // Flush if timeout exceeded
        if ((microtime(true) - $this->lastFlush) >= $this->batchTimeout) {
            return true;
        }

        return false;
    }

    /**
     * Flush current batch
     */
    public function flush(): void
    {
        if (empty($this->batch)) {
            return;
        }

        // Queue job for async shipping
        ShipLogsJob::dispatch($this->batch);

        $this->batch = [];
        $this->lastFlush = microtime(true);
    }

    /**
     * Destructor to ensure remaining logs are flushed
     */
    public function __destruct()
    {
        $this->flush();
    }
}