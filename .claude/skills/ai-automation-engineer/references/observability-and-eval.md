# Observability & Evaluation for AI Systems

Tracing, logging, metrics, and evaluation patterns for production AI automation.

---

## Observability Stack

```
AI System → [Tracing] → [Logging] → [Metrics] → [Dashboards] → [Alerts]
               │            │           │
               └── LangSmith/Langfuse/OpenTelemetry ──→ Monitoring
```

---

## Distributed Tracing

### Trace Structure for AI Pipelines

```
Trace: user_request_abc123
├── Span: route_request (12ms)
├── Span: retrieve_context (340ms)
│   ├── Span: embed_query (45ms)
│   ├── Span: vector_search (120ms)
│   └── Span: rerank (175ms)
├── Span: llm_call (1850ms)
│   ├── Attribute: model=claude-sonnet-4-5
│   ├── Attribute: input_tokens=1200
│   ├── Attribute: output_tokens=450
│   ├── Attribute: cost=$0.0054
│   └── Attribute: temperature=0.0
├── Span: validate_output (25ms)
└── Span: format_response (5ms)
```

### Implementation with OpenTelemetry

```python
from opentelemetry import trace
from opentelemetry.sdk.trace import TracerProvider
from opentelemetry.sdk.trace.export import BatchSpanProcessor
import time

tracer = trace.get_tracer("ai-automation")

class AITracer:
    """Standardized tracing for AI operations."""

    @staticmethod
    def trace_llm_call(func):
        async def wrapper(*args, **kwargs):
            with tracer.start_as_current_span("llm_call") as span:
                span.set_attribute("ai.model", kwargs.get("model", "unknown"))
                span.set_attribute("ai.temperature", kwargs.get("temperature", 0))

                start = time.monotonic()
                try:
                    result = await func(*args, **kwargs)
                    elapsed = time.monotonic() - start

                    span.set_attribute("ai.input_tokens", result.usage.input_tokens)
                    span.set_attribute("ai.output_tokens", result.usage.output_tokens)
                    span.set_attribute("ai.latency_ms", elapsed * 1000)
                    span.set_attribute("ai.cost", calculate_cost(result.usage))
                    span.set_attribute("ai.stop_reason", result.stop_reason)

                    return result
                except Exception as e:
                    span.set_attribute("error", True)
                    span.set_attribute("error.message", str(e))
                    span.record_exception(e)
                    raise
        return wrapper

    @staticmethod
    def trace_tool_call(func):
        async def wrapper(tool_name: str, tool_input: dict, *args, **kwargs):
            with tracer.start_as_current_span(f"tool:{tool_name}") as span:
                span.set_attribute("tool.name", tool_name)
                span.set_attribute("tool.input_keys", list(tool_input.keys()))

                start = time.monotonic()
                try:
                    result = await func(tool_name, tool_input, *args, **kwargs)
                    elapsed = time.monotonic() - start

                    span.set_attribute("tool.success", result.success)
                    span.set_attribute("tool.latency_ms", elapsed * 1000)
                    return result
                except Exception as e:
                    span.set_attribute("error", True)
                    span.record_exception(e)
                    raise
        return wrapper
```

---

## Logging Standards

### Structured Logging

```python
import structlog
import json

logger = structlog.get_logger()

# LLM call logging
logger.info("llm_call_complete",
    model="claude-sonnet-4-5",
    input_tokens=1200,
    output_tokens=450,
    latency_ms=1850,
    cost_usd=0.0054,
    trace_id="abc123",
    stop_reason="end_turn",
)

# Agent step logging
logger.info("agent_step",
    agent="researcher",
    step=3,
    action="tool_use",
    tool="web_search",
    query="AI automation trends 2025",
    trace_id="abc123",
)

# Error logging (with PII redaction)
logger.error("llm_call_failed",
    model="claude-sonnet-4-5",
    error_type="RateLimitError",
    retry_count=2,
    trace_id="abc123",
    # Never log: user input, PII, API keys
)
```

### PII Redaction

