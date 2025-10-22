<?php

/**
 * Example of using Superlog's Model Query Logging feature
 * 
 * This feature automatically logs Eloquent model operations with the tag format:
 * [MODEL/$model_name/$verb]
 * 
 * Where:
 * - $model_name is the class name of the model (e.g., User, Product)
 * - $verb is the operation (select, insert, update, delete, etc.)
 * 
 * Each log entry includes:
 * - The SQL query executed
 * - Time to execute (in milliseconds)
 * - Number of records affected/retrieved
 * - Error status (if applicable)
 */

// The feature is enabled by default in config/superlog.php
// You can disable it by setting:
// 'enabled' => false in the 'model_query_logging' section

// Example 1: Basic model operations are automatically logged
$user = User::find(1);
// Logs: [MODEL/User/SELECT] with the SQL query, execution time, etc.

$user->name = 'New Name';
$user->save();
// Logs: [MODEL/User/UPDATE] with the SQL query, execution time, etc.

// Example 2: Bulk operations are also logged
User::where('status', 'inactive')->delete();
// Logs: [MODEL/User/DELETE] with the SQL query, execution time, etc.

// Example 3: Query builder operations through models are logged
$activeUsers = User::where('status', 'active')
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get();
// Logs: [MODEL/User/SELECT] with the SQL query, execution time, etc.

// Example 4: Relationship operations are logged
$user = User::find(1);
$posts = $user->posts()->where('published', true)->get();
// Logs: [MODEL/Post/SELECT] with the SQL query, execution time, etc.

// Example 5: Errors are logged at error level
try {
    // Attempting an operation that would cause an error
    User::create([
        'email' => 'duplicate@example.com', // Assuming this is a duplicate
    ]);
} catch (\Exception $e) {
    // The error will be automatically logged with [MODEL/User/INSERT]
    // at error level with the exception details
}

/**
 * Configuration Options
 * 
 * In config/superlog.php:
 * 
 * 'model_query_logging' => [
 *     'enabled' => env('SUPERLOG_MODEL_QUERY_LOGGING', true),
 *     'log_level' => env('SUPERLOG_MODEL_QUERY_LOG_LEVEL', 'debug'),
 *     'include_bindings' => env('SUPERLOG_MODEL_QUERY_INCLUDE_BINDINGS', true),
 *     'slow_query_threshold_ms' => env('SUPERLOG_MODEL_SLOW_QUERY_MS', 100),
 * ],
 * 
 * Environment variables:
 * SUPERLOG_MODEL_QUERY_LOGGING=true|false
 * SUPERLOG_MODEL_QUERY_LOG_LEVEL=debug|info|notice|warning|error
 * SUPERLOG_MODEL_QUERY_INCLUDE_BINDINGS=true|false
 * SUPERLOG_MODEL_SLOW_QUERY_MS=100
 */