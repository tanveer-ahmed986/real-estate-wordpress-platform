# Cost Optimization for AI Automation

Token optimization, caching, model routing, and budget control patterns.

---

## Cost Optimization Strategies

```
[Reduce Tokens] + [Cache Results] + [Route to Cheaper Models] + [Batch Processing] = Cost Control
```

---

## Token Optimization

### Prompt Compression

```python
def compress_context(context: str, max_tokens: int = 2000) -> str:
    """Compress context to fit within token budget."""
    estimated_tokens = len(context) // 4  # Rough estimate

    if estimated_tokens <= max_tokens:
        return context

    # Strategy 1: Remove redundant whitespace
    compressed = re.sub(r'\n{3,}', '\n\n', context)
    compressed = re.sub(r' {2,}', ' ', compressed)

    # Strategy 2: Truncate with summary
    if len(compressed) // 4 > max_tokens:
        # Keep first and last portions, summarize middle
        keep_chars = max_tokens * 4
        first_half = compressed[:keep_chars // 2]
        last_half = compressed[-keep_chars // 2:]
        compressed = f"{first_half}\n\n[... middle content omitted ...]\n\n{last_half}"

    return compressed

# Strategy 3: LLM-based summarization for repeated contexts
async def summarize_for_context(text: str, focus: str) -> str:
    """Summarize text focusing on relevant aspects."""
    return await call_llm(
        model="claude-haiku-4-5-20251001",  # Cheap model for summarization
        prompt=f"Summarize the following text, focusing on aspects relevant to: {focus}\n\n{text}",
        max_tokens=500,
    )
```

### Efficient Prompt Design

| Technique | Token Savings | Example |
|-----------|--------------|---------|
| Remove filler words | 10-20% | "Please analyze" → "Analyze" |
| Use abbreviations in system | 5-10% | Define abbrevs in system prompt |
| Structured output schema | 15-30% | JSON schema vs verbose instructions |
| Few-shot → zero-shot | 50-80% of examples | Remove examples if quality holds |
| Dynamic few-shot | 30-50% of examples | Select only relevant examples |

### Context Window Management

```python
class ContextManager:
    """Manage conversation context within token budgets."""

    def __init__(self, max_context_tokens: int = 8000):
        self.max_tokens = max_context_tokens
        self.messages: list[dict] = []

    def add_message(self, message: dict):
        self.messages.append(message)
        self._trim_if_needed()

    def _trim_if_needed(self):
        total = self._estimate_tokens()
        if total <= self.max_tokens:
            return

        # Strategy: Keep system + first message + recent messages
        system = self.messages[0] if self.messages[0]["role"] == "system" else None
        first_user = next((m for m in self.messages if m["role"] == "user"), None)

        # Remove oldest non-essential messages
        while self._estimate_tokens() > self.max_tokens and len(self.messages) > 3:
            # Remove second message (keep system + first + recent)
            self.messages.pop(1)

    def _estimate_tokens(self) -> int:
        return sum(len(str(m.get("content", ""))) // 4 for m in self.messages)
```

---

## Caching Strategies

### Response Caching

```python
import hashlib
import json
from datetime import datetime, timedelta

class LLMCache:
    """Cache LLM responses for identical or similar requests."""

    def __init__(self, store, ttl_hours: int = 24):
        self.store = store  # Redis, SQLite, etc.
        self.ttl = timedelta(hours=ttl_hours)

    def _cache_key(self, model: str, messages: list, temperature: float) -> str:
        """Generate deterministic cache key."""
        content = json.dumps({
            "model": model,
            "messages": messages,
            "temperature": temperature,
        }, sort_keys=True)
        return f"llm:{hashlib.sha256(content.encode()).hexdigest()}"

    async def get_or_call(self, model: str, messages: list, temperature: float = 0.0, **kwargs):
        # Only cache deterministic calls (temperature=0)
        if temperature > 0:
            return await call_llm(model=model, messages=messages, temperature=temperature, **kwargs)

        key = self._cache_key(model, messages, temperature)
        cached = await self.store.get(key)

        if cached:
            logger.info("cache_hit", key=key[:16])
            return json.loads(cached)

        result = await call_llm(model=model, messages=messages, temperature=temperature, **kwargs)
        await self.store.set(key, json.dumps(result), ex=int(self.ttl.total_seconds()))
        return result
```

### Semantic Caching

```python
class SemanticCache:
    """Cache based on semantic similarity of queries."""

    def __init__(self, vector_store, similarity_threshold: float = 0.95):
        self.vector_store = vector_store
        self.threshold = similarity_threshold

    async def get_or_call(self, query: str, call_fn):
        query_embedding = await embed(query)

        # Search for similar cached queries
        results = await self.vector_store.search(query_embedding, top_k=1)

        if results and results[0].score >= self.threshold:
            logger.info("semantic_cache_hit", similarity=results[0].score)
            return results[0].metadata["response"]

        # Cache miss - make the call
        response = await call_fn(query)

        # Store in cache
        await self.vector_store.upsert({
            "embedding": query_embedding,
            "metadata": {"query": query, "response": response, "created": datetime.utcnow().isoformat()},
        })

        return response
```

---

## Model Routing

### Complexity-Based Routing

```python
class ModelRouter:
    """Route requests to appropriate model based on complexity."""

    MODELS = {
        "simple": "claude-haiku-4-5-20251001",      # $0.80/$4.00 per 1M tokens
        "standard": "claude-sonnet-4-5-20250929",    # $3.00/$15.00 per 1M tokens
        "complex": "claude-opus-4-6",                # $15.00/$75.00 per 1M tokens
    }

    async def route(self, task: str, context: dict = None) -> str:
        complexity = await self._assess_complexity(task)
        return self.MODELS[complexity]

    async def _assess_complexity(self, task: str) -> str:
        # Fast classification with cheapest model
        result = await call_llm(
            model=self.MODELS["simple"],
            prompt=f"""Classify task complexity as simple/standard/complex:

Task: {task}

- simple: Classification, extraction, formatting, simple Q&A
- standard: Analysis, summarization, code generation, multi-step reasoning
- complex: Novel problem solving, creative writing, complex code architecture

Respond with ONE word: simple, standard, or complex""",
            max_tokens=10,
        )
        complexity = result.strip().lower()
        return complexity if complexity in self.MODELS else "standard"
```

### Fallback Chain

```python
class ModelFallbackChain:
    """Try models in order, falling back on failure."""

    def __init__(self, models: list[str]):
        self.models = models  # Ordered by preference

    async def call(self, **kwargs) -> dict:
        last_error = None

        for model in self.models:
            try:
                return await call_llm(model=model, **kwargs)
            except RateLimitError:
                logger.warning(f"Rate limited on {model}, trying next")
                last_error = "rate_limited"
            except Exception as e:
                logger.error(f"Error on {model}: {e}")
                last_error = str(e)

        raise Exception(f"All models failed. Last error: {last_error}")

# Usage
fallback = ModelFallbackChain([
    "claude-sonnet-4-5-20250929",   # Primary
    "claude-haiku-4-5-20251001",    # Fallback (cheaper, faster)
])
```

---

## Batch Processing

### Batch API Usage

```python
async def batch_process(items: list[str], batch_size: int = 100) -> list[dict]:
    """Process items using batch API for 50% cost savings."""
    results = []

    for i in range(0, len(items), batch_size):
        batch = items[i:i + batch_size]

        # Submit batch
        batch_job = await client.batches.create(
            requests=[
                {
                    "custom_id": f"item-{i+j}",
                    "params": {
                        "model": "claude-sonnet-4-5-20250929",
                        "max_tokens": 1024,
                        "messages": [{"role": "user", "content": item}],
                    }
                }
                for j, item in enumerate(batch)
            ]
        )

        # Poll for completion
        while True:
            status = await client.batches.retrieve(batch_job.id)
            if status.processing_status == "ended":
                break
            await asyncio.sleep(10)

        results.extend(status.results)

    return results
```

---

## Cost Monitoring Dashboard Metrics

| Metric | Calculation | Alert |
|--------|------------|-------|
| Cost per request | Total cost / request count | > $0.50/request |
| Daily spend | Sum of all costs today | > 80% of daily budget |
| Cost per user | Total cost / active users | > $5/user/day |
| Cache hit rate | Cache hits / total requests | < 30% (optimization opportunity) |
| Token efficiency | Useful output tokens / total tokens | < 50% (prompt too verbose) |
| Model distribution | % requests per model tier | > 20% on expensive tier |

---

## Quick Wins Checklist

- [ ] Set temperature=0 for deterministic tasks (enables caching)
- [ ] Use Haiku for classification, routing, simple extraction
- [ ] Implement response caching for repeated queries
- [ ] Compress/truncate context before sending to LLM
- [ ] Use structured output (saves output tokens vs verbose text)
- [ ] Batch non-urgent processing (50% cost savings)
- [ ] Remove unnecessary few-shot examples
- [ ] Set appropriate max_tokens (don't overprovision)
- [ ] Monitor and alert on cost anomalies
- [ ] Route simple tasks to cheaper models automatically