```python
import re

PII_PATTERNS = {
    "email": r'\b[\w.-]+@[\w.-]+\.\w+\b',
    "phone": r'\b\d{3}[-.]?\d{3}[-.]?\d{4}\b',
    "ssn": r'\b\d{3}-\d{2}-\d{4}\b',
    "credit_card": r'\b\d{4}[-\s]?\d{4}[-\s]?\d{4}[-\s]?\d{4}\b',
}

def redact_pii(text: str) -> str:
    for pii_type, pattern in PII_PATTERNS.items():
        text = re.sub(pattern, f'[REDACTED_{pii_type.upper()}]', text)
    return text

# Use in logging
logger.info("user_query", query=redact_pii(user_input))
```

---

## Metrics & Dashboards

### Key Metrics

| Category | Metric | Type | Alert Threshold |
|----------|--------|------|----------------|
| **Latency** | LLM call p50/p95/p99 | Histogram | p99 > 10s |
| **Tokens** | Input/output tokens per request | Counter | Spike > 3x baseline |
| **Cost** | Cost per request / per hour / per day | Gauge | Daily > budget |
| **Errors** | Error rate by type | Counter | > 5% in 5min window |
| **Quality** | Output validation pass rate | Gauge | < 90% |
| **Throughput** | Requests per minute | Counter | Sudden drop > 50% |
| **Agent** | Average iterations per task | Histogram | > max_iterations * 0.8 |
| **Tools** | Tool success rate by tool | Gauge | < 95% |

### Token & Cost Tracking

```python
from dataclasses import dataclass, field
from collections import defaultdict

# Model pricing (per million tokens, as of 2025)
MODEL_PRICING = {
    "claude-opus-4-6": {"input": 15.0, "output": 75.0},
    "claude-sonnet-4-5-20250929": {"input": 3.0, "output": 15.0},
    "claude-haiku-4-5-20251001": {"input": 0.80, "output": 4.0},
}

@dataclass
class UsageTracker:
    total_input_tokens: int = 0
    total_output_tokens: int = 0
    total_cost_usd: float = 0.0
    calls_by_model: dict = field(default_factory=lambda: defaultdict(int))
    cost_by_model: dict = field(default_factory=lambda: defaultdict(float))

    def record(self, model: str, input_tokens: int, output_tokens: int):
        pricing = MODEL_PRICING.get(model, MODEL_PRICING["claude-sonnet-4-5-20250929"])
        cost = (input_tokens * pricing["input"] + output_tokens * pricing["output"]) / 1_000_000

        self.total_input_tokens += input_tokens
        self.total_output_tokens += output_tokens
        self.total_cost_usd += cost
        self.calls_by_model[model] += 1
        self.cost_by_model[model] += cost

    def report(self) -> dict:
        return {
            "total_tokens": self.total_input_tokens + self.total_output_tokens,
            "total_cost_usd": round(self.total_cost_usd, 4),
            "calls_by_model": dict(self.calls_by_model),
            "cost_by_model": {k: round(v, 4) for k, v in self.cost_by_model.items()},
        }
```

---

## Evaluation Framework

### Evaluation Types

| Type | What It Tests | When to Use |
|------|--------------|-------------|
| **Unit Evals** | Single LLM call output quality | Every prompt change |
| **Integration Evals** | End-to-end pipeline output | Feature releases |
| **Regression Evals** | No quality degradation | Before deployment |
| **A/B Evals** | Compare two approaches | Architecture decisions |
| **Human Evals** | Subjective quality assessment | Periodic sampling |

### Evaluation Implementation

