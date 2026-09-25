# Security & Guardrails for AI Agent Systems

Comprehensive security patterns for autonomous AI agents with tool access.

---

## Threat Model for AI Agents

| Threat | Vector | Impact | Mitigation |
|--------|--------|--------|------------|
| **Prompt injection** | Malicious input in user messages or tool results | Agent performs unintended actions | Input sanitization, output validation |
| **Tool abuse** | Agent misuses tools beyond intended scope | Data corruption, unauthorized actions | Least-privilege permissions, approval gates |
| **Data exfiltration** | Agent leaks sensitive data via tools | Privacy breach, compliance violation | Output filtering, data classification |
| **Denial of service** | Agent enters infinite loops or excessive API calls | Resource exhaustion, cost explosion | Rate limits, max iterations, budgets |
| **Privilege escalation** | Agent gains access beyond its role | Unauthorized system access | Role-based tool access, sandboxing |
| **Supply chain** | Compromised MCP server or tool dependency | Backdoor access | Pin versions, audit dependencies |

---

## Input Guardrails

### Prompt Injection Defense

```python
# OpenAI Agents SDK guardrail pattern
from agents import Agent, GuardrailFunctionOutput, input_guardrail

@input_guardrail
async def injection_guard(ctx, agent, input):
    """Check for prompt injection before agent processes input."""
    result = await Runner.run(
        injection_detector_agent,
        input,
        context=ctx
    )
    is_injection = result.final_output.lower().strip() == "unsafe"
    return GuardrailFunctionOutput(
        output_info={"flagged": is_injection},
        tripwire_triggered=is_injection
    )

agent = Agent(
    name="assistant",
    instructions="Help users with their requests.",
    input_guardrails=[injection_guard]
)

# Multi-layer defense
class InputValidator:
    def __init__(self):
        self.patterns = [
            r"ignore previous instructions",
            r"system prompt",
            r"you are now",
            r"disregard.*above",
            r"</?(system|user|assistant)>",
        ]

    def check(self, input_text: str) -> tuple[bool, str]:
        """Returns (is_safe, reason)."""
        # Layer 1: Pattern matching (fast, cheap)
        for pattern in self.patterns:
            if re.search(pattern, input_text, re.IGNORECASE):
                return False, f"Suspicious pattern detected: {pattern}"

        # Layer 2: Length check
        if len(input_text) > 10000:
            return False, "Input exceeds maximum length"

        # Layer 3: LLM-based detection (slow, accurate)
        # Only if passes basic checks
        return True, "passed"
```

### Tool Result Validation

```python
def validate_tool_result(tool_name: str, result: any) -> any:
    """Sanitize tool results before injecting into agent context."""
    if isinstance(result, str):
        # Strip potential injection from external data
        result = strip_control_chars(result)
        # Truncate to prevent context overflow
        if len(result) > 5000:
            result = result[:5000] + "\n[TRUNCATED]"
    return result
```

---

## Output Guardrails

### Content Filtering

```python
@output_guardrail
async def content_filter(ctx, agent, output):
    """Validate agent output before returning to user."""
    checks = [
        no_pii_leaked(output),        # No SSN, credit cards, etc.
        no_secrets_leaked(output),     # No API keys, passwords
        no_harmful_content(output),    # No harmful instructions
        within_scope(output, agent),   # Stays within agent's role
    ]
    results = await asyncio.gather(*checks)
    for check_name, passed, reason in results:
        if not passed:
            return GuardrailFunctionOutput(
                output_info={"blocked": check_name, "reason": reason},
                tripwire_triggered=True
            )
    return GuardrailFunctionOutput(output_info={"status": "passed"})

# PII detection
import re

PII_PATTERNS = {
    "ssn": r"\b\d{3}-\d{2}-\d{4}\b",
    "credit_card": r"\b\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?\d{4}\b",
    "email": r"\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b",
    "phone": r"\b\d{3}[\s.-]\d{3}[\s.-]\d{4}\b",
}

def no_pii_leaked(text: str) -> tuple[str, bool, str]:
    for pii_type, pattern in PII_PATTERNS.items():
        if re.search(pattern, text):
            return ("pii_check", False, f"Potential {pii_type} detected in output")
    return ("pii_check", True, "")
```

---

## Permission Model

### Least-Privilege Tool Access

```python
# Define permission levels per agent role
ROLE_PERMISSIONS = {
    "researcher": {
        "allowed_tools": ["web_search", "read_document", "query_db"],
        "denied_tools": ["send_email", "delete_record", "execute_code"],
        "max_calls_per_minute": 20,
        "require_approval": [],
    },
    "sales_agent": {
        "allowed_tools": ["search_crm", "get_contact", "draft_email", "log_activity"],
        "denied_tools": ["delete_contact", "modify_pricing"],
        "max_calls_per_minute": 30,
        "require_approval": ["send_email", "create_deal"],
    },
    "admin_agent": {
        "allowed_tools": ["*"],
        "denied_tools": ["drop_table", "delete_database"],
        "max_calls_per_minute": 50,
        "require_approval": ["delete_record", "bulk_update"],
    },
}

class PermissionEnforcer:
    def __init__(self, role: str):
        self.perms = ROLE_PERMISSIONS[role]

    def can_use_tool(self, tool_name: str) -> tuple[bool, bool]:
        """Returns (allowed, needs_approval)."""
        if tool_name in self.perms["denied_tools"]:
            return False, False
        if "*" not in self.perms["allowed_tools"] and tool_name not in self.perms["allowed_tools"]:
            return False, False
        needs_approval = tool_name in self.perms.get("require_approval", [])
        return True, needs_approval
```

### Human-in-the-Loop Checkpoints

```python
# LangGraph interrupt pattern
from langgraph.types import interrupt

def sensitive_action(state):
    """Pause for human approval before executing."""
    action = state["pending_action"]

    # Interrupt execution for human review
    approval = interrupt({
        "action": action,
        "reason": "This action requires human approval",
        "details": state["action_details"]
    })

    if approval.get("approved"):
        return execute_action(action)
    else:
        return {"status": "rejected", "reason": approval.get("reason", "Denied by human")}
```

---

## Sandboxing Patterns

### Code Execution Sandbox

```python
# Docker-based sandbox
class DockerSandbox:
    def __init__(self, image: str = "python:3.11-slim", memory: str = "256m", timeout: int = 30):
        self.image = image
        self.memory = memory
        self.timeout = timeout

    async def run(self, code: str) -> dict:
        container = await docker.containers.run(
            self.image,
            command=["python", "-c", code],
            mem_limit=self.memory,
            network_disabled=True,        # No network access
            read_only=True,               # Read-only filesystem
            tmpfs={"/tmp": "size=50m"},   # Limited temp space
            remove=True,
            detach=True
        )
        try:
            result = await asyncio.wait_for(container.wait(), timeout=self.timeout)
            logs = await container.logs()
            return {"stdout": logs.decode(), "exit_code": result["StatusCode"]}
        except asyncio.TimeoutError:
            await container.kill()
            return {"error": "Execution timed out", "exit_code": -1}
```

### Network Isolation

| Agent Type | Network Access | Rationale |
|------------|---------------|-----------|
| Code executor | None | Prevent data exfiltration |
| Internal agent | Internal only | Access company APIs only |
| Web researcher | External read | Needs web access, no writes |
| Full agent | Filtered | Allowlist of approved domains |

---

## Cost & Budget Controls

```python
class BudgetManager:
    def __init__(self, max_tokens: int = 100_000, max_cost_usd: float = 10.0):
        self.max_tokens = max_tokens
        self.max_cost = max_cost_usd
        self.tokens_used = 0
        self.cost_usd = 0.0

    def track_usage(self, input_tokens: int, output_tokens: int, model: str):
        self.tokens_used += input_tokens + output_tokens
        self.cost_usd += self._calculate_cost(input_tokens, output_tokens, model)

    def check_budget(self) -> tuple[bool, str]:
        if self.tokens_used >= self.max_tokens:
            return False, f"Token budget exhausted: {self.tokens_used}/{self.max_tokens}"
        if self.cost_usd >= self.max_cost:
            return False, f"Cost budget exhausted: ${self.cost_usd:.2f}/${self.max_cost:.2f}"
        return True, "within budget"

    def _calculate_cost(self, input_tokens: int, output_tokens: int, model: str) -> float:
        rates = {
            "gpt-4o": (0.0025, 0.01),
            "claude-sonnet-4-20250514": (0.003, 0.015),
            "claude-haiku-4-5-20251001": (0.0008, 0.004),
        }
        input_rate, output_rate = rates.get(model, (0.005, 0.015))
        return (input_tokens * input_rate + output_tokens * output_rate) / 1000
```

---

## Audit Logging

```python
import structlog

logger = structlog.get_logger()

class AuditLogger:
    def log_tool_call(self, agent_id: str, tool: str, params: dict, result: any, approved_by: str = None):
        logger.info("agent.tool_call",
            agent_id=agent_id,
            tool=tool,
            params=self._redact_sensitive(params),
            result_summary=str(result)[:200],
            approved_by=approved_by,
            timestamp=datetime.utcnow().isoformat()
        )

    def log_decision(self, agent_id: str, decision: str, reasoning: str, confidence: float):
        logger.info("agent.decision",
            agent_id=agent_id,
            decision=decision,
            reasoning=reasoning[:500],
            confidence=confidence
        )

    def log_handoff(self, from_agent: str, to_agent: str, reason: str, context_summary: str):
        logger.info("agent.handoff",
            from_agent=from_agent,
            to_agent=to_agent,
            reason=reason,
            context=context_summary[:300]
        )

    def _redact_sensitive(self, params: dict) -> dict:
        sensitive_keys = {"password", "token", "api_key", "secret", "ssn", "credit_card"}
        return {k: "***REDACTED***" if k.lower() in sensitive_keys else v for k, v in params.items()}
```

---

## Security Checklist

- [ ] Input validation on all user-facing entry points
- [ ] Prompt injection defense (pattern + LLM-based)
- [ ] Tool results sanitized before context injection
- [ ] Output filtering for PII and secrets
- [ ] Least-privilege tool permissions per agent role
- [ ] Human approval gates for destructive/sensitive actions
- [ ] Code execution sandboxed (no network, limited resources)
- [ ] Rate limiting on all tool calls
- [ ] Token and cost budget enforcement
- [ ] Audit logging for all tool calls and decisions
- [ ] Max iteration limits on all agent loops
- [ ] Timeout on all external calls
- [ ] Dependency pinning and audit
- [ ] Regular review of agent permissions
