<?php

namespace Superlog\Utils;

use Ramsey\Uuid\Uuid;

class CorrelationContext
{
    // Static trace ID to ensure consistency across the application
    protected static ?string $staticTraceId = null;
    protected static ?string $tempTraceId = null;
    protected static ?string $replacedTempTraceId = null; // Track the temp ID that was replaced
    
    protected string $method;
    protected string $path;
    protected string $clientIp;
    protected array $spanIds = [];
    protected ?\DateTimeImmutable $startTime = null;

    public function __construct()
    {
        $this->startTime = new \DateTimeImmutable();
    }

    /**
     * Set trace ID (UUID v4)
     */
    public function setTraceId(string $traceId): self
    {
        // If this is a temporary ID (prefixed with tmp/), store it separately
        if (strpos($traceId, 'tmp/') === 0) {
            self::$tempTraceId = $traceId;
        } else {
            self::$staticTraceId = $traceId;
            
            // If we had a temporary ID before, track it as replaced
            if (self::$tempTraceId) {
                self::$replacedTempTraceId = self::$tempTraceId;
                \Log::info("Session identification from " . self::$tempTraceId);
                self::$tempTraceId = null;
            }
        }
        
        return $this;
    }

    /**
     * Get trace ID
     */
    public function getTraceId(): string
    {
        // If we have a permanent trace ID, use it
        if (self::$staticTraceId) {
            return self::$staticTraceId;
        }
        
        // If we have a temporary trace ID, use it
        if (self::$tempTraceId) {
            return self::$tempTraceId;
        }
        
        // Generate a new temporary trace ID
        self::$tempTraceId = 'tmp/' . Uuid::uuid4()->toString();
        return self::$tempTraceId;
    }

    /**
     * Get the temporary trace ID that was replaced with a permanent one
     */
    public function getReplacedTempTraceId(): ?string
    {
        return self::$replacedTempTraceId;
    }

    /**
     * Set HTTP method
     */
    public function setMethod(string $method): self
    {
        $this->method = $method;
        return $this;
    }

    /**
     * Get HTTP method
     */
    public function getMethod(): string
    {
        return $this->method ?? 'UNKNOWN';
    }

    /**
     * Set request path
     */
    public function setPath(string $path): self
    {
        $this->path = $path;
        return $this;
    }

    /**
     * Get request path
     */
    public function getPath(): string
    {
        return $this->path ?? '/';
    }

    /**
     * Set client IP
     */
    public function setClientIp(string $ip): self
    {
        $this->clientIp = $ip;
        return $this;
    }

    /**
     * Get client IP
     */
    public function getClientIp(): string
    {
        return $this->clientIp ?? 'UNKNOWN';
    }

    /**
     * Get or create span ID for a section
     */
    public function getOrCreateSpanId(string $section): string
    {
        if (!isset($this->spanIds[$section])) {
            $this->spanIds[$section] = Uuid::uuid4()->toString();
        }
        return $this->spanIds[$section];
    }

    /**
     * Get request duration in milliseconds
     */
    public function getDurationMs(): float
    {
        if (!$this->startTime) {
            return 0;
        }
        $now = new \DateTimeImmutable();
        $interval = $now->diff($this->startTime);
        return ($interval->f * 1000) + ($interval->s * 1000);
    }

    /**
     * Convert to array for serialization
     */
    public function toArray(): array
    {
        return [
            'trace_id' => $this->getTraceId(),
            'method' => $this->getMethod(),
            'path' => $this->getPath(),
            'client_ip' => $this->getClientIp(),
            'request_duration_ms' => $this->getDurationMs(),
        ];
    }
}