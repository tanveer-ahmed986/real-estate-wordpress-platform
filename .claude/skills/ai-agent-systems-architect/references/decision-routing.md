# Decision Engines & Routing Logic

Patterns for implementing decision-making, conditional routing, and state machines in AI agent systems.

---

## Decision Engine Patterns

### 1. LLM-Based Classification Router

The agent uses an LLM to classify input and route to the appropriate handler.

```python
from pydantic import BaseModel, Field
from enum import Enum

class Intent(str, Enum):
    BILLING = "billing"
    TECHNICAL = "technical"
    SALES = "sales"
    GENERAL = "general"

class Classification(BaseModel):
    intent: Intent = Field(description="The classified intent")
    confidence: float = Field(description="Confidence score 0-1")
    reasoning: str = Field(description="Why this classification")

def classify_and_route(input_text: str) -> str:
    result = llm.with_structured_output(Classification).invoke(
        f"Classify this customer request:\n{input_text}"
    )

    if result.confidence < 0.7:
        return "human_escalation"  # Low confidence → human review

    route_map = {
        Intent.BILLING: "billing_agent",
        Intent.TECHNICAL: "technical_agent",
        Intent.SALES: "sales_agent",
        Intent.GENERAL: "general_agent",
    }
    return route_map[result.intent]
```

### 2. Rule-Based Decision Tree

For deterministic decisions that don't need LLM reasoning.

```python
class DecisionTree:
    def __init__(self, rules: list[dict]):
        self.rules = rules  # Ordered list of condition → action

    def evaluate(self, context: dict) -> str:
        for rule in self.rules:
            if self._matches(rule["conditions"], context):
                return rule["action"]
        return self.rules[-1].get("default_action", "fallback")

    def _matches(self, conditions: dict, context: dict) -> bool:
        for key, expected in conditions.items():
            if callable(expected):
                if not expected(context.get(key)):
                    return False
            elif context.get(key) != expected:
                return False
        return True

# Usage
router = DecisionTree([
    {"conditions": {"priority": "urgent", "type": "billing"}, "action": "senior_billing"},
    {"conditions": {"priority": "urgent"}, "action": "escalation"},
    {"conditions": {"type": "billing"}, "action": "billing_agent"},
    {"conditions": {"type": "technical"}, "action": "technical_agent"},
    {"conditions": {}, "default_action": "general_agent"},
])
```

### 3. Scoring-Based Router

Route based on weighted scores across multiple criteria.

```python
class ScoringRouter:
    def __init__(self, agents: dict[str, dict]):
        """agents: {name: {capabilities: [...], cost: float, latency: float}}"""
        self.agents = agents

    def route(self, task: dict) -> str:
        scores = {}
        for name, config in self.agents.items():
            score = 0
            # Capability match
            required = set(task.get("required_capabilities", []))
            available = set(config["capabilities"])
            if not required.issubset(available):
                continue  # Skip agents that lack required capabilities
            score += len(required & available) * 10

            # Prefer lower cost
            score -= config["cost"] * 5

            # Prefer lower latency for urgent tasks
            if task.get("urgent"):
                score -= config["latency"] * 10

            scores[name] = score

        return max(scores, key=scores.get) if scores else "fallback"
```

---

## State Machine Patterns

### Finite State Machine for Agent Workflows

```python
from enum import Enum, auto

class WorkflowState(Enum):
    INTAKE = auto()
    RESEARCH = auto()
    ANALYSIS = auto()
    DRAFT = auto()
    REVIEW = auto()
    REVISION = auto()
    APPROVAL = auto()
    COMPLETE = auto()
    FAILED = auto()

TRANSITIONS = {
    WorkflowState.INTAKE: {
        "valid": WorkflowState.RESEARCH,
        "invalid": WorkflowState.FAILED,
    },
    WorkflowState.RESEARCH: {
        "data_found": WorkflowState.ANALYSIS,
        "no_data": WorkflowState.FAILED,
    },
    WorkflowState.ANALYSIS: {
        "complete": WorkflowState.DRAFT,
    },
    WorkflowState.DRAFT: {
        "complete": WorkflowState.REVIEW,
    },
    WorkflowState.REVIEW: {
        "approved": WorkflowState.APPROVAL,
        "needs_revision": WorkflowState.REVISION,
        "rejected": WorkflowState.FAILED,
    },
    WorkflowState.REVISION: {
        "complete": WorkflowState.REVIEW,  # Back to review
    },
    WorkflowState.APPROVAL: {
        "approved": WorkflowState.COMPLETE,
        "rejected": WorkflowState.REVISION,
    },
}

class WorkflowStateMachine:
    def __init__(self):
        self.state = WorkflowState.INTAKE
        self.history: list[tuple[WorkflowState, str]] = []

    def transition(self, event: str) -> WorkflowState:
        valid = TRANSITIONS.get(self.state, {})
        if event not in valid:
            raise ValueError(f"Invalid transition: {self.state} + {event}")
        self.history.append((self.state, event))
        self.state = valid[event]
        return self.state

    def can_transition(self, event: str) -> bool:
        return event in TRANSITIONS.get(self.state, {})
```

