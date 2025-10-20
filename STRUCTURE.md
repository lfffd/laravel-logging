# Superlog Package Structure

```
superlog/
├── composer.json                          # Package metadata
├── README.md                              # Full documentation
├── SETUP_GUIDE.md                         # Installation & setup instructions
├── LICENSE                                # MIT License
├── .gitignore                             # Git ignore rules
│
├── config/
│   └── superlog.php                       # Default configuration (published to app's config/)
│
├── src/
│   ├── SuperlogServiceProvider.php        # Service provider for Laravel
│   │
│   ├── Logger/
│   │   └── StructuredLogger.php           # Core logging engine
│   │
│   ├── Middleware/
│   │   └── RequestLifecycleMiddleware.php # Auto-capture request lifecycle
│   │
│   ├── Handlers/
│   │   ├── SuperlogHandler.php            # Monolog handler integration
│   │   └── ExternalLogHandler.php         # Handler for async shipping
│   │
│   ├── Processors/
│   │   ├── RedactionProcessor.php         # PII masking & redaction
│   │   └── PayloadProcessor.php           # Payload normalization & truncation
│   │
│   ├── Jobs/
│   │   └── ShipLogsJob.php                # Queue job for async log shipping
│   │
│   ├── Utils/
│   │   ├── CorrelationContext.php         # Request correlation tracking
│   │   └── RequestTimer.php               # Elapsed time tracking
│   │
│   └── Facades/
│       └── Superlog.php                   # Facade for easy access
│
├── examples/
│   └── middleware_integration.php         # Usage examples
│
└── tests/
    └── SuperlogTest.php                   # PHPUnit test suite
```

## Key Components

### Core Files

- **SuperlogServiceProvider.php**: Registers the service, middleware, and Monolog handler
- **StructuredLogger.php**: Main logging engine with all logging methods
- **RequestLifecycleMiddleware.php**: Automatically captures request startup/shutdown

### Processors

- **RedactionProcessor.php**: Masks sensitive data (passwords, tokens, PII)
- **PayloadProcessor.php**: Truncates large payloads, summarizes files

### Utils

- **CorrelationContext.php**: Manages trace_id, span_id, and request metadata
- **RequestTimer.php**: Tracks request timing for metrics

### Integration

- **SuperlogHandler.php**: Bridges Monolog with Superlog for Laravel logging
- **ExternalLogHandler.php**: Handles async shipping to external services
- **ShipLogsJob.php**: Queue job for batched log shipping

## Configuration

The `config/superlog.php` file is published to the application's `config/` directory and contains:
- General enable/disable settings
- Log level configuration
- Sampling rates
- Slow request detection thresholds
- PII redaction patterns
- Async shipping settings
- External handler configuration
- Per-section settings

## Usage Flow

1. **Request arrives** → RequestLifecycleMiddleware initialized
2. **[STARTUP] logged** → Route, IP, user info captured
3. **Middleware executed** → [MIDDLEWARE START/END] logged with timing
4. **Database queries** → [DATABASE] stats aggregated
5. **External APIs** → [HTTP-OUT] captured
6. **Response sent** → [SHUTDOWN] logged
7. **Optional**: Logs shipped async to ELK/OpenSearch

## Key Features

### Correlation
- **trace_id**: UUID v4, spans entire request
- **req_seq**: Incremental per-request log counter (0000000001, 0000000002, etc.)
- **span_id**: Per-section correlation ID

### Sections
- [STARTUP] - Request initialization
- [MIDDLEWARE START/END] - Middleware execution
- [DATABASE] - Query aggregation
- [HTTP-OUT] - External API calls
- [CACHE] - Cache statistics
- [SHUTDOWN] - Request completion

### Security
- Smart PII redaction with configurable patterns
- Password, token, email, phone, SSN masking
- Binary file summarization
- Configurable masking modes (mask/remove)

### Performance
- Lightweight processor design
- Deferred heavy collectors until shutdown
- Configurable sampling for info/debug levels
- Optional async queue-based shipping

### Output
- Structured JSON for ELK/OpenSearch integration
- Human-readable text format for local development
- Single daily log file (standard Laravel rotation)

## Installation

1. Require via Composer:
   ```bash
   composer require lfffd/laravel-logging
   ```

2. Publish config:
   ```bash
   php artisan vendor:publish --provider="Superlog\SuperlogServiceProvider"
   ```

3. Configure in `config/logging.php`

4. Register middleware in `app/Http/Kernel.php`

5. Set environment variables in `.env`

See **SETUP_GUIDE.md** for detailed instructions.