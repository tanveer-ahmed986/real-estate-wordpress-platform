# Observability & Production Deployment

Patterns for monitoring, tracing, and deploying AI agent systems at scale.

---

## Observability Stack

```
┌─────────────────────────────────────────────┐
│              Agent Application               │
│  ┌─────────┐ ┌──────────┐ ┌──────────────┐ │
│  │ Logging │ │ Tracing  │ │   Metrics    │ │
│  └────┬────┘ └────┬─────┘ └──────┬───────┘ │
└───────┼───────────┼──────────────┼──────────┘
        │           │              │
   ┌────▼────┐ ┌────▼─────┐ ┌─────▼──────┐
   │Logstash │ │LangSmith │ │Prometheus  │
   │/Loki    │ │/Jaeger   │ │/Datadog    │
   └────┬────┘ └────┬─────┘ └─────┬──────┘
        │           │              │
   ┌────▼───────────▼──────────────▼──────┐
   │           Grafana / Dashboard         │
   └───────────────────────────────────────┘
```

---

## Structured Logging

```python
import structlog

# Configure structured logging
structlog.configure(
    processors=[
        structlog.processors.TimeStamper(fmt="iso"),
        structlog.processors.add_log_level,
        structlog.processors.JSONRenderer()
    ]
)
logger = structlog.get_logger()

class AgentLogger:
    def __init__(self, agent_id: str, run_id: str):
        self.log = logger.bind(agent_id=agent_id, run_id=run_id)

    def step_start(self, step: int, action: str):
        self.log.info("agent.step.start", step=step, action=action)

    def tool_call(self, tool: str, params: dict, duration_ms: float, success: bool):
        self.log.info("agent.tool",
            tool=tool, params=params, duration_ms=duration_ms, success=success)

    def llm_call(self, model: str, input_tokens: int, output_tokens: int, duration_ms: float):
        self.log.info("agent.llm",
            model=model, input_tokens=input_tokens, output_tokens=output_tokens,
            duration_ms=duration_ms, cost_usd=self._calc_cost(model, input_tokens, output_tokens))

    def error(self, error_type: str, message: str, recoverable: bool):
        self.log.error("agent.error",
            error_type=error_type, message=message, recoverable=recoverable)

    def run_complete(self, steps: int, total_tokens: int, total_cost: float, success: bool):
        self.log.info("agent.run.complete",
            steps=steps, total_tokens=total_tokens, total_cost_usd=total_cost, success=success)
```

---

## Distributed Tracing

### OpenTelemetry Integration

```python
from opentelemetry import trace
from opentelemetry.sdk.trace import TracerProvider
from opentelemetry.sdk.trace.export import BatchSpanProcessor
from opentelemetry.exporter.otlp.proto.grpc.trace_exporter import OTLPSpanExporter

# Setup
provider = TracerProvider()
provider.add_span_processor(BatchSpanProcessor(OTLPSpanExporter()))
trace.set_tracer_provider(provider)
tracer = trace.get_tracer("ai-agent-system")

class TracedAgent:
    def __init__(self, agent, tracer):
        self.agent = agent
        self.tracer = tracer

    async def run(self, task: str):
        with self.tracer.start_as_current_span("agent.run") as span:
            span.set_attribute("agent.name", self.agent.name)
            span.set_attribute("agent.task", task[:200])

            result = await self.agent.invoke(task)

            span.set_attribute("agent.steps", result.step_count)
            span.set_attribute("agent.tokens", result.total_tokens)
            span.set_attribute("agent.success", result.success)
            return result

    async def call_tool(self, tool_name: str, params: dict):
        with self.tracer.start_as_current_span("agent.tool_call") as span:
            span.set_attribute("tool.name", tool_name)
            start = time.time()
            result = await self.agent.tools[tool_name](**params)
            span.set_attribute("tool.duration_ms", (time.time() - start) * 1000)
            return result
```

### LangSmith Tracing (LangGraph)

```python
# Set environment variables
# LANGSMITH_API_KEY=<key>
# LANGSMITH_PROJECT=<project>
# LANGSMITH_TRACING=true

# LangGraph automatically traces when LangSmith is configured
# All tool calls, LLM calls, and state transitions are captured

# Custom trace metadata
from langsmith import traceable

@traceable(run_type="chain", name="agent_workflow")
def my_agent_workflow(input: str):
    result = graph.invoke({"input": input})
    return result
```

### OpenAI Agents SDK Tracing

```python
# Built-in tracing
from agents.tracing import set_tracing_export_api_key, TracingProcessor

# Automatic tracing to OpenAI dashboard
set_tracing_export_api_key("sk-...")

# Custom trace processor
class CustomProcessor(TracingProcessor):
    def on_trace_start(self, trace):
        logger.info("trace.start", trace_id=trace.id)

    def on_span_end(self, span):
        if span.type == "tool_call":
            logger.info("tool.complete", tool=span.name, duration=span.duration)
```

