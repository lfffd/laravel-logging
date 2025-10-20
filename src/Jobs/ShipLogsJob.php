<?php

namespace Superlog\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Superlog\Handlers\ExternalLogHandler;

class ShipLogsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected array $logBatch;
    protected string $handler;

    public function __construct(array $logBatch, string $handler = 'http')
    {
        $this->logBatch = $logBatch;
        $this->handler = $handler;
        $this->queue = config('superlog.async_shipping.queue', 'default');
    }

    /**
     * Execute the job
     */
    public function handle(): void
    {
        $config = config('superlog.external_handlers');

        if (empty($config)) {
            return;
        }

        foreach ($config as $handlerConfig) {
            if ($handlerConfig['driver'] === 'http') {
                $this->shipViaHttp($handlerConfig);
            }
        }
    }

    /**
     * Ship logs via HTTP
     */
    protected function shipViaHttp(array $config): void
    {
        try {
            $client = new \GuzzleHttp\Client([
                'timeout' => $config['timeout'] ?? 10,
            ]);

            $headers = ['Content-Type' => 'application/json'];

            if (isset($config['auth'])) {
                $headers['Authorization'] = 'Basic ' . base64_encode(
                    $config['auth']['username'] . ':' . $config['auth']['password']
                );
            }

            $client->post($config['url'], [
                'headers' => $headers,
                'json' => $this->logBatch,
            ]);
        } catch (\Exception $e) {
            if ($this->attempts() < ($config['retry_count'] ?? 3)) {
                $this->release(60); // Retry after 60 seconds
            }
        }
    }

    /**
     * Determine the time at which the job should timeout
     */
    public function timeout(): int
    {
        return 30;
    }

    /**
     * Get the number of seconds to wait before retrying the job
     */
    public function retryAfter(): int
    {
        return 60;
    }
}