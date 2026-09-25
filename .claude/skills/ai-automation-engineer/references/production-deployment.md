# Production Deployment for AI Automation

Deployment patterns, scaling, reliability, and operational excellence.

---

## Deployment Architecture

### Basic Production Setup

```
[Load Balancer]
     │
     ├── [API Gateway] ← Rate Limiting, Auth
     │       │
     │   [AI Service]
     │       ├── [LLM Provider] (Anthropic/OpenAI)
     │       ├── [Vector Store] (Pinecone/Weaviate/pgvector)
     │       ├── [Cache] (Redis)
     │       └── [Queue] (RabbitMQ/SQS)
     │
     └── [Monitoring]
             ├── [Metrics] (Prometheus/Datadog)
             ├── [Traces] (LangSmith/Jaeger)
             └── [Alerts] (PagerDuty/OpsGenie)
```

---

## Reliability Patterns

### Circuit Breaker

```python
from enum import Enum
from dataclasses import dataclass
import time

class CircuitState(Enum):
    CLOSED = "closed"       # Normal operation
    OPEN = "open"           # Failing, reject requests
    HALF_OPEN = "half_open" # Testing recovery

@dataclass
class CircuitBreaker:
    failure_threshold: int = 5
    recovery_timeout: float = 60.0  # seconds
    half_open_max_calls: int = 3

    _state: CircuitState = CircuitState.CLOSED
    _failure_count: int = 0
    _last_failure_time: float = 0
    _half_open_calls: int = 0

    def can_execute(self) -> bool:
        if self._state == CircuitState.CLOSED:
            return True
        if self._state == CircuitState.OPEN:
            if time.time() - self._last_failure_time > self.recovery_timeout:
                self._state = CircuitState.HALF_OPEN
                self._half_open_calls = 0
                return True
            return False
        if self._state == CircuitState.HALF_OPEN:
            return self._half_open_calls < self.half_open_max_calls

    def record_success(self):
        if self._state == CircuitState.HALF_OPEN:
            self._half_open_calls += 1
            if self._half_open_calls >= self.half_open_max_calls:
                self._state = CircuitState.CLOSED
                self._failure_count = 0
        self._failure_count = 0

    def record_failure(self):
        self._failure_count += 1
        self._last_failure_time = time.time()
        if self._failure_count >= self.failure_threshold:
            self._state = CircuitState.OPEN
        if self._state == CircuitState.HALF_OPEN:
            self._state = CircuitState.OPEN

# Usage
llm_circuit = CircuitBreaker(failure_threshold=5, recovery_timeout=60)

async def resilient_llm_call(**kwargs):
    if not llm_circuit.can_execute():
        # Fallback: return cached result or error
        return await get_fallback_response(**kwargs)

    try:
        result = await call_llm(**kwargs)
        llm_circuit.record_success()
        return result
    except Exception as e:
        llm_circuit.record_failure()
        raise
```

### Retry with Exponential Backoff

```python
import asyncio
import random

async def retry_with_backoff(
    func,
    max_retries: int = 3,
    base_delay: float = 1.0,
    max_delay: float = 60.0,
    retryable_exceptions: tuple = (RateLimitError, TimeoutError, ConnectionError),
):
    """Retry with exponential backoff and jitter."""
    for attempt in range(max_retries + 1):
        try:
            return await func()
        except retryable_exceptions as e:
            if attempt == max_retries:
                raise

            delay = min(base_delay * (2 ** attempt), max_delay)
            jitter = random.uniform(0, delay * 0.1)
            total_delay = delay + jitter

            logger.warning(f"Retry {attempt + 1}/{max_retries} after {total_delay:.1f}s: {e}")
            await asyncio.sleep(total_delay)
```

### Dead Letter Queue

```python
class DeadLetterQueue:
    """Store failed requests for later processing or investigation."""

    def __init__(self, store):
        self.store = store

    async def enqueue(self, request: dict, error: str, attempts: int):
        await self.store.push({
            "request": request,
            "error": error,
            "attempts": attempts,
            "timestamp": datetime.utcnow().isoformat(),
            "status": "pending",
        })
        logger.error("request_to_dlq", error=error, attempts=attempts)

    async def reprocess(self, batch_size: int = 10):
        """Retry failed requests from DLQ."""
        items = await self.store.pop(batch_size)
        results = []
        for item in items:
            try:
                result = await process_request(item["request"])
                results.append({"status": "success", "item": item})
            except Exception as e:
                if item.get("attempts", 0) < 5:
                    await self.enqueue(item["request"], str(e), item["attempts"] + 1)
                else:
                    logger.error("dlq_permanent_failure", item=item)
                results.append({"status": "failed", "item": item})
        return results
```

---

## Scaling Patterns

### Async Processing with Queues