### LangGraph State Machine Implementation

```python
from langgraph.graph import StateGraph

class AgentState(TypedDict):
    messages: list
    workflow_state: str
    data: dict
    review_count: int

def intake_node(state):
    # Validate and classify input
    validation = validate_input(state["messages"][-1])
    return {"workflow_state": "research" if validation.valid else "failed"}

def research_node(state):
    results = research_agent.invoke(state["messages"])
    return {"data": {"research": results}, "workflow_state": "analysis"}

def review_node(state):
    review = review_agent.invoke(state["data"]["draft"])
    if review.approved:
        return {"workflow_state": "complete"}
    if state["review_count"] >= 3:
        return {"workflow_state": "human_escalation"}
    return {"workflow_state": "revision", "review_count": state["review_count"] + 1}

def route_by_state(state) -> str:
    return state["workflow_state"]

graph = StateGraph(AgentState)
graph.add_node("intake", intake_node)
graph.add_node("research", research_node)
graph.add_node("analysis", analysis_node)
graph.add_node("draft", draft_node)
graph.add_node("review", review_node)
graph.add_node("revision", revision_node)

for node in ["intake", "research", "analysis", "draft", "review", "revision"]:
    graph.add_conditional_edges(node, route_by_state)
```

---

## Conditional Logic Patterns

### If-Then-Else with LLM

```python
def conditional_agent_step(state):
    """Agent decides next action based on current state."""
    decision = llm.with_structured_output(Decision).invoke(
        f"""Given the current state:
        - Task: {state['task']}
        - Progress: {state['progress']}
        - Tools available: {state['available_tools']}

        What should we do next?
        Options: {state['possible_actions']}
        """
    )
    return {"next_action": decision.action, "reasoning": decision.reasoning}
```

### Priority Queue for Task Selection

```python
import heapq

class AgentTaskQueue:
    def __init__(self):
        self.queue = []
        self.counter = 0

    def add_task(self, priority: int, task: dict):
        heapq.heappush(self.queue, (priority, self.counter, task))
        self.counter += 1

    def get_next(self) -> dict | None:
        if self.queue:
            _, _, task = heapq.heappop(self.queue)
            return task
        return None

# Usage in orchestrator
queue = AgentTaskQueue()
queue.add_task(1, {"type": "urgent_support", "customer": "vip"})
queue.add_task(3, {"type": "data_analysis", "report": "weekly"})
queue.add_task(2, {"type": "email_followup", "lead": "warm"})
```

### Consensus-Based Decision Making

```python
async def consensus_decision(question: str, agents: list, threshold: float = 0.66):
    """Multiple agents vote on a decision."""
    votes = await asyncio.gather(*[
        agent.invoke(f"Vote YES or NO: {question}") for agent in agents
    ])

    yes_count = sum(1 for v in votes if "yes" in v.lower())
    agreement = yes_count / len(votes)

    return {
        "decision": "approved" if agreement >= threshold else "rejected",
        "agreement": agreement,
        "votes": votes
    }
```

---

## Error-Driven Routing

```python
class ErrorRouter:
    """Route to recovery agents based on error type."""

    recovery_map = {
        "rate_limit": {"action": "wait_and_retry", "delay": 60},
        "auth_failure": {"action": "refresh_credentials", "agent": "auth_agent"},
        "not_found": {"action": "search_alternative", "agent": "search_agent"},
        "validation_error": {"action": "fix_and_retry", "agent": "validator_agent"},
        "timeout": {"action": "retry_with_backoff", "max_retries": 3},
        "unknown": {"action": "escalate_to_human"},
    }

    def route_error(self, error: Exception) -> dict:
        error_type = self._classify_error(error)
        return self.recovery_map.get(error_type, self.recovery_map["unknown"])

    def _classify_error(self, error: Exception) -> str:
        if "rate limit" in str(error).lower():
            return "rate_limit"
        if "401" in str(error) or "403" in str(error):
            return "auth_failure"
        if "404" in str(error):
            return "not_found"
        if "timeout" in str(error).lower():
            return "timeout"
        return "unknown"
```
