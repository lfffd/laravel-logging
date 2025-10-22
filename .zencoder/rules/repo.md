---
description: Repository Information Overview
alwaysApply: true
---

# Superlog Information

## Summary
Superlog is an enterprise-grade structured logging package for Laravel with built-in correlation tracking, PII redaction, performance diagnostics, and seamless ELK/OpenSearch integration. It provides advanced logging capabilities with features like trace ID tracking, request sequence numbering, and section-based logging.

## Structure
- **src/**: Core package code organized by functionality (Logger, Handlers, Processors, etc.)
- **config/**: Configuration files for the package
- **tests/**: PHPUnit test suite
- **demo/**: Example Laravel application demonstrating package usage
- **examples/**: Code snippets showing integration patterns

## Language & Runtime
**Language**: PHP
**Version**: ^8.1
**Framework**: Laravel ^10.0|^11.0
**Package Manager**: Composer

## Dependencies
**Main Dependencies**:
- laravel/framework: ^10.0|^11.0
- monolog/monolog: ^3.0
- ramsey/uuid: ^4.7

**Development Dependencies**:
- phpunit/phpunit: ^10.0
- laravel/pint: ^1.0

## Build & Installation
```bash
# From GitHub (Before Packagist Publication)
composer config repositories.superlog vcs https://github.com/lfffd/laravel-logging.git
composer require lfffd/laravel-logging:dev-main

# From Packagist (After Publication)
composer require lfffd/laravel-logging

# Publish configuration
php artisan vendor:publish --provider="Superlog\SuperlogServiceProvider"
```

## Testing
**Framework**: PHPUnit ^10.0
**Test Location**: tests/
**Configuration**: phpunit.xml
**Run Command**:
```bash
./vendor/bin/phpunit
```

## Key Features
- **Correlation Tracking**: UUID-based trace IDs and sequential request IDs
- **Structured Sections**: Startup, middleware, database, HTTP outbound, cache, shutdown
- **PII Redaction**: Automatic masking of sensitive data (passwords, tokens, PII)
- **Performance Diagnostics**: Request timing, slow query detection, memory usage
- **Adaptive Verbosity**: Configurable sampling rates for different log levels
- **Async Shipping**: Queue-based log shipping to external systems (ELK/OpenSearch)

## Integration
**Service Provider**: Superlog\SuperlogServiceProvider
**Middleware**: Superlog\Middleware\RequestLifecycleMiddleware
**Facade**: Superlog\Facades\Superlog
**Configuration**: config/superlog.php