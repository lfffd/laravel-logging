<?php

/**
 * Example of using Superlog in non-HTTP contexts like CLI commands, queue jobs, and webhooks
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Superlog\Facades\Superlog;

class ProcessDataCommand extends Command
{
    protected $signature = 'app:process-data';
    protected $description = 'Process data with Superlog tracing';

    public function handle()
    {
        // Superlog will automatically generate a trace ID for CLI commands
        // The trace ID will have a 'cli_' prefix by default
        
        // Log the start of processing
        Superlog::log('info', 'PROCESS', 'Starting data processing', [
            'command' => $this->signature,
            'arguments' => $this->arguments(),
            'options' => $this->options(),
        ]);
        
        // Simulate some processing
        $this->processItems();
        
        // Log the completion
        Superlog::log('info', 'PROCESS', 'Data processing completed', [], [
            'duration_ms' => 1250,
            'items_processed' => 100,
        ]);
        
        // All logs will have the same trace ID, making it easy to track the entire process
        
        return 0;
    }
    
    protected function processItems()
    {
        // Simulate processing items
        for ($i = 0; $i < 5; $i++) {
            // Each log entry will share the same trace ID
            Superlog::log('debug', 'ITEM_PROCESS', "Processing item $i", [
                'item_id' => $i,
                'status' => 'processing',
            ]);
            
            // Simulate some work
            usleep(100000); // 100ms
            
            Superlog::log('debug', 'ITEM_PROCESS', "Completed item $i", [
                'item_id' => $i,
                'status' => 'completed',
            ]);
        }
    }
}

/**
 * Example of using Superlog in a queue job
 */

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Superlog\Facades\Superlog;

class ProcessWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $webhookData;

    public function __construct(array $webhookData)
    {
        $this->webhookData = $webhookData;
    }

    public function handle()
    {
        // Superlog will automatically generate a trace ID for queue jobs
        // The trace ID will have a 'job_' prefix by default
        
        // Log the start of webhook processing
        Superlog::log('info', 'WEBHOOK', 'Processing webhook', [
            'webhook_id' => $this->webhookData['id'] ?? 'unknown',
            'webhook_type' => $this->webhookData['type'] ?? 'unknown',
        ]);
        
        // Process the webhook data
        // ...
        
        // Log the completion
        Superlog::log('info', 'WEBHOOK', 'Webhook processed successfully', [], [
            'duration_ms' => 350,
        ]);
        
        // All logs will have the same trace ID, making it easy to track the entire webhook processing
    }
}

/**
 * Example output:
 * 
 * [2025-10-22T14:15:23.456789+00:00] superlog.INFO: [cli_a1b2c3d4-e5f6-7890-1234-567890abcdef:0000000001] PROCESS Starting data processing {"command":"app:process-data","arguments":[],"options":[]}
 * [2025-10-22T14:15:23.556789+00:00] superlog.DEBUG: [cli_a1b2c3d4-e5f6-7890-1234-567890abcdef:0000000002] ITEM_PROCESS Processing item 0 {"item_id":0,"status":"processing"}
 * [2025-10-22T14:15:23.656789+00:00] superlog.DEBUG: [cli_a1b2c3d4-e5f6-7890-1234-567890abcdef:0000000003] ITEM_PROCESS Completed item 0 {"item_id":0,"status":"completed"}
 * ...
 * [2025-10-22T14:15:24.706789+00:00] superlog.INFO: [cli_a1b2c3d4-e5f6-7890-1234-567890abcdef:0000000011] PROCESS Data processing completed {"duration_ms":1250,"items_processed":100}
 */