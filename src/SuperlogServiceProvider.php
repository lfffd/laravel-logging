<?php

namespace Superlog;

use Illuminate\Support\ServiceProvider;
use Illuminate\Log\LogManager;
use Superlog\Handlers\SuperlogHandler;
use Superlog\Middleware\RequestLifecycleMiddleware;
use Superlog\Logger\StructuredLogger;
use Superlog\Commands\SuperlogCheckCommand;
use Superlog\Processors\ModelQueryProcessor;
use Superlog\Utils\CorrelationContext;

class SuperlogServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/superlog.php',
            'superlog'
        );

        $this->app->singleton(StructuredLogger::class, function ($app) {
            return new StructuredLogger($app['config']['superlog']);
        });

        $this->app->singleton(SuperlogHandler::class, function ($app) {
            return new SuperlogHandler($app->make(StructuredLogger::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/superlog.php' => config_path('superlog.php'),
        ], 'config');

        $this->commands([
            SuperlogCheckCommand::class,
        ]);

        $this->registerMonologHandler();
        $this->registerMiddleware();
        $this->registerModelQueryProcessor();
    }

    /**
     * Register the custom Monolog handler with Laravel's logging system.
     */
    protected function registerMonologHandler(): void
    {
        $manager = $this->app->make('log');

        if ($manager instanceof LogManager) {
            $manager->extend('superlog', function ($app, array $config) {
                // Create stream handler for file writing
                $path = $config['path'] ?? storage_path('logs/laravel-' . date('Y-m-d') . '.log');
                $streamHandler = new \Monolog\Handler\StreamHandler(
                    $path,
                    \Monolog\Level::Debug
                );
                
                // Remove default formatting - we handle that in SuperlogHandler
                $streamHandler->setFormatter(new \Monolog\Formatter\LineFormatter("%message%\n"));
                
                // Create Superlog handler
                $superlogHandler = $app->make(SuperlogHandler::class);
                $superlogHandler->setStreamHandler($streamHandler, $path);
                
                return new \Monolog\Logger(
                    $config['name'] ?? 'superlog',
                    [$superlogHandler]
                );
            });
        }
    }

    /**
     * Register the request lifecycle middleware.
     */
    protected function registerMiddleware(): void
    {
        if (config('superlog.auto_capture_requests', true)) {
            $this->app['router']->aliasMiddleware(
                'superlog.request-lifecycle',
                RequestLifecycleMiddleware::class
            );
            
            // Register the non-HTTP context middleware
            $this->app['router']->aliasMiddleware(
                'superlog.non-http-context',
                \Superlog\Middleware\NonHttpContextMiddleware::class
            );
            
            // Add the non-HTTP context middleware to the global middleware stack
            // This ensures it runs for all requests, including non-HTTP contexts
            if ($this->app->runningInConsole()) {
                $this->app->singleton(CorrelationContext::class, function ($app) {
                    $correlation = new CorrelationContext();
                    $correlation->setTraceId('cli_' . \Illuminate\Support\Str::uuid()->toString());
                    $correlation->setMethod('CLI');
                    $correlation->setPath(implode(' ', $_SERVER['argv'] ?? ['unknown']));
                    $correlation->setClientIp('127.0.0.1');
                    return $correlation;
                });
            }
        }
    }
    
    /**
     * Register the model query processor.
     */
    protected function registerModelQueryProcessor(): void
    {
        if (config('superlog.model_query_logging.enabled', true)) {
            ModelQueryProcessor::register();
        }
    }
}