# Observability & Reliability

Logging, monitoring, tracing, circuit breakers, error handling, and resilience patterns.

---

## Three Pillars of Observability

```
             Observability
            /      |       \
        Logs    Metrics    Traces
      (events)  (numbers)  (flows)
```

---

## Structured Logging

### Log Format (JSON)

```json
{
  "timestamp": "2024-01-15T10:30:00.123Z",
  "level": "error",
  "service": "order-service",
  "version": "1.4.2",
  "trace_id": "abc123",
  "span_id": "def456",
  "request_id": "req_789",
  "user_id": "user_123",
  "method": "POST",
  "path": "/api/v1/orders",
  "status": 500,
  "duration_ms": 342,
  "error": {
    "type": "DatabaseError",
    "message": "Connection refused",
    "stack": "..."
  }
}
```

### Log Levels

| Level | When to Use | Example |
|-------|-------------|---------|
| `error` | Operation failed, needs attention | DB connection lost, payment failed |
| `warn` | Degraded but functional | High memory, retry succeeded, rate limited |
| `info` | Significant business events | User registered, order placed, deploy complete |
| `debug` | Development diagnostics | SQL queries, cache hit/miss, function entry/exit |

### Logging Best Practices

```typescript
// DO: Structured, contextual logging
logger.info('Order created', {
  orderId: order.id,
  userId: user.id,
  total: order.total,
  items: order.items.length,
});

// DON'T: Unstructured string concatenation
logger.info(`Order ${order.id} created by user ${user.id}`);

// DO: Log at appropriate level
logger.error('Payment processing failed', {
  orderId, error: err.message, provider: 'stripe',
});

// DON'T: Log sensitive data
logger.info('User login', { email, password }); // NEVER log passwords
```

### Correlation IDs

```typescript
// Middleware to generate/propagate correlation ID
function correlationId(req, res, next) {
  const id = req.headers['x-request-id'] || crypto.randomUUID();
  req.requestId = id;
  res.setHeader('X-Request-Id', id);

  // Attach to async context for automatic propagation
  asyncLocalStorage.run({ requestId: id }, () => next());
}

// Logger automatically includes correlation ID
const logger = {
  info(message, data = {}) {
    const store = asyncLocalStorage.getStore();
    console.log(JSON.stringify({
      level: 'info',
      message,
      request_id: store?.requestId,
      timestamp: new Date().toISOString(),
      ...data,
    }));
  },
};
```

---

## Metrics & Monitoring

### Key Metrics (RED + USE)

**RED Method** (request-driven services):

| Metric | What | Alert When |
|--------|------|------------|
| **R**ate | Requests per second | Sudden spike or drop |
| **E**rrors | Error rate (%) | > 1% for 5 minutes |
| **D**uration | Latency (p50, p95, p99) | p95 > SLA threshold |

**USE Method** (infrastructure):

| Metric | What | Alert When |
|--------|------|------------|
| **U**tilization | Resource usage % | CPU > 80%, Memory > 85% |
| **S**aturation | Queue depth, backlog | Growing queue, rejected connections |
| **E**rrors | Hardware/system errors | Any non-zero rate |

### Application Metrics

```typescript
// Prometheus-style metrics
const httpRequestDuration = new Histogram({
  name: 'http_request_duration_seconds',
  help: 'HTTP request latency in seconds',
  labelNames: ['method', 'route', 'status'],
  buckets: [0.01, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10],
});

const httpRequestTotal = new Counter({
  name: 'http_requests_total',
  help: 'Total HTTP requests',
  labelNames: ['method', 'route', 'status'],
});

const activeConnections = new Gauge({
  name: 'active_connections',
  help: 'Number of active connections',
});

// Middleware
app.use((req, res, next) => {
  const end = httpRequestDuration.startTimer();
  activeConnections.inc();

  res.on('finish', () => {
    const labels = {
      method: req.method,
      route: req.route?.path || 'unknown',
      status: res.statusCode,
    };
    end(labels);
    httpRequestTotal.inc(labels);
    activeConnections.dec();
  });

  next();
});
```

### Health Check Endpoints