---

## Key Metrics to Track

### Agent-Level Metrics

| Metric | Type | Alert Threshold |
|--------|------|-----------------|
| `agent.run.duration_seconds` | Histogram | p99 > 60s |
| `agent.run.steps_count` | Histogram | p99 > 15 steps |
| `agent.run.success_rate` | Gauge | < 95% |
| `agent.run.tokens_total` | Counter | Budget threshold |
| `agent.run.cost_usd` | Counter | Daily limit |

### Tool-Level Metrics

| Metric | Type | Alert Threshold |
|--------|------|-----------------|
| `agent.tool.duration_seconds` | Histogram | p99 > 10s |
| `agent.tool.error_rate` | Gauge | > 5% |
| `agent.tool.calls_total` | Counter | Rate limit |
| `agent.tool.timeout_rate` | Gauge | > 2% |

### LLM-Level Metrics

| Metric | Type | Alert Threshold |
|--------|------|-----------------|
| `agent.llm.latency_seconds` | Histogram | p99 > 15s |
| `agent.llm.tokens_input` | Counter | Budget |
| `agent.llm.tokens_output` | Counter | Budget |
| `agent.llm.rate_limit_hits` | Counter | > 0/min |
| `agent.llm.error_rate` | Gauge | > 1% |

```python
# Prometheus metrics
from prometheus_client import Histogram, Counter, Gauge

agent_duration = Histogram("agent_run_duration_seconds", "Agent run duration",
    ["agent_name", "status"], buckets=[1, 5, 10, 30, 60, 120, 300])
agent_cost = Counter("agent_run_cost_usd", "Agent run cost", ["agent_name", "model"])
tool_errors = Counter("agent_tool_errors_total", "Tool call errors", ["agent_name", "tool"])
active_agents = Gauge("agent_active_runs", "Currently running agents", ["agent_name"])
```

---

## Production Deployment Patterns

### Queue-Based Architecture

```
┌──────────┐    ┌───────────┐    ┌──────────────┐    ┌──────────┐
│  Client   │───→│  Queue    │───→│ Agent Worker │───→│  Result  │
│  (API)    │    │(Redis/SQS)│    │  (Consumer)  │    │  Store   │
└──────────┘    └───────────┘    └──────────────┘    └──────────┘
                                       ↕
                                 ┌──────────┐
                                 │ Tool APIs│
                                 └──────────┘
```

```python
# Celery-based agent worker
from celery import Celery

app = Celery("agents", broker="redis://localhost:6379/0")

@app.task(bind=True, max_retries=2, soft_time_limit=300, time_limit=360)
def run_agent_task(self, task_input: dict):
    try:
        agent = create_agent(task_input["agent_type"])
        result = agent.invoke(task_input["input"])
        store_result(self.request.id, result)
        return {"status": "success", "result": result}
    except SoftTimeLimitExceeded:
        return {"status": "timeout", "partial_result": get_partial_state()}
    except Exception as e:
        if self.request.retries < self.max_retries:
            raise self.retry(exc=e, countdown=30)
        return {"status": "error", "error": str(e)}
```

### Container Deployment

```yaml
# docker-compose.yml for agent system
services:
  api:
    build: ./api
    ports: ["8000:8000"]
    environment:
      - REDIS_URL=redis://redis:6379
      - DATABASE_URL=postgres://db:5432/agents

  agent-worker:
    build: ./worker
    deploy:
      replicas: 3
      resources:
        limits:
          memory: 1G
          cpus: "1.0"
    environment:
      - REDIS_URL=redis://redis:6379
      - OPENAI_API_KEY=${OPENAI_API_KEY}
      - ANTHROPIC_API_KEY=${ANTHROPIC_API_KEY}

  redis:
    image: redis:7-alpine
    volumes: ["redis-data:/data"]

  db:
    image: postgres:16-alpine
    volumes: ["pg-data:/var/lib/postgresql/data"]
    environment:
      - POSTGRES_DB=agents

volumes:
  redis-data:
  pg-data:
```

### Kubernetes Deployment

```yaml
# Agent worker deployment with autoscaling
apiVersion: apps/v1
kind: Deployment
metadata:
  name: agent-worker
spec:
  replicas: 3
  template:
    spec:
      containers:
      - name: worker
        image: agent-worker:latest
        resources:
          requests: { memory: "512Mi", cpu: "500m" }
          limits: { memory: "1Gi", cpu: "1000m" }
        env:
        - name: OPENAI_API_KEY
          valueFrom:
            secretKeyRef: { name: llm-secrets, key: openai-key }
        livenessProbe:
          httpGet: { path: /health, port: 8080 }
          periodSeconds: 30
---
apiVersion: autoscaling/v2
kind: HorizontalPodAutoscaler
metadata:
  name: agent-worker-hpa
spec:
  scaleTargetRef:
    apiVersion: apps/v1
    kind: Deployment
    name: agent-worker
  minReplicas: 2
  maxReplicas: 10
  metrics:
  - type: External
    external:
      metric: { name: queue_depth }
      target: { type: AverageValue, averageValue: "5" }
```

