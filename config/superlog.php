<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Enable Superlog
    |--------------------------------------------------------------------------
    | Toggle the Superlog engine on/off globally
    */
    'enabled' => env('SUPERLOG_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Auto-capture requests
    |--------------------------------------------------------------------------
    | Automatically capture request lifecycle (startup, middleware, shutdown)
    */
    'auto_capture_requests' => env('SUPERLOG_AUTO_CAPTURE', true),

    /*
    |--------------------------------------------------------------------------
    | Default log channel
    |--------------------------------------------------------------------------
    | The logging channel to use (must be configured in config/logging.php)
    */
    'channel' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Log Level
    |--------------------------------------------------------------------------
    | The minimum log level to process
    */
    'level' => env('LOG_LEVEL', 'debug'),

    /*
    |--------------------------------------------------------------------------
    | Request ID Header
    |--------------------------------------------------------------------------
    | HTTP header to extract/set request trace ID
    */
    'trace_id_header' => env('SUPERLOG_TRACE_ID_HEADER', 'X-Trace-Id'),

    /*
    |--------------------------------------------------------------------------
    | Sampling Configuration
    |--------------------------------------------------------------------------
    | Sample noisy log levels (info/debug) at configurable rates
    */
    'sampling' => [
        'enabled' => env('SUPERLOG_SAMPLING_ENABLED', false),
        'info_rate' => 0.1,   // 10% of INFO logs
        'debug_rate' => 0.05, // 5% of DEBUG logs
    ],

    /*
    |--------------------------------------------------------------------------
    | Slow Request Detection
    |--------------------------------------------------------------------------
    | Automatically escalate diagnostics for requests exceeding threshold
    */
    'slow_request' => [
        'threshold_ms' => env('SUPERLOG_SLOW_REQUEST_MS', 750),
        'collect_queries' => true,
        'collect_cache_stats' => true,
        'collect_bindings_count' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | PII Redaction
    |--------------------------------------------------------------------------
    | Automatic masking of sensitive fields
    */
    'redaction' => [
        'enabled' => env('SUPERLOG_REDACTION_ENABLED', true),
        'mode' => 'mask', // 'mask' or 'remove'
        'mask_char' => '*',
        
        // Smart detection (built-in patterns)
        'smart_detection' => true,
        
        // Additional keys to redact (merged with smart defaults)
        'custom_keys' => explode(',', env('SUPERLOG_REDACT_KEYS', '')),
        
        // Patterns that trigger redaction
        'patterns' => [
            'password', 'passwd', 'pwd',
            'token', 'auth', 'authorization',
            'api_key', 'apikey', 'api-key',
            'secret', 'access_token', 'refresh_token',
            'credit_card', 'cc_number', 'cvv', 'cvc',
            'ssn', 'nif', 'iban', 'pan',
            'phone', 'mobile', 'telephone',
            'email', 'mail', 'username',
            'cookie', 'session',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Binary & File Handling
    |--------------------------------------------------------------------------
    | How to handle large or binary payloads
    */
    'payload_handling' => [
        'max_string_length' => 5000,
        'max_array_depth' => 10,
        'max_file_size_kb' => 1024,
        'summarize_uploads' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Async Shipping
    |--------------------------------------------------------------------------
    | Queue-based log shipping to external systems
    */
    'async_shipping' => [
        'enabled' => env('SUPERLOG_ASYNC_ENABLED', false),
        'queue' => env('SUPERLOG_QUEUE', 'default'),
        'batch_size' => 100,
        'batch_timeout_ms' => 5000,
    ],

    /*
    |--------------------------------------------------------------------------
    | External Handlers
    |--------------------------------------------------------------------------
    | Endpoints to ship logs to (e.g., ELK, OpenSearch, custom webhook)
    */
    'external_handlers' => [
        // Example:
        // [
        //     'driver' => 'http',
        //     'url' => env('SUPERLOG_HTTP_URL', 'http://localhost:9200'),
        //     'auth' => [
        //         'username' => env('SUPERLOG_HTTP_USER'),
        //         'password' => env('SUPERLOG_HTTP_PASS'),
        //     ],
        //     'timeout' => 10,
        //     'retry_count' => 3,
        // ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Sections Configuration
    |--------------------------------------------------------------------------
    | Control which sections are logged and their behavior
    */
    'sections' => [
        'startup' => [
            'enabled' => true,
            'capture_user_agent' => true,
            'capture_headers' => false,
            'capture_query_string' => true,
        ],
        'middleware' => [
            'enabled' => true,
            'include_all' => false,
            'whitelist' => [],
            'blacklist' => ['Start', 'DebugBarMiddleware'],
        ],
        'database' => [
            'enabled' => true,
            'capture_bindings' => false, // Security: set to true only in dev
            'slow_query_threshold_ms' => 100,
        ],
        'cache' => [
            'enabled' => true,
        ],
        'http_outbound' => [
            'enabled' => true,
            'capture_request_body' => false,
            'capture_response_body' => false,
        ],
        'shutdown' => [
            'enabled' => true,
            'capture_memory_peak' => true,
            'capture_included_files_count' => true,
            'capture_opcache_status' => env('APP_ENV') === 'local',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Options
    |--------------------------------------------------------------------------
    | Tune performance and resource usage
    */
    'performance' => [
        'defer_heavy_collectors' => true,
        'enable_profiling' => env('SUPERLOG_PROFILING', false),
        'max_backtrace_depth' => 5,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Model Query Logging
    |--------------------------------------------------------------------------
    | Track and log Eloquent model operations with their SQL queries
    */
    'model_query_logging' => [
        'enabled' => env('SUPERLOG_MODEL_QUERY_LOGGING', true),
        'log_level' => env('SUPERLOG_MODEL_QUERY_LOG_LEVEL', 'debug'),
        'include_bindings' => env('SUPERLOG_MODEL_QUERY_INCLUDE_BINDINGS', true),
        'slow_query_threshold_ms' => env('SUPERLOG_MODEL_SLOW_QUERY_MS', 100),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Non-HTTP Context Logging
    |--------------------------------------------------------------------------
    | Configuration for logging in non-HTTP contexts (CLI, queue jobs, webhooks)
    */
    'non_http_context' => [
        'enabled' => env('SUPERLOG_NON_HTTP_CONTEXT_ENABLED', true),
        'generate_trace_id' => true,
        'prefix_trace_id' => true, // Adds 'cli_', 'job_', etc. prefix to trace IDs
    ],

    /*
    |--------------------------------------------------------------------------
    | Output Format
    |--------------------------------------------------------------------------
    | JSON or text output
    */
    'format' => env('SUPERLOG_FORMAT', 'json'), // 'json' or 'text'
];