```typescript
// Liveness: Is the process running?
app.get('/health/live', (req, res) => {
  res.status(200).json({ status: 'alive' });
});

// Readiness: Can it handle requests?
app.get('/health/ready', async (req, res) => {
  const checks = {
    database: await checkDatabase(),
    redis: await checkRedis(),
    disk: checkDiskSpace(),
  };

  const allHealthy = Object.values(checks).every(c => c.status === 'healthy');
  const status = allHealthy ? 200 : 503;

  res.status(status).json({
    status: allHealthy ? 'ready' : 'degraded',
    checks,
    uptime: process.uptime(),
    timestamp: new Date().toISOString(),
  });
});

async function checkDatabase() {
  try {
    await db.$queryRaw`SELECT 1`;
    return { status: 'healthy', latency_ms: elapsed };
  } catch (err) {
    return { status: 'unhealthy', error: err.message };
  }
}
```

---

## Distributed Tracing

### Trace Structure

```
Trace: order-creation (trace_id: abc123)
├── Span: API Gateway (span_id: 001, 0-350ms)
│   ├── Span: Auth Middleware (span_id: 002, 5-15ms)
│   ├── Span: Order Service (span_id: 003, 20-300ms)
│   │   ├── Span: Validate Input (span_id: 004, 25-35ms)
│   │   ├── Span: Database Write (span_id: 005, 40-120ms)
│   │   ├── Span: Payment Service Call (span_id: 006, 130-280ms)
│   │   │   └── Span: Stripe API (span_id: 007, 140-270ms)
│   │   └── Span: Publish Event (span_id: 008, 285-295ms)
│   └── Span: Response Serialization (span_id: 009, 305-310ms)
```

### OpenTelemetry Setup

```typescript
import { NodeSDK } from '@opentelemetry/sdk-node';
import { getNodeAutoInstrumentations } from '@opentelemetry/auto-instrumentations-node';

const sdk = new NodeSDK({
  serviceName: 'order-service',
  traceExporter: new OTLPTraceExporter({
    url: process.env.OTEL_EXPORTER_OTLP_ENDPOINT,
  }),
  instrumentations: [getNodeAutoInstrumentations()],
});

sdk.start();
```

---

## Resilience Patterns

### Circuit Breaker

```typescript
enum CircuitState { CLOSED, OPEN, HALF_OPEN }

class CircuitBreaker {
  private state = CircuitState.CLOSED;
  private failureCount = 0;
  private lastFailureTime = 0;

  constructor(
    private threshold: number = 5,     // Failures before opening
    private timeout: number = 30000,   // Time before trying again
    private monitorWindow: number = 60000,
  ) {}

  async execute<T>(fn: () => Promise<T>): Promise<T> {
    if (this.state === CircuitState.OPEN) {
      if (Date.now() - this.lastFailureTime > this.timeout) {
        this.state = CircuitState.HALF_OPEN;
      } else {
        throw new CircuitOpenError('Circuit is open');
      }
    }

    try {
      const result = await fn();
      this.onSuccess();
      return result;
    } catch (error) {
      this.onFailure();
      throw error;
    }
  }

  private onSuccess() {
    this.failureCount = 0;
    this.state = CircuitState.CLOSED;
  }

  private onFailure() {
    this.failureCount++;
    this.lastFailureTime = Date.now();
    if (this.failureCount >= this.threshold) {
      this.state = CircuitState.OPEN;
    }
  }
}

// Usage
const paymentCircuit = new CircuitBreaker(5, 30000);
try {
  const result = await paymentCircuit.execute(() => paymentService.charge(amount));
} catch (err) {
  if (err instanceof CircuitOpenError) {
    // Fallback: queue for retry, return pending status
    await retryQueue.add('payment', { amount, orderId });
    return { status: 'payment_pending' };
  }
  throw err;
}
```

### Retry with Exponential Backoff

```typescript
async function retryWithBackoff<T>(
  fn: () => Promise<T>,
  options: {
    maxRetries?: number;
    baseDelay?: number;
    maxDelay?: number;
    retryOn?: (error: Error) => boolean;
  } = {}
): Promise<T> {
  const {
    maxRetries = 3,
    baseDelay = 1000,
    maxDelay = 30000,
    retryOn = isTransientError,
  } = options;

  for (let attempt = 0; attempt <= maxRetries; attempt++) {
    try {
      return await fn();
    } catch (error) {
      if (attempt === maxRetries || !retryOn(error)) throw error;

      const delay = Math.min(
        baseDelay * Math.pow(2, attempt) + Math.random() * 1000,
        maxDelay
      );
      await sleep(delay);
    }
  }
}

function isTransientError(error) {
  if (error.response) {
    return [408, 429, 500, 502, 503, 504].includes(error.response.status);
  }
  return ['ECONNRESET', 'ETIMEDOUT', 'ECONNREFUSED'].includes(error.code);
}
```