```python
from dataclasses import dataclass
from enum import Enum

class EvalResult(str, Enum):
    PASS = "pass"
    FAIL = "fail"
    PARTIAL = "partial"

@dataclass
class EvalCase:
    input: str
    expected_output: str | None = None  # For deterministic
    criteria: list[str] | None = None    # For LLM-judge
    tags: list[str] | None = None        # For filtering

@dataclass
class EvalScore:
    case_id: str
    result: EvalResult
    score: float  # 0.0 - 1.0
    details: dict

class EvalSuite:
    def __init__(self, name: str):
        self.name = name
        self.cases: list[EvalCase] = []
        self.results: list[EvalScore] = []

    def add_case(self, case: EvalCase):
        self.cases.append(case)

    async def run(self, pipeline_fn, judge_fn=None) -> dict:
        for i, case in enumerate(self.cases):
            output = await pipeline_fn(case.input)

            if case.expected_output:
                # Deterministic evaluation
                score = self._exact_match(output, case.expected_output)
            elif case.criteria and judge_fn:
                # LLM-as-judge evaluation
                score = await judge_fn(case.input, output, case.criteria)
            else:
                score = EvalScore(case_id=str(i), result=EvalResult.PARTIAL, score=0.5, details={})

            self.results.append(score)

        return self._summary()

    def _summary(self) -> dict:
        total = len(self.results)
        passed = sum(1 for r in self.results if r.result == EvalResult.PASS)
        avg_score = sum(r.score for r in self.results) / total if total else 0

        return {
            "suite": self.name,
            "total": total,
            "passed": passed,
            "pass_rate": passed / total if total else 0,
            "avg_score": round(avg_score, 3),
        }
```

### LLM-as-Judge Pattern

```python
JUDGE_PROMPT = """Evaluate the AI assistant's response based on these criteria:

## Input
{input}

## Response
{response}

## Criteria
{criteria}

## Scoring
For each criterion, score 0-5:
- 5: Excellent - Fully meets criterion
- 4: Good - Minor gaps
- 3: Acceptable - Some issues
- 2: Poor - Significant issues
- 1: Very Poor - Major issues
- 0: Fail - Does not meet criterion

Return JSON:
{{
  "scores": {{"criterion_name": score}},
  "overall": weighted_average,
  "reasoning": "brief explanation"
}}"""

async def llm_judge(input_text: str, response: str, criteria: list[str]) -> EvalScore:
    judgment = await call_llm(
        model="claude-sonnet-4-5-20250929",  # Use capable model as judge
        prompt=JUDGE_PROMPT.format(
            input=input_text,
            response=response,
            criteria="\n".join(f"- {c}" for c in criteria),
        ),
    )
    parsed = json.loads(judgment)
    score = parsed["overall"] / 5.0  # Normalize to 0-1

    return EvalScore(
        case_id="",
        result=EvalResult.PASS if score >= 0.7 else EvalResult.FAIL,
        score=score,
        details=parsed,
    )
```

### Regression Testing

```python
class RegressionSuite:
    """Compare new pipeline version against baseline."""

    def __init__(self, baseline_results: dict):
        self.baseline = baseline_results
        self.tolerance = 0.05  # Allow 5% degradation

    async def compare(self, new_results: dict) -> dict:
        regressions = []
        improvements = []

        for metric, baseline_value in self.baseline.items():
            new_value = new_results.get(metric, 0)
            delta = new_value - baseline_value

            if delta < -self.tolerance:
                regressions.append({
                    "metric": metric,
                    "baseline": baseline_value,
                    "new": new_value,
                    "delta": delta,
                })
            elif delta > self.tolerance:
                improvements.append({
                    "metric": metric,
                    "baseline": baseline_value,
                    "new": new_value,
                    "delta": delta,
                })

        return {
            "passed": len(regressions) == 0,
            "regressions": regressions,
            "improvements": improvements,
        }
```

---

## Observability Anti-Patterns

| Anti-Pattern | Problem | Fix |
|-------------|---------|-----|
| Logging PII | Privacy violation, compliance risk | Redact before logging |
| No trace correlation | Can't debug multi-step failures | Propagate trace IDs |
| Logging everything | Storage costs, noise | Structured logging, sampling |
| No cost tracking | Budget overruns | Track per-request and aggregate |
| Missing error context | Unhelpful alerts | Include model, prompt hash, input metadata |
| No baseline metrics | Can't detect regressions | Establish baselines before deploying |