```python
import asyncio
from dataclasses import dataclass

@dataclass
class WorkItem:
    id: str
    payload: dict
    priority: int = 0
    created_at: float = 0

class AsyncWorkerPool:
    """Process AI tasks asynchronously with configurable concurrency."""

    def __init__(self, max_workers: int = 5):
        self.max_workers = max_workers
        self.queue: asyncio.PriorityQueue = asyncio.PriorityQueue()
        self._workers: list[asyncio.Task] = []

    async def start(self):
        self._workers = [
            asyncio.create_task(self._worker(i))
            for i in range(self.max_workers)
        ]

    async def submit(self, item: WorkItem):
        await self.queue.put((item.priority, item))

    async def _worker(self, worker_id: int):
        while True:
            _, item = await self.queue.get()
            try:
                await process_item(item)
            except Exception as e:
                logger.error(f"Worker {worker_id} failed: {e}")
                await dlq.enqueue(item.payload, str(e), 1)
            finally:
                self.queue.task_done()
```

### Horizontal Scaling Considerations

| Component | Scaling Strategy | Bottleneck |
|-----------|-----------------|------------|
| API Gateway | Horizontal (stateless) | Network |
| AI Service | Horizontal + queue | LLM rate limits |
| Vector Store | Read replicas | Write throughput |
| Cache | Redis Cluster | Memory |
| Queue | Partitioning | Consumer throughput |

---

## Health Checks

```python
from fastapi import FastAPI
from datetime import datetime

app = FastAPI()

@app.get("/health")
async def health_check():
    checks = {
        "llm_provider": await check_llm_health(),
        "vector_store": await check_vector_store_health(),
        "cache": await check_cache_health(),
        "queue": await check_queue_health(),
    }

    all_healthy = all(c["status"] == "healthy" for c in checks.values())

    return {
        "status": "healthy" if all_healthy else "degraded",
        "timestamp": datetime.utcnow().isoformat(),
        "checks": checks,
    }

async def check_llm_health() -> dict:
    try:
        start = time.monotonic()
        await call_llm(
            model="claude-haiku-4-5-20251001",
            messages=[{"role": "user", "content": "ping"}],
            max_tokens=5,
        )
        latency = (time.monotonic() - start) * 1000
        return {"status": "healthy", "latency_ms": latency}
    except Exception as e:
        return {"status": "unhealthy", "error": str(e)}
```

---

## Deployment Strategies

### Blue-Green Deployment

```
1. Deploy new version to Green environment
2. Run smoke tests on Green
3. Run eval suite on Green (compare with Blue baseline)
4. If evals pass: Switch traffic Green ← → Blue
5. If evals fail: Keep traffic on Blue, investigate
6. Keep Blue as rollback target for 24h
```

### Canary Deployment

```python
class CanaryRouter:
    """Route percentage of traffic to new version."""

    def __init__(self, canary_percentage: float = 5.0):
        self.canary_pct = canary_percentage

    def should_use_canary(self, request_id: str) -> bool:
        # Deterministic routing based on request ID
        hash_val = int(hashlib.md5(request_id.encode()).hexdigest(), 16)
        return (hash_val % 1000) < (self.canary_pct * 10)
```

### Feature Flags

```python
class FeatureFlags:
    """Control AI feature rollout."""

    FLAGS = {
        "new_prompt_v2": {"enabled": False, "rollout_pct": 0},
        "multi_agent_mode": {"enabled": True, "rollout_pct": 50},
        "advanced_rag": {"enabled": True, "rollout_pct": 100},
    }

    @classmethod
    def is_enabled(cls, flag: str, user_id: str = "") -> bool:
        config = cls.FLAGS.get(flag, {"enabled": False})
        if not config["enabled"]:
            return False
        if config.get("rollout_pct", 100) < 100:
            hash_val = int(hashlib.md5(f"{flag}:{user_id}".encode()).hexdigest(), 16)
            return (hash_val % 100) < config["rollout_pct"]
        return True
```

---

## Kill Switch

```python
class KillSwitch:
    """Emergency shutdown for AI systems."""

    def __init__(self, store):
        self.store = store  # Redis or similar

    async def is_killed(self, service: str = "default") -> bool:
        return await self.store.get(f"killswitch:{service}") == "true"

    async def kill(self, service: str = "default", reason: str = ""):
        await self.store.set(f"killswitch:{service}", "true")
        logger.critical("kill_switch_activated", service=service, reason=reason)
        await alert_oncall(f"Kill switch activated for {service}: {reason}")

    async def revive(self, service: str = "default"):
        await self.store.delete(f"killswitch:{service}")
        logger.info("kill_switch_deactivated", service=service)

# Middleware
async def check_kill_switch(request):
    if await kill_switch.is_killed():
        return {"error": "Service temporarily unavailable", "status": 503}
```

---

## Operational Runbook Checklist

### Pre-Deployment
- [ ] Eval suite passes (>= baseline scores)
- [ ] Load test completed (meets throughput requirements)
- [ ] Cost estimate within budget
- [ ] Rollback plan documented
- [ ] Kill switch tested

### Post-Deployment (First 24h)
- [ ] Monitor error rates (< 1%)
- [ ] Monitor latency (p99 < SLA)
- [ ] Monitor cost (< daily budget)
- [ ] Check eval metrics (no regression)
- [ ] Verify health checks passing
- [ ] Review sample outputs for quality

### Incident Response
1. Check kill switch necessity (data leak, safety issue → kill immediately)
2. Check circuit breaker status
3. Check LLM provider status page
4. Review recent deploys / prompt changes
5. Check rate limits and quota
6. Review error logs with trace IDs
7. Escalate if not resolved in 15 minutes
