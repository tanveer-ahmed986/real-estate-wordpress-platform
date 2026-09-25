# Tool Integration Patterns

Patterns for equipping AI agents with tools to interact with external systems (CRM, email, APIs, dashboards, databases, code execution).

---

## Tool Definition Principles

### 1. Clear Tool Descriptions Are Critical

The agent decides which tool to use based on the description. Poor descriptions = wrong tool selection.

```python
# BAD: Vague description
@tool
def search(q: str) -> str:
    """Search for things."""

# GOOD: Specific, actionable description
@tool
def search_crm_contacts(
    query: str,
    filters: dict | None = None
) -> list[dict]:
    """Search CRM contacts by name, email, or company.

    Args:
        query: Search term (name, email, or company name)
        filters: Optional filters like {"status": "active", "region": "US"}

    Returns:
        List of matching contacts with id, name, email, company, status.
        Returns empty list if no matches. Max 50 results.
    """
```

### 2. Tool Design Rules

| Rule | Why | Example |
|------|-----|---------|
| Single responsibility | Easier for LLM to choose correctly | `send_email` not `communicate` |
| Typed parameters | Reduces errors | `count: int` not `count: str` |
| Descriptive names | Self-documenting | `get_invoice_by_id` not `fetch` |
| Bounded output | Prevent context overflow | Paginate, summarize, limit fields |
| Idempotent reads | Safe to retry | GET operations return same result |
| Explicit errors | Agent can reason about failures | Return error messages, not exceptions |

### 3. Tool Schema (Function Calling)

```python
# OpenAI/Anthropic function calling schema
tool_schema = {
    "name": "create_support_ticket",
    "description": "Create a new customer support ticket in the helpdesk system.",
    "parameters": {
        "type": "object",
        "properties": {
            "subject": {"type": "string", "description": "Ticket subject line"},
            "description": {"type": "string", "description": "Detailed description"},
            "priority": {
                "type": "string",
                "enum": ["low", "medium", "high", "urgent"],
                "description": "Ticket priority level"
            },
            "customer_email": {"type": "string", "description": "Customer's email"}
        },
        "required": ["subject", "description", "priority", "customer_email"]
    }
}
```

---

## Integration Patterns by Category

### CRM Integration (Salesforce, HubSpot, etc.)

```python
# Tool set for CRM operations
crm_tools = [
    search_contacts,      # Search by name/email/company
    get_contact_details,  # Full profile by ID
    update_contact,       # Modify contact fields
    create_deal,          # New sales opportunity
    get_deal_pipeline,    # View deal stages
    log_activity,         # Log call/email/meeting
    get_account_history,  # Activity timeline
]

# Implementation pattern
class CRMToolkit:
    def __init__(self, api_key: str, base_url: str):
        self.client = httpx.AsyncClient(
            base_url=base_url,
            headers={"Authorization": f"Bearer {api_key}"},
            timeout=30.0
        )

    async def search_contacts(self, query: str, limit: int = 20) -> list[dict]:
        """Search CRM contacts. Returns summarized results to fit context window."""
        resp = await self.client.get("/contacts/search", params={"q": query, "limit": limit})
        resp.raise_for_status()
        # Return only essential fields to save tokens
        return [
            {"id": c["id"], "name": c["name"], "email": c["email"], "company": c["company"]}
            for c in resp.json()["results"]
        ]
```

### Email Integration

```python
# Tool set for email operations
email_tools = [
    search_emails,       # Search inbox/sent by query
    read_email,          # Get full email content by ID
    send_email,          # Compose and send
    reply_to_email,      # Reply to thread
    draft_email,         # Save draft (human reviews before send)
    list_recent_emails,  # Recent inbox summary
]

# Safety: Always draft first in production
@tool
def draft_email(to: str, subject: str, body: str) -> dict:
    """Create an email draft for human review before sending.

    Returns draft ID. Human must approve before sending.
    Use send_draft(draft_id) after human approval.
    """
    draft = gmail.drafts().create(body={
        "message": create_message(to, subject, body)
    }).execute()
    return {"draft_id": draft["id"], "status": "draft_created", "needs_approval": True}
```

### API Integration (Generic REST/GraphQL)

```python
# Pattern: Wrap API endpoints as individual tools
class APIToolkit:
    def __init__(self, base_url: str, auth_token: str):
        self.client = httpx.AsyncClient(
            base_url=base_url,
            headers={"Authorization": f"Bearer {auth_token}"},
            timeout=30.0,
            limits=httpx.Limits(max_connections=10)
        )

    # Each endpoint becomes a tool with clear description
    async def get_order_status(self, order_id: str) -> dict:
        """Check the current status of a customer order.
        Returns: order status, tracking info, estimated delivery date.
        """
        resp = await self.client.get(f"/orders/{order_id}")
        if resp.status_code == 404:
            return {"error": f"Order {order_id} not found"}
        resp.raise_for_status()
        data = resp.json()
        return {
            "order_id": data["id"],
            "status": data["status"],
            "tracking": data.get("tracking_number"),
            "eta": data.get("estimated_delivery")
        }
```

### Database Integration

```python
# Read-only query tool (safe for agents)
@tool
def query_database(sql: str) -> list[dict]:
    """Execute a READ-ONLY SQL query against the analytics database.

    ONLY SELECT queries are allowed. No INSERT, UPDATE, DELETE, DROP, ALTER.
    Results limited to 100 rows. Use LIMIT clause for large tables.

    Available tables: orders, customers, products, order_items
    """
    # Safety: validate query is read-only
    normalized = sql.strip().upper()
    if not normalized.startswith("SELECT"):
        return {"error": "Only SELECT queries are allowed"}

    forbidden = ["INSERT", "UPDATE", "DELETE", "DROP", "ALTER", "TRUNCATE", "CREATE"]
    if any(kw in normalized for kw in forbidden):
        return {"error": f"Mutation queries are not allowed"}

    with engine.connect() as conn:
        result = conn.execute(text(sql + " LIMIT 100"))
        return [dict(row._mapping) for row in result]
```

### Dashboard Integration

```python
# Dashboard query and update tools
dashboard_tools = [
    get_dashboard_metrics,    # Read current metrics/KPIs
    update_dashboard_widget,  # Update a specific widget
    create_chart_data,        # Generate chart-ready data
    get_dashboard_alerts,     # Check alert status
    trigger_report,           # Generate on-demand report
]

@tool
def get_dashboard_metrics(dashboard_id: str, time_range: str = "7d") -> dict:
    """Fetch current metrics from a dashboard.

    Args:
        dashboard_id: Dashboard identifier
        time_range: Time range (1d, 7d, 30d, 90d)

    Returns: Dictionary of metric name → current value with trend.
    """
```

### Code Execution

```python
# Sandboxed code execution tool
@tool
def execute_python(code: str, timeout: int = 30) -> dict:
    """Execute Python code in a sandboxed environment.

    Pre-installed: pandas, numpy, matplotlib, requests.
    Max execution time: 30 seconds. Max memory: 256MB.
    File output saved to /tmp/output/

    Returns: stdout, stderr, and paths to any generated files.
    """
    result = sandbox.run(
        code=code,
        timeout=timeout,
        memory_limit="256m",
        network=False  # No network access from sandbox
    )
    return {
        "stdout": result.stdout[:5000],  # Truncate
        "stderr": result.stderr[:2000],
        "files": result.output_files,
        "exit_code": result.exit_code
    }
```

---

## MCP (Model Context Protocol) Integration

MCP provides a standardized protocol for connecting agents to external tools and data sources.

```python
# OpenAI Agents SDK with MCP
from agents.mcp import MCPServerStdio, MCPServerStreamableHTTP

# Local MCP server (stdio)
filesystem_server = MCPServerStdio(
    command="npx",
    args=["-y", "@modelcontextprotocol/server-filesystem", "/path/to/data"]
)

# Remote MCP server (HTTP)
crm_server = MCPServerStreamableHTTP(url="https://crm-mcp.internal/sse")

agent = Agent(
    name="data_analyst",
    instructions="Analyze data using available tools.",
    mcp_servers=[filesystem_server, crm_server]
)

# MCP servers expose tools that the agent discovers dynamically
async with filesystem_server, crm_server:
    result = await Runner.run(agent, "Analyze Q4 sales from the CRM")
```

### MCP Benefits
- Standardized tool discovery and invocation
- Agents automatically see available tools
- Server-side tool management (update tools without changing agent code)
- Built-in auth and permission handling

---

## Tool Safety Patterns

### Permission Levels

| Level | Allowed | Example Tools |
|-------|---------|---------------|
| **Read-only** | Query, search, fetch | `search_contacts`, `get_order` |
| **Draft** | Create drafts, no direct action | `draft_email`, `prepare_ticket` |
| **Write with approval** | Mutate after human confirms | `send_email` (after approval) |
| **Full autonomy** | Act independently | `log_activity`, `update_status` |

### Rate Limiting

```python
from functools import wraps
import time

def rate_limit(max_calls: int, period: int):
    """Decorator to rate-limit tool calls."""
    calls = []
    def decorator(func):
        @wraps(func)
        async def wrapper(*args, **kwargs):
            now = time.time()
            calls[:] = [t for t in calls if now - t < period]
            if len(calls) >= max_calls:
                return {"error": f"Rate limit: max {max_calls} calls per {period}s"}
            calls.append(now)
            return await func(*args, **kwargs)
        return wrapper
    return decorator

@rate_limit(max_calls=10, period=60)
async def search_crm(query: str): ...
```

### Error Handling for Tools

```python
async def safe_tool_call(tool_func, *args, **kwargs):
    """Wrapper that returns structured errors instead of raising exceptions."""
    try:
        result = await asyncio.wait_for(tool_func(*args, **kwargs), timeout=30)
        return {"success": True, "data": result}
    except asyncio.TimeoutError:
        return {"success": False, "error": "Tool call timed out after 30s"}
    except httpx.HTTPStatusError as e:
        return {"success": False, "error": f"API error: {e.response.status_code}"}
    except Exception as e:
        return {"success": False, "error": f"Unexpected error: {str(e)}"}
```

---

## Tool Organization by Agent Role

| Agent Role | Tools | Permission |
|------------|-------|------------|
| **Researcher** | web_search, read_docs, query_db | Read-only |
| **Sales rep** | search_crm, create_deal, send_email | Write with approval |
| **Support agent** | search_tickets, update_ticket, lookup_customer | Write |
| **Data analyst** | query_db, execute_python, create_chart | Read + sandbox exec |
| **Admin** | All tools | Full (with audit logging) |
