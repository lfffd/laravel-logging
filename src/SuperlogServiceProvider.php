<?php

namespace Superlog;

use Illuminate\Support\ServiceProvider;
use Illuminate\Log\LogManager;
use Superlog\Handlers\SuperlogHandler;
use Superlog\Middleware\RequestLifecycleMiddleware;
use Superlog\Logger\StructuredLogger;

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
        $this->publishesConfig([
            __DIR__ . '/../config/superlog.php' => config_path('superlog.php'),
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