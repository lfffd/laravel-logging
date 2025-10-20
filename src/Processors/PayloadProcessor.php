<?php

namespace Superlog\Processors;

class PayloadProcessor
{
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Process and normalize payloads in log entry
     */
    public function process(array $entry): array
    {
        if (isset($entry['context'])) {
            $entry['context'] = $this->processValue($entry['context']);
        }

        if (isset($entry['metrics'])) {
            $entry['metrics'] = $this->processValue($entry['metrics']);
        }

        return $entry;
    }

    /**
     * Recursively process values, truncating large data
     */
    protected function processValue($value, int $depth = 0)
    {
        $maxDepth = $this->config['max_array_depth'] ?? 10;

        if ($depth > $maxDepth) {
            return '[MAX_DEPTH_REACHED]';
        }

        if (is_string($value)) {
            return $this->truncateString($value);
        }

        if (is_array($value)) {
            $result = [];
            foreach ($value as $k => $v) {
                $result[$k] = $this->processValue($v, $depth + 1);
            }
            return $result;
        }

        if (is_resource($value)) {
            return '[RESOURCE]';
        }

        if ($value instanceof \SplFileInfo || $value instanceof \Traversable) {
            return $this->summarizeFile($value);
        }

        return $value;
    }

    /**
     * Truncate overly long strings
     */
    protected function truncateString(string $value): string
    {
        $maxLength = $this->config['max_string_length'] ?? 5000;

        if (strlen($value) > $maxLength) {
            return substr($value, 0, $maxLength) . '[TRUNCATED]';
        }

        return $value;
    }

    /**
     * Summarize file information instead of dumping content
     */
    protected function summarizeFile($file): array
    {
        if ($file instanceof \SplFileInfo) {
            return [
                'type' => 'file',
                'name' => $file->getFilename(),
                'size_kb' => round($file->getSize() / 1024, 2),
                'mime' => $this->getMimeType($file->getPathname()),
            ];
        }

        return ['type' => 'unknown_resource'];
    }

    /**
     * Get MIME type of file
     */
    protected function getMimeType(string $path): ?string
    {
        if (function_exists('mime_content_type')) {
            return mime_content_type($path);
        }

        if (function_exists('finfo_file')) {
            return finfo_file(finfo_open(FILEINFO_MIME_TYPE), $path);
        }

        return null;
    }
}