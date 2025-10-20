<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Superlog\Facades\Superlog;

class DemoController extends Controller
{
    /**
     * Show the demo home page
     */
    public function index()
    {
        Log::info('Demo home page accessed');

        $demos = [
            [
                'name' => 'Basic Log',
                'url' => route('demo.log'),
                'description' => 'Test basic logging functionality',
            ],
            [
                'name' => 'Error Handling',
                'url' => route('demo.error'),
                'description' => 'Test error and exception logging',
            ],
            [
                'name' => 'Context Data',
                'url' => route('demo.context'),
                'description' => 'Test logging with context information',
            ],
            [
                'name' => 'Multiple Logs',
                'url' => route('demo.multiple'),
                'description' => 'Test multiple sequential log entries',
            ],
            [
                'name' => 'JSON Format',
                'url' => route('demo.json'),
                'description' => 'Test JSON formatted logs',
            ],
        ];

        return view('demo.index', compact('demos'));
    }

    /**
     * Test basic logging
     */
    public function testLog()
    {
        Log::debug('Debug message from demo');
        Log::info('Info message from demo');
        Log::notice('Notice message from demo');
        Log::warning('Warning message from demo');

        return response()->json([
            'status' => 'success',
            'message' => 'Basic logging test completed',
            'log_file' => storage_path('logs'),
        ]);
    }

    /**
     * Test error logging
     */
    public function testError()
    {
        try {
            Log::warning('About to trigger a warning');
            Log::error('An error occurred during processing');

            return response()->json([
                'status' => 'success',
                'message' => 'Error logging test completed',
            ]);
        } catch (\Exception $e) {
            Log::error('Exception caught', [
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test logging with context
     */
    public function testContext()
    {
        $userId = 42;
        $userName = 'John Doe';
        $action = 'user_login';

        Log::info('User action recorded', [
            'user_id' => $userId,
            'user_name' => $userName,
            'action' => $action,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->toIso8601String(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Context logging test completed',
            'context' => [
                'user_id' => $userId,
                'action' => $action,
            ],
        ]);
    }

    /**
     * Test multiple sequential logs
     */
    public function testMultiple()
    {
        Log::info('Step 1: Processing started');
        Log::debug('Step 2: Validating input data', ['data' => 'sample']);
        Log::info('Step 3: Data validated successfully');
        Log::debug('Step 4: Saving to database');
        Log::info('Step 5: Data saved');
        Log::info('Step 6: Processing completed');

        return response()->json([
            'status' => 'success',
            'message' => 'Multiple log entries test completed',
            'entries_logged' => 6,
        ]);
    }

    /**
     * Test JSON format logging
     */
    public function testJson()
    {
        $data = [
            'request_id' => uniqid('req_'),
            'service' => 'demo_service',
            'operation' => 'test_operation',
            'status' => 'success',
            'duration_ms' => 234.5,
            'items_processed' => 100,
        ];

        Log::info('JSON formatted log entry', $data);

        return response()->json([
            'status' => 'success',
            'message' => 'JSON logging test completed',
            'logged_data' => $data,
        ]);
    }
}