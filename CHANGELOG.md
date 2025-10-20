# Changelog

All notable changes to Superlog will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Planned Features
- [ ] Database query profiling with execution plans
- [ ] Redis cache instrumentation
- [ ] Queue job lifecycle tracking
- [ ] Request/response body capture (optional)
- [ ] Distributed tracing with OpenTelemetry
- [ ] Custom metrics support
- [ ] Integration with monitoring dashboards
- [ ] Real-time log streaming
- [ ] Circuit breaker pattern detection
- [ ] Cost analysis for external API calls

## [1.0.0] - 2025-10-20

### Added
- Initial release of Superlog
- **First-class correlation** with trace_id, req_seq, span_id
- **Structured logging** with JSON + human-readable format
- **Request lifecycle sections**: STARTUP, MIDDLEWARE, DATABASE, HTTP-OUT, CACHE, SHUTDOWN
- **Automatic request tracking** via middleware
- **PII redaction** with smart detection and configurable patterns
- **Performance diagnostics**:
  - Request timing
  - Peak memory usage
  - Query aggregation
  - Cache statistics
  - Included files count
  - PHP OPcache status
- **Adaptive verbosity**:
  - Configurable sampling for info/debug logs
  - Automatic escalation on 4xx/5xx responses
  - Slow request detection (750ms default)
- **Async shipping**:
  - Queue-based log batching
  - HTTP handler for ELK/OpenSearch
  - Configurable retry logic
- **Payload handling**:
  - String truncation for large values
  - Array depth limiting
  - File summarization
  - Binary data handling
- **Monolog integration** via custom handler
- **Laravel service provider** for automatic registration
- **Facade** for easy access: `Superlog::`
- **Comprehensive configuration** via `config/superlog.php`
- **Full test coverage** with PHPUnit
- **Documentation**:
  - README.md with full API reference
  - SETUP_GUIDE.md with step-by-step installation
  - QUICK_REFERENCE.md with common tasks
  - STRUCTURE.md with architecture overview
  - Examples with real-world usage patterns
  - CONTRIBUTING.md for developers

### Features by Component

#### Logger (StructuredLogger.php)
- `log()` - Main logging method
- `logStartup()` - Request initialization
- `logMiddlewareStart/End()` - Middleware tracking
- `logDatabase()` - Query aggregation
- `logHttpOut()` - External API calls
- `logCache()` - Cache statistics
- `logShutdown()` - Request completion
- `formatLogEntry()` - Output formatting

#### Middleware (RequestLifecycleMiddleware.php)
- Automatic request initialization
- Startup logging with route/user/IP
- Shutdown logging with timing/memory
- Tenant resolution (framework-agnostic)

#### Processors
- **RedactionProcessor**: Smart PII masking
- **PayloadProcessor**: Normalization and truncation

#### Utils
- **CorrelationContext**: Correlation tracking
- **RequestTimer**: High-precision timing

#### Integration
- **SuperlogHandler**: Monolog bridge
- **ExternalLogHandler**: Async shipping
- **ShipLogsJob**: Queue-based batch shipping

#### Facades
- **Superlog**: Public API access

### Configuration Options

#### Core
- `enabled` - Toggle logging on/off
- `auto_capture_requests` - Automatic lifecycle capture
- `channel` - Logging channel
- `level` - Minimum log level
- `trace_id_header` - HTTP header for trace ID
- `format` - Output format (json/text)

#### Sampling
- `sampling.enabled` - Toggle sampling
- `sampling.info_rate` - INFO level sampling (default 10%)
- `sampling.debug_rate` - DEBUG level sampling (default 5%)

#### Slow Request Detection
- `slow_request.threshold_ms` - Threshold for slow requests
- `slow_request.collect_queries` - Capture slow queries
- `slow_request.collect_cache_stats` - Capture cache stats
- `slow_request.collect_bindings_count` - Container bindings

#### Redaction
- `redaction.enabled` - Toggle PII masking
- `redaction.mode` - Masking mode (mask/remove)
- `redaction.smart_detection` - Pattern-based detection
- `redaction.custom_keys` - Additional keys to redact
- `redaction.patterns` - Sensitive patterns list

#### Payload Handling
- `payload_handling.max_string_length` - String truncation
- `payload_handling.max_array_depth` - Array depth limit
- `payload_handling.max_file_size_kb` - File size limit
- `payload_handling.summarize_uploads` - File summarization

#### Sections
- Per-section configuration for:
  - `startup` - Route/user/IP capture
  - `middleware` - Middleware whitelist/blacklist
  - `database` - Query capture settings
  - `cache` - Cache statistics
  - `http_outbound` - API call capture
  - `shutdown` - Memory/files/opcache

#### Async Shipping
- `async_shipping.enabled` - Toggle async shipping
- `async_shipping.queue` - Queue name
- `async_shipping.batch_size` - Batch size (default 100)
- `async_shipping.batch_timeout_ms` - Batch timeout

#### External Handlers
- HTTP handler configuration:
  - URL endpoint
  - Authentication
  - Timeout
  - Retry count

### Environment Variables

```
SUPERLOG_ENABLED=true
SUPERLOG_AUTO_CAPTURE=true
SUPERLOG_FORMAT=json
SUPERLOG_TRACE_ID_HEADER=X-Trace-Id
SUPERLOG_SAMPLING_ENABLED=false
SUPERLOG_SLOW_REQUEST_MS=750
SUPERLOG_REDACTION_ENABLED=true
SUPERLOG_REDACT_KEYS=
SUPERLOG_ASYNC_ENABLED=false
SUPERLOG_QUEUE=default
SUPERLOG_HTTP_URL=http://localhost:9200
SUPERLOG_HTTP_USER=
SUPERLOG_HTTP_PASS=
```

### Log Output Examples

#### JSON Format
```json
{
  "timestamp": "2025-10-20T09:13:46.343976Z",
  "trace_id": "a1b2c3d4-e5f6-7890-1234-567890abcdef",
  "req_seq": "0000000001",
  "span_id": "b5c6d7e8-f9a0-1234-5678-90abcdef1234",
  "level": "INFO",
  "section": "[MIDDLEWARE END]",
  "message": "[MIDDLEWARE END] VerifyCsrfToken - SUCCESS",
  "context": {},
  "metrics": {
    "duration_ms": 11.75,
    "response_status": 200
  },
  "correlation": {
    "trace_id": "a1b2c3d4-e5f6-7890-1234-567890abcdef",
    "method": "GET",
    "path": "/users",
    "client_ip": "192.168.1.100"
  }
}
```

#### Text Format
```
[2025-10-20 09:13:46] local.INFO: [MIDDLEWARE END] VerifyCsrfToken - SUCCESS {"duration_ms":11.75,"response_status":200}
```

### Testing

- 15+ unit tests covering core functionality
- RedactionProcessor tests with various PII patterns
- PayloadProcessor tests for truncation and normalization
- CorrelationContext tests for trace ID and span ID generation
- Sequence numbering tests (req_seq)
- Output formatting tests (JSON/text)

### Documentation

- **README.md**: Comprehensive documentation with features, setup, and examples
- **SETUP_GUIDE.md**: Step-by-step installation and configuration
- **QUICK_REFERENCE.md**: Quick API and configuration reference
- **STRUCTURE.md**: Package structure and architecture
- **CONTRIBUTING.md**: Developer guidelines
- **examples/**: Real-world usage patterns
- **Inline PHPDoc**: Comprehensive docstring comments

### Compatibility

- PHP: 8.1+
- Laravel: 10.x, 11.x
- Monolog: 3.x

### License

MIT License

---

## [Planned: 1.1.0]

### Planned Features
- Database query execution plan analysis
- Cache miss analysis and recommendations
- Request comparison/trending
- Custom metric types
- Built-in dashboards
- Grafana integration

## [Planned: 2.0.0]

### Planned Breaking Changes
- OpenTelemetry integration
- New distributed tracing format
- Queue job instrumentation
- Real-time streaming mode
- Advanced security features