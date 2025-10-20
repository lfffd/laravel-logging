<?php

namespace Superlog\Processors;

class RedactionProcessor
{
    protected array $config;
    protected array $patterns;
    protected string $maskChar;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->maskChar = $config['mask_char'] ?? '*';
        $this->patterns = array_merge(
            $config['patterns'] ?? [],
            $config['custom_keys'] ?? []
        );
    }

    /**
     * Process and redact sensitive data from log entry
     */
    public function process(array $entry): array
    {
        if (!$this->config['enabled']) {
            return $entry;
        }

        // Redact context
        if (isset($entry['context']) && is_array($entry['context'])) {
            $entry['context'] = $this->redactArray($entry['context']);
        }

        // Redact metrics
        if (isset($entry['metrics']) && is_array($entry['metrics'])) {
            $entry['metrics'] = $this->redactArray($entry['metrics']);
        }

        // Redact correlation
        if (isset($entry['correlation']) && is_array($entry['correlation'])) {
            $entry['correlation'] = $this->redactArray($entry['correlation']);
        }

        return $entry;
    }

    /**
     * Recursively redact sensitive values in an array
     */
    protected function redactArray(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if ($this->isSensitiveKey($key)) {
                $result[$key] = $this->maskValue($value);
            } elseif (is_array($value)) {
                $result[$key] = $this->redactArray($value);
            } elseif (is_string($value) && $this->isSensitiveValue($value)) {
                $result[$key] = $this->maskValue($value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Check if a key matches sensitive patterns
     */
    protected function isSensitiveKey(string $key): bool
    {
        $lowerKey = strtolower($key);

        foreach ($this->patterns as $pattern) {
            if (stripos($lowerKey, strtolower($pattern)) !== false) {
                return true;
            }
        }

        // Smart detection for common patterns
        if ($this->config['smart_detection']) {
            if (preg_match('/^(password|passwd|pwd|secret|token|auth|api_key|key)/', $lowerKey)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a value looks sensitive (email, IP in cookie, etc.)
     */
    protected function isSensitiveValue(string $value): bool
    {
        if (!$this->config['smart_detection']) {
            return false;
        }

        // Email pattern
        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return true;
        }

        // Credit card pattern (16+ digits)
        if (preg_match('/^\d{13,19}$/', str_replace(' ', '', $value))) {
            return true;
        }

        // Bearer token pattern
        if (preg_match('/^Bearer\s+[a-zA-Z0-9\-_.]+/', $value)) {
            return true;
        }

        return false;
    }

    /**
     * Mask a sensitive value
     */
    protected function maskValue($value): string
    {
        if (!is_string($value)) {
            return $this->maskChar;
        }

        if (strlen($value) <= 2) {
            return str_repeat($this->maskChar, strlen($value));
        }

        $mode = $this->config['mode'] ?? 'mask';

        if ($mode === 'remove') {
            return '[REDACTED]';
        }

        // Mask: show first and last char
        $first = substr($value, 0, 1);
        $last = substr($value, -1);
        $masked = str_repeat($this->maskChar, strlen($value) - 2);

        return $first . $masked . $last;
    }
}