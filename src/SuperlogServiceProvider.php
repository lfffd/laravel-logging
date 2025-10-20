<?php

namespace Superlog;

use Illuminate\Support\ServiceProvider;
use Illuminate\Log\LogManager;
use Superlog\Handlers\SuperlogHandler;
use Superlog\Middleware\RequestLifecycleMiddleware;
use Superlog\Logger\StructuredLogger;
use Superlog\Commands\SuperlogCheckCommand;

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
    }

    /**
     * Register the custom Monolog handler with Laravel's logging system.
     */
    protected function registerMonologHandler(): void
    {
        $manager = $this->app->make('log');

        if ($manager instanceof LogManager) {
            $manager->extend('superlog', function ($app, array $config) {
                return new \Monolog\Logger(
                    $config['name'] ?? 'superlog',
                    [
                        $app->make(SuperlogHandler::class),
                    ]
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
        }
    }
}