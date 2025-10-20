<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Superlog Configuration
    |--------------------------------------------------------------------------
    |
    | This file is for configuring Superlog, a structured logging solution
    | for Laravel applications. It provides advanced features for tracking
    | requests, managing log levels, and formatting log output.
    |
    */

    'enabled' => env('SUPERLOG_ENABLED', true),

    'channel' => env('SUPERLOG_CHANNEL', 'superlog'),

    'format' => env('SUPERLOG_FORMAT', 'text'),

    'formats' => [
        'text' => [
            'separator' => ' | ',
            'include_timestamp' => true,
            'include_level' => true,
            'include_channel' => true,
        ],
        'json' => [
            'pretty_print' => false,
        ],
    ],

    'min_log_level' => env('SUPERLOG_MIN_LOG_LEVEL', 'debug'),

    'context' => [
        'include_context' => env('SUPERLOG_INCLUDE_CONTEXT', true),
        'include_stack_trace' => env('SUPERLOG_INCLUDE_STACK_TRACE', false),
        'trace_id_enabled' => true,
        'request_sequence_enabled' => true,
    ],

    'output' => [
        'path' => storage_path('logs'),
        'filename_pattern' => 'superlog-{date}.log',
        'max_file_size' => '100MB',
        'max_files' => 10,
    ],

    'processors' => [
        \Superlog\Processors\TraceIdProcessor::class,
        \Superlog\Processors\RequestSequenceProcessor::class,
    ],

    'middleware' => [
        'enabled' => true,
        'except_paths' => [
            '/health',
            '/up',
        ],
    ],
];