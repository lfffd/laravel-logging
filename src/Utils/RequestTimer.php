<?php

namespace Superlog\Utils;

class RequestTimer
{
    protected float $startTime;

    public function __construct()
    {
        $this->startTime = microtime(true);
    }

    /**
     * Get elapsed time in milliseconds
     */
    public function elapsed(): float
    {
        return (microtime(true) - $this->startTime) * 1000;
    }

    /**
     * Get elapsed time in seconds
     */
    public function elapsedSeconds(): float
    {
        return microtime(true) - $this->startTime;
    }

    /**
     * Reset timer
     */
    public function reset(): self
    {
        $this->startTime = microtime(true);
        return $this;
    }
}