---

## Resilience Patterns

### Circuit Breaker for LLM Calls

```python
from circuitbreaker import circuit

@circuit(failure_threshold=3, recovery_timeout=60, expected_exception=Exception)
async def call_llm(model: str, messages: list) -> str:
    """LLM call with circuit breaker. Opens after 3 failures, retries after 60s."""
    response = await client.chat.completions.create(model=model, messages=messages)
    return response.choices[0].message.content

# Fallback chain
async def resilient_llm_call(messages: list):
    models = ["claude-sonnet-4-20250514", "gpt-4o", "claude-haiku-4-5-20251001"]
    for model in models:
        try:
            return await call_llm(model, messages)
        except CircuitBreakerError:
            continue
    raise RuntimeError("All LLM providers unavailable")
```

### Retry with Exponential Backoff

```python
import tenacity

@tenacity.retry(
    stop=tenacity.stop_after_attempt(3),
    wait=tenacity.wait_exponential(multiplier=1, min=2, max=30),
    retry=tenacity.retry_if_exception_type((httpx.TimeoutException, httpx.HTTPStatusError)),
    before_sleep=lambda retry_state: logger.warning(
        "tool.retry", attempt=retry_state.attempt_number, wait=retry_state.next_action.sleep
    )
)
async def call_external_api(url: str, params: dict):
    resp = await client.get(url, params=params)
    resp.raise_for_status()
    return resp.json()
```

### Graceful Degradation

```python
class DegradationManager:
    """Progressively reduce agent capabilities under pressure."""

    levels = {
        "normal": {"max_tools": None, "model": "claude-sonnet-4-20250514", "max_steps": 15},
        "degraded": {"max_tools": 5, "model": "claude-haiku-4-5-20251001", "max_steps": 8},
        "minimal": {"max_tools": 2, "model": "claude-haiku-4-5-20251001", "max_steps": 3},
    }

    def __init__(self):
        self.level = "normal"

    def check_and_adjust(self, error_rate: float, latency_p99: float):
        if error_rate > 0.1 or latency_p99 > 30:
            self.level = "minimal"
        elif error_rate > 0.05 or latency_p99 > 15:
            self.level = "degraded"
        else:
            self.level = "normal"

    def get_config(self) -> dict:
        return self.levels[self.level]
```

---

## Scaling Strategies

| Strategy | When | How |
|----------|------|-----|
| **Horizontal worker scaling** | Queue depth increases | Add more agent worker instances |
| **Model tiering** | Cost optimization | Route simple tasks to cheaper models |
| **Caching** | Repeated queries | Cache tool results and LLM responses |
| **Batching** | High throughput | Batch similar tool calls together |
| **Async execution** | Long-running tasks | Return immediately, notify on completion |
| **Priority queues** | Mixed urgency | Separate queues for high/low priority |

### Caching Pattern

```python
from functools import lru_cache
import hashlib

class AgentCache:
    def __init__(self, redis_client, default_ttl: int = 300):
        self.redis = redis_client
        self.ttl = default_ttl

    async def cached_tool_call(self, tool_name: str, params: dict, ttl: int = None):
        """Cache idempotent tool call results."""
        key = f"tool:{tool_name}:{hashlib.md5(json.dumps(params, sort_keys=True).encode()).hexdigest()}"
        cached = await self.redis.get(key)
        if cached:
            return json.loads(cached)
        result = await execute_tool(tool_name, params)
        await self.redis.setex(key, ttl or self.ttl, json.dumps(result))
        return result
```

---

## A/B Testing Agent Configurations

```python
class AgentExperiment:
    def __init__(self, experiments: dict):
        self.experiments = experiments  # {"control": config_a, "variant": config_b}
        self.weights = {"control": 0.5, "variant": 0.5}

    def get_config(self, user_id: str) -> tuple[str, dict]:
        """Deterministic assignment based on user_id."""
        hash_val = int(hashlib.md5(user_id.encode()).hexdigest(), 16) % 100
        cumulative = 0
        for variant, weight in self.weights.items():
            cumulative += weight * 100
            if hash_val < cumulative:
                return variant, self.experiments[variant]
        return "control", self.experiments["control"]

    def log_result(self, user_id: str, variant: str, metrics: dict):
        """Log experiment results for analysis."""
        logger.info("experiment.result",
            user_id=user_id, variant=variant, **metrics)
```
