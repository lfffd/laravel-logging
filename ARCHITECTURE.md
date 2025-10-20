# Superlog Architecture

## High-Level Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                     Laravel Application                          │
├─────────────────────────────────────────────────────────────────┤
│  HTTP Request                                                    │
│      ↓                                                           │
│  RequestLifecycleMiddleware (AUTO-CAPTURE)                      │
│      ↓                                                           │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │  StructuredLogger (CORE ENGINE)                         │   │
│  │  ├─ Correlation Context (trace_id, req_seq, span_id)   │   │
│  │  ├─ Processors                                          │   │
│  │  │  ├─ RedactionProcessor (PII masking)               │   │
│  │  │  └─ PayloadProcessor (Truncation)                   │   │
│  │  └─ Log Methods                                         │   │
│  │     ├─ log() - Generic logging                          │   │
│  │     ├─ logStartup() - Request init                      │   │
│  │     ├─ logMiddlewareEnd() - Middleware timing           │   │
│  │     ├─ logDatabase() - Query stats                      │   │
│  │     ├─ logHttpOut() - External calls                    │   │
│  │     └─ logShutdown() - Request completion              │   │
│  └─────────────────────────────────────────────────────────┘   │
│      ↓                                                           │
│  SuperlogHandler (MONOLOG BRIDGE)                              │
│      ↓                                                           │
│  ┌──────────────────────────────────────────┐                  │
│  │  Laravel Logging Stack                   │                  │
│  │  ├─ Stream Handler → storage/logs/       │                  │
│  │  ├─ [Optional] Slack Handler             │                  │
│  │  └─ [Optional] Other Handlers            │                  │
│  └──────────────────────────────────────────┘                  │
│      ↓                                                           │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │  Async Shipping (OPTIONAL)                               │  │
│  │  ├─ ExternalLogHandler (batching)                        │  │
│  │  └─ ShipLogsJob (queue-based)                            │  │
│  │     └─ HTTP → ELK/OpenSearch                             │  │
│  └──────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
```

## Request Lifecycle Flow

```
1. REQUEST ARRIVES
   │
   └─→ RequestLifecycleMiddleware::handle()
       │
       ├─→ initializeRequest()
       │   │
       │   ├─ Set trace_id (UUID v4)
       │   ├─ Initialize span_ids
       │   └─ Create CorrelationContext
       │
       ├─→ logStartup()
       │   │
       │   ├─ Log [STARTUP] section
       │   ├─ Capture route, IP, user, tenant
       │   └─ Record request payload size
       │
       ├─→ next() → Execute middleware + route
       │   │
       │   ├─ [MIDDLEWARE START] (if monitored)
       │   ├─ Middleware logic
       │   └─ [MIDDLEWARE END] with timing
       │
       ├─→ Application processes request
       │   │
       │   ├─ [DATABASE] queries logged
       │   ├─ [HTTP-OUT] external calls logged
       │   ├─ [CACHE] operations logged
       │   └─ Response built
       │
       ├─→ response returned
       │
       └─→ Finally block
           │
           ├─→ logShutdown()
           │   │
           │   ├─ Log [SHUTDOWN] section
           │   ├─ Calculate request duration
           │   ├─ Record peak memory
           │   ├─ Count included files
           │   ├─ Check opcache status
           │   └─ Record dispatched jobs
           │
           └─ Request complete

2. LOG PROCESSING
   │
   ├─→ Each log entry structured:
   │   ├─ timestamp
   │   ├─ trace_id (UUID v4)
   │   ├─ req_seq (0000000001, 0000000002, ...)
   │   ├─ span_id (per section)
   │   ├─ level, section, message
   │   ├─ context, metrics
   │   └─ correlation data
   │
   ├─→ RedactionProcessor
   │   ├─ Identify sensitive keys
   │   ├─ Mask values (smart detection)
   │   └─ Apply configured patterns
   │
   ├─→ PayloadProcessor
   │   ├─ Truncate long strings
   │   ├─ Limit array depth
   │   └─ Summarize files
   │
   ├─→ Format output
   │   ├─ JSON (production)
   │   └─ Text with header (development)
   │
   └─→ Monolog stack
       ├─ SuperlogHandler processes
       ├─ Stream to storage/logs/laravel.log
       ├─ [Optional] Other handlers
       └─ [Optional] Ship async

3. ASYNC SHIPPING (Optional)
   │
   ├─→ ExternalLogHandler
   │   ├─ Buffer logs in memory
   │   ├─ Check batch size OR timeout
   │   └─ When ready, dispatch ShipLogsJob
   │
   ├─→ Queue Worker
   │   ├─ Pick up ShipLogsJob
   │   ├─ Prepare HTTP request
   │   └─ POST to external handler
   │
   ├─→ External Service (ELK/OpenSearch)
   │   ├─ Receive JSON logs
   │   ├─ Index by trace_id
   │   └─ Make searchable
   │
   └─→ Kibana/OpenSearch UI
       ├─ Query by trace_id
       ├─ Filter by section
       └─ Visualize request flow
```

## Component Relationships

### Core Components

```
┌────────────────────────┐
│ SuperlogServiceProvider│
│ (Registers everything) │
└────────────────────────┘
           │
    ┌──────┼──────┐
    │      │      │
    ↓      ↓      ↓
┌──────┐ ┌─────────────┐ ┌──────────────────┐
│Config│ │StructuredLoggerLogger │ │RequestLifecycleMiddleware│
└──────┘ │ (CORE ENGINE)│ └──────────────────┘
         │ ├─ CorrelationContext│       │
         │ ├─ RedactionProcessor       │
         │ └─ PayloadProcessor   │       │
         └─────────────────────────────┘
              │
              ├─→ SuperlogHandler (Monolog)
              │   └─→ Logging Stack
              │
              └─→ ExternalLogHandler
                  └─→ ShipLogsJob
                      └─→ HTTP POST
```

### Processor Chain

```
Log Entry
    │
    ├─→ RedactionProcessor
    │   ├─ Check if key is sensitive
    │   ├─ Apply smart detection
    │   ├─ Recursively redact array
    │   └─ Mask values
    │
    ├─→ PayloadProcessor
    │   ├─ Truncate strings
    │   ├─ Limit array depth
    │   ├─ Summarize files/resources
    │   └─ Normalize values
    │
    ├─→ Formatter
    │   ├─ JSON output
    │   └─ Text output
    │
    └─→ Output (file, external service, etc.)
```

### Correlation Context Flow

```
HTTP Request arrives
    │
    ├─→ Generate trace_id (UUID v4)
    │   └─ Valid for entire request lifecycle
    │       └─ Passed to external services
    │
    ├─→ Initialize req_seq counter (0)
    │   └─ Increments per log line
    │       ├─ 0000000001
    │       ├─ 0000000002
    │       └─ 0000000003
    │
    ├─→ Generate span_ids per section
    │   ├─ [STARTUP] → span_id_1
    │   ├─ [MIDDLEWARE] → span_id_2
    │   ├─ [DATABASE] → span_id_3
    │   └─ [SHUTDOWN] → span_id_4
    │
    └─→ All logs include:
        ├─ trace_id (same for all logs in request)
        ├─ req_seq (incremental, unique per line)
        ├─ span_id (per section, consistent within section)
        └─ correlation.trace_id (for ELK grouping)
```

## Data Flow: Detailed

### 1. Request Initialization

```
HTTP Request
    │
    ├─→ extract trace_id from header (X-Trace-Id) OR generate new
    │
    ├─→ CorrelationContext::setTraceId()
    │
    ├─→ CorrelationContext::setMethod("GET")
    ├─→ CorrelationContext::setPath("/users")
    ├─→ CorrelationContext::setClientIp("192.168.1.100")
    │
    └─→ Ready for logging
```

### 2. Log Creation

```
StructuredLogger::log()
    │
    ├─→ Increment req_seq counter
    │   └─ "0000000001" (zero-padded to 10 chars)
    │
    ├─→ Get or create span_id for section
    │   └─ CorrelationContext::getOrCreateSpanId("SECTION")
    │
    ├─→ Build log entry array:
    │   ├─ timestamp: ISO8601
    │   ├─ trace_id: from CorrelationContext
    │   ├─ req_seq: incremental counter
    │   ├─ span_id: section-specific
    │   ├─ level: INFO/ERROR/DEBUG/etc
    │   ├─ section: [MIDDLEWARE], [DATABASE], etc
    │   ├─ message: log message
    │   ├─ context: custom data
    │   ├─ metrics: performance data
    │   └─ correlation: correlation data
    │
    ├─→ Apply RedactionProcessor
    │   ├─ Scan all string keys
    │   ├─ Match against patterns
    │   └─ Mask sensitive values
    │
    ├─→ Apply PayloadProcessor
    │   ├─ Truncate long strings
    │   ├─ Limit array depth
    │   └─ Summarize resources
    │
    └─→ Return processed log entry
```

### 3. Output Formatting

```
Log Entry → formatLogEntry()
    │
    ├─→ If format = 'json'
    │   ├─ json_encode() with options
    │   └─ Return JSON string
    │
    └─→ If format = 'text'
        ├─ Build header: [timestamp] channel.LEVEL: [SECTION] message
        ├─ Append context + metrics as JSON
        └─ Return formatted string
```

### 4. Async Shipping

```
ExternalLogHandler
    │
    ├─→ Add log to batch array
    │
    ├─→ Check if should flush:
    │   ├─ Batch size >= batch_size? (default 100)
    │   ├─ OR timeout exceeded?
    │   └─ OR destructor called?
    │
    ├─→ If flush needed:
    │   │
    │   ├─→ ShipLogsJob::dispatch($batch)
    │   │   └─ Added to queue
    │   │
    │   ├─→ Clear batch array
    │   └─→ Reset timeout counter
    │
    └─→ Queue worker processes job
        │
        ├─→ Read config('superlog.external_handlers')
        │
        ├─→ For each handler:
        │   ├─ If driver = 'http'
        │   │   ├─ POST /url with logs
        │   │   ├─ Include auth headers
        │   │   ├─ Retry on failure
        │   │   └─ Log shipping result
        │   │
        │   └─ Future: Kafka, SQS, etc.
        │
        └─→ Job complete
```

## Memory Management

```
Request Start
    │
    ├─ Track peak memory: memory_get_peak_usage(true)
    │
    ├─ During processing:
    │   ├─ Log entries cached in memory
    │   ├─ Processors apply transformations
    │   └─ No disk I/O until flush
    │
    ├─ Request End → [SHUTDOWN]
    │   ├─ Record peak memory usage
    │   ├─ Report in shutdown metrics
    │   └─ Flush all remaining logs
    │
    └─ If async enabled:
        ├─ Batch logs in queue table
        └─ Worker processes asynchronously
```

## Performance Characteristics

```
Per-Request Overhead:
├─ CorrelationContext: ~0.1ms
├─ RedactionProcessor: ~0.5-2ms (depends on data size)
├─ PayloadProcessor: ~0.2-1ms
├─ Monolog integration: ~0.1ms
└─ Total: ~1-3ms overhead

Sampling (if enabled):
├─ INFO logs: 10% sampled (90% dropped)
├─ DEBUG logs: 5% sampled (95% dropped)
└─ ERROR/WARN: Always logged

Memory:
├─ Single log entry: ~500 bytes (average)
├─ 100-log batch: ~50KB
├─ Async shipping frees memory after dispatch
└─ No memory leaks (proper cleanup)
```

## Configuration Cascade

```
1. composer.json (dependencies)
   ↓
2. SuperlogServiceProvider::register()
   ├─ Merge config/superlog.php
   └─ Register services
   ↓
3. config/superlog.php (defaults)
   ├─ All features enabled
   ├─ Sensible defaults
   └─ Overridable
   ↓
4. config/logging.php (Laravel)
   ├─ Specify 'driver' => 'superlog'
   └─ Configure channel
   ↓
5. .env (Environment)
   ├─ Override specific settings
   └─ Secrets (URLs, credentials)
   ↓
6. Runtime (Application)
   ├─ Facade calls: Superlog::log()
   └─ Programmatic config
```

## Error Handling

```
If log processing fails:
├─ RedactionProcessor error → continue with unredacted
├─ PayloadProcessor error → continue with full payload
├─ Formatter error → fallback to basic string
└─ External handler error → queue retry (3 attempts)

Graceful degradation:
├─ Missing trace_id → generate new UUID
├─ Missing section → use 'GENERAL'
├─ Missing context → use empty array
└─ Shipping fails → retry or skip
```

## Extension Points

```
Custom Processors:
├─ Extend Processor interface
├─ Override process(array $entry): array
└─ Register in middleware stack

Custom Sections:
├─ StructuredLogger::log('info', 'MY_SECTION', ...)
├─ Will auto-create span_id
└─ Appears in logs

Custom External Handlers:
├─ Add driver in config
├─ Implement HTTP/queue shipping
└─ Integrate with external service

Custom Redaction:
├─ Update config/superlog.php
├─ Add patterns or custom_keys
└─ Automatic masking applied
```

## Testing Strategy

```
Unit Tests:
├─ CorrelationContext (trace_id, span_id generation)
├─ RedactionProcessor (masking patterns)
├─ PayloadProcessor (truncation, depth limits)
├─ StructuredLogger (all logging methods)
└─ RequestTimer (timing accuracy)

Integration Tests (Todo):
├─ RequestLifecycleMiddleware
├─ Laravel service provider
├─ Monolog handler integration
└─ External shipping

Performance Tests (Todo):
├─ Memory usage under load
├─ CPU usage with sampling
├─ Shipping batch performance
└─ Concurrent requests
```

## Deployment Considerations

```
Development:
├─ Format: text (human-readable)
├─ Sampling: disabled
├─ Redaction: enabled
├─ Async: optional
└─ Files: local storage

Production:
├─ Format: json (ELK/OpenSearch compatible)
├─ Sampling: enabled (reduce noise)
├─ Redaction: enabled (PII protection)
├─ Async: enabled (non-blocking)
├─ Shipping: ELK/OpenSearch
├─ Queue worker: running
└─ Monitoring: Kibana dashboards
```

---

**Next Steps**: See README.md for usage, SETUP_GUIDE.md for installation, and QUICK_REFERENCE.md for common tasks.