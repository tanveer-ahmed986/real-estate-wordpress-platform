# Tool Use & Function Calling Patterns

Production patterns for implementing AI agent tool interfaces.

---

## Tool Definition Best Practices

### Anthropic Tool Schema

```python
tools = [
    {
        "name": "search_products",  # snake_case, verb_noun
        "description": (
            "Search the product catalog by query string. "
            "Returns matching products with name, price, and availability. "
            "Use when the user asks about products, pricing, or inventory."
        ),
        "input_schema": {
            "type": "object",
            "properties": {
                "query": {
                    "type": "string",
                    "description": "Search query - supports product names, categories, and SKUs"
                },
                "category": {
                    "type": "string",
                    "description": "Filter by category",
                    "enum": ["electronics", "clothing", "food", "home"]
                },
                "max_results": {
                    "type": "integer",
                    "description": "Maximum number of results to return (1-50)",
                    "default": 10
                },
                "in_stock_only": {
                    "type": "boolean",
                    "description": "Only return products currently in stock",
                    "default": True
                }
            },
            "required": ["query"]
        }
    }
]
```

### Tool Description Guidelines

| Element | Good | Bad |
|---------|------|-----|
| Name | `search_database` | `tool1`, `doSearch` |
| Description | "Search customer database by name, email, or ID. Returns matching customer records." | "Searches stuff" |
| Parameters | Clear types, descriptions, enums, defaults | Missing descriptions |
| Required fields | Only truly required params | Everything required |

---

## Tool Implementation Pattern

### Robust Tool Executor

```python
import asyncio
import time
import logging
from typing import Any, Callable
from dataclasses import dataclass
from pydantic import BaseModel, ValidationError

logger = logging.getLogger(__name__)

@dataclass
class ToolResult:
    success: bool
    data: Any = None
    error: str | None = None
    execution_time_ms: float = 0
    tool_name: str = ""

class ToolExecutor:
    def __init__(self):
        self._tools: dict[str, Callable] = {}
        self._schemas: dict[str, type[BaseModel]] = {}
        self._timeout: float = 30.0
        self._max_retries: int = 2

    def register(self, name: str, func: Callable, input_schema: type[BaseModel] | None = None):
        self._tools[name] = func
        if input_schema:
            self._schemas[name] = input_schema

    async def execute(self, tool_name: str, tool_input: dict) -> ToolResult:
        start = time.monotonic()

        if tool_name not in self._tools:
            return ToolResult(
                success=False,
                error=f"Unknown tool: {tool_name}",
                tool_name=tool_name,
            )

        # Validate input
        if tool_name in self._schemas:
            try:
                validated = self._schemas[tool_name].model_validate(tool_input)
                tool_input = validated.model_dump()
            except ValidationError as e:
                return ToolResult(
                    success=False,
                    error=f"Invalid input: {e}",
                    tool_name=tool_name,
                )

        # Execute with timeout and retries
        last_error = None
        for attempt in range(self._max_retries + 1):
            try:
                result = await asyncio.wait_for(
                    self._tools[tool_name](**tool_input),
                    timeout=self._timeout,
                )
                elapsed = (time.monotonic() - start) * 1000
                logger.info(f"Tool {tool_name} succeeded in {elapsed:.0f}ms")
                return ToolResult(
                    success=True,
                    data=result,
                    execution_time_ms=elapsed,
                    tool_name=tool_name,
                )
            except asyncio.TimeoutError:
                last_error = f"Timeout after {self._timeout}s"
                logger.warning(f"Tool {tool_name} timed out (attempt {attempt + 1})")
            except Exception as e:
                last_error = str(e)
                logger.error(f"Tool {tool_name} failed (attempt {attempt + 1}): {e}")
                if attempt < self._max_retries:
                    await asyncio.sleep(0.5 * (2 ** attempt))

        elapsed = (time.monotonic() - start) * 1000
        return ToolResult(
            success=False,
            error=last_error,
            execution_time_ms=elapsed,
            tool_name=tool_name,
        )
```

---

## Tool Categories

### Information Retrieval Tools

```python
# Database query
async def query_database(sql: str, params: list | None = None) -> list[dict]:
    """Execute read-only SQL query with parameterized inputs."""
    ...

# API call
async def call_api(endpoint: str, method: str = "GET", body: dict | None = None) -> dict:
    """Call external API with automatic retry and rate limiting."""
    ...

# File read
async def read_file(path: str, encoding: str = "utf-8") -> str:
    """Read file contents with path validation."""
    path = validate_path(path)  # Prevent traversal
    ...

# Web search
async def web_search(query: str, num_results: int = 5) -> list[dict]:
    """Search the web and return structured results."""
    ...
```

### Action Tools

```python
# Send notification
async def send_notification(channel: str, message: str, urgency: str = "normal") -> dict:
    """Send notification via specified channel (email, slack, sms)."""
    ...

# Create record
async def create_record(table: str, data: dict) -> dict:
    """Create a new record in the database. Returns created record with ID."""
    ...

# Execute workflow
async def trigger_workflow(workflow_id: str, inputs: dict) -> dict:
    """Trigger an automation workflow with given inputs."""
    ...
```

### Computation Tools

```python
# Calculate
async def calculate(expression: str) -> float:
    """Safely evaluate mathematical expression."""
    # Use ast.literal_eval or restricted eval
    ...

# Transform data
async def transform_data(data: list[dict], operations: list[dict]) -> list[dict]:
    """Apply transformation operations to data."""
    ...

# Generate chart
async def generate_chart(data: list[dict], chart_type: str, config: dict) -> str:
    """Generate chart and return base64 image or URL."""
    ...
```

---

## Tool Orchestration Patterns

### Sequential Tool Use

```python
async def agent_loop(messages: list, tools: list, max_iterations: int = 10):
    """Standard sequential tool use loop."""
    for i in range(max_iterations):
        response = await call_llm(messages=messages, tools=tools)

        if response.stop_reason == "end_turn":
            return extract_text(response)

        if response.stop_reason == "tool_use":
            # Process ALL tool calls in the response
            tool_results = []
            for block in response.content:
                if block.type == "tool_use":
                    result = await tool_executor.execute(block.name, block.input)
                    tool_results.append({
                        "type": "tool_result",
                        "tool_use_id": block.id,
                        "content": format_result(result),
                    })

            messages.append({"role": "assistant", "content": response.content})
            messages.append({"role": "user", "content": tool_results})

    return "Max iterations reached."
```

### Parallel Tool Execution

```python
async def execute_parallel_tools(tool_calls: list[dict]) -> list[dict]:
    """Execute multiple tool calls in parallel."""
    tasks = [
        tool_executor.execute(call["name"], call["input"])
        for call in tool_calls
    ]
    results = await asyncio.gather(*tasks, return_exceptions=True)

    return [
        {
            "tool_use_id": call["id"],
            "content": format_result(r) if not isinstance(r, Exception) else f"Error: {r}",
            "is_error": isinstance(r, Exception),
        }
        for call, r in zip(tool_calls, results)
    ]
```

### Tool Result Formatting

```python
def format_result(result: ToolResult) -> str:
    """Format tool result for LLM consumption."""
    if result.success:
        if isinstance(result.data, (dict, list)):
            import json
            data_str = json.dumps(result.data, indent=2, default=str)
            # Truncate if too large
            if len(data_str) > 10000:
                data_str = data_str[:10000] + "\n... (truncated)"
            return data_str
        return str(result.data)
    else:
        return f"Error: {result.error}. Please try a different approach."
```

---

## Tool Safety

### Input Sanitization

```python
import re

def sanitize_tool_input(tool_name: str, inputs: dict) -> dict:
    """Sanitize tool inputs to prevent injection attacks."""
    sanitized = {}
    for key, value in inputs.items():
        if isinstance(value, str):
            # Remove potential command injection
            value = re.sub(r'[;&|`$]', '', value)
            # Remove path traversal
            value = value.replace('..', '')
        sanitized[key] = value
    return sanitized
```

### Permission System

```python
from enum import Flag, auto

class ToolPermission(Flag):
    READ = auto()
    WRITE = auto()
    DELETE = auto()
    EXECUTE = auto()
    ADMIN = auto()

TOOL_PERMISSIONS = {
    "query_database": ToolPermission.READ,
    "create_record": ToolPermission.WRITE,
    "delete_record": ToolPermission.DELETE,
    "run_script": ToolPermission.EXECUTE,
    "send_notification": ToolPermission.WRITE,
}

def check_permission(tool_name: str, user_permissions: ToolPermission) -> bool:
    required = TOOL_PERMISSIONS.get(tool_name, ToolPermission.ADMIN)
    return bool(user_permissions & required)
```

### Confirmation for Dangerous Operations

```python
DANGEROUS_TOOLS = {"delete_record", "send_email", "deploy", "run_script"}

async def execute_with_confirmation(tool_name: str, tool_input: dict) -> ToolResult:
    if tool_name in DANGEROUS_TOOLS:
        # In automated pipelines: log and proceed with safeguards
        # In interactive mode: ask for confirmation
        logger.warning(f"Dangerous tool invoked: {tool_name} with {tool_input}")
        await audit_log(tool_name, tool_input)

    return await tool_executor.execute(tool_name, tool_input)
```

---

## Anti-Patterns

| Anti-Pattern | Problem | Fix |
|-------------|---------|-----|
| Too many tools (>20) | Model confusion, poor selection | Group related tools, limit to 10-15 |
| Vague descriptions | Model uses wrong tool | Specific descriptions with use cases |
| No input validation | Crashes, injection | Pydantic schemas for all tools |
| No timeout | Hanging agent loops | Timeout on all tool executions |
| No error context | Model can't recover | Return helpful error messages |
| Exposing raw SQL tools | SQL injection risk | Parameterized queries only |
| No result truncation | Token overflow | Limit result size |
| Missing idempotency | Duplicate actions on retry | Idempotency keys for write operations |