### Timeout Pattern

```typescript
async function withTimeout<T>(
  promise: Promise<T>,
  ms: number,
  message = 'Operation timed out'
): Promise<T> {
  const timer = new Promise<never>((_, reject) =>
    setTimeout(() => reject(new TimeoutError(message)), ms)
  );
  return Promise.race([promise, timer]);
}

// Usage: all external calls MUST have timeouts
const user = await withTimeout(userService.getUser(id), 5000, 'User service timeout');
```

### Bulkhead Pattern

```typescript
// Isolate resources to prevent cascade failures
class Bulkhead {
  private active = 0;
  private queue: Array<{ resolve: Function; reject: Function }> = [];

  constructor(
    private maxConcurrent: number = 10,
    private maxQueue: number = 100,
  ) {}

  async execute<T>(fn: () => Promise<T>): Promise<T> {
    if (this.active >= this.maxConcurrent) {
      if (this.queue.length >= this.maxQueue) {
        throw new Error('Bulkhead queue full');
      }
      await new Promise((resolve, reject) => this.queue.push({ resolve, reject }));
    }

    this.active++;
    try {
      return await fn();
    } finally {
      this.active--;
      if (this.queue.length > 0) {
        this.queue.shift().resolve();
      }
    }
  }
}

// Separate bulkheads for different downstream services
const paymentBulkhead = new Bulkhead(10, 50);
const inventoryBulkhead = new Bulkhead(20, 100);
```

---

## Error Handling Strategy

### Error Classification

| Category | HTTP Status | Retry? | Example |
|----------|-------------|--------|---------|
| Validation | 400, 422 | No | Invalid input, missing fields |
| Authentication | 401 | No (re-auth) | Expired token, invalid credentials |
| Authorization | 403 | No | Insufficient permissions |
| Not Found | 404 | No | Resource doesn't exist |
| Conflict | 409 | Maybe | Duplicate, version mismatch |
| Rate Limited | 429 | Yes (after delay) | Too many requests |
| Server Error | 500 | Yes | Unexpected failure |
| Upstream Error | 502, 503, 504 | Yes | Dependency failure |

### Global Error Handler

```typescript
// Centralized error handling middleware
class AppError extends Error {
  constructor(
    message: string,
    public statusCode: number,
    public code: string,
    public isOperational: boolean = true,
    public details?: unknown,
  ) {
    super(message);
  }
}

// Express error handler
app.use((err, req, res, next) => {
  // Log all errors
  logger.error('Request failed', {
    error: err.message,
    code: err.code,
    statusCode: err.statusCode || 500,
    stack: err.stack,
    requestId: req.requestId,
    path: req.path,
    method: req.method,
  });

  // Operational errors: send details to client
  if (err instanceof AppError && err.isOperational) {
    return res.status(err.statusCode).json({
      error: {
        code: err.code,
        message: err.message,
        details: err.details,
        request_id: req.requestId,
      },
    });
  }

  // Programming errors: generic message
  res.status(500).json({
    error: {
      code: 'INTERNAL_ERROR',
      message: 'An unexpected error occurred',
      request_id: req.requestId,
    },
  });
});
```

---

## Graceful Shutdown

```typescript
const server = app.listen(PORT);

async function gracefulShutdown(signal) {
  logger.info(`${signal} received, starting graceful shutdown`);

  // 1. Stop accepting new requests
  server.close();

  // 2. Wait for in-flight requests (with timeout)
  const shutdownTimeout = setTimeout(() => {
    logger.error('Shutdown timeout, forcing exit');
    process.exit(1);
  }, 30000);

  try {
    // 3. Close database connections
    await db.$disconnect();

    // 4. Close Redis connections
    await redis.quit();

    // 5. Drain message queue consumers
    await messageQueue.close();

    clearTimeout(shutdownTimeout);
    logger.info('Graceful shutdown complete');
    process.exit(0);
  } catch (err) {
    logger.error('Error during shutdown', { error: err.message });
    process.exit(1);
  }
}

process.on('SIGTERM', () => gracefulShutdown('SIGTERM'));
process.on('SIGINT', () => gracefulShutdown('SIGINT'));
```
