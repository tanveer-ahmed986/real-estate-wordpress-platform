# Multi-Agent Patterns

Patterns for orchestrating multiple AI agents working together.

---

## Supervisor Pattern

A manager agent delegates tasks to specialized worker agents.

### Architecture

```
User Input → [Supervisor Agent]
                 ├── [Worker: Researcher]
                 ├── [Worker: Analyst]
                 ├── [Worker: Writer]
                 └── [Worker: Reviewer]
             → Aggregated Output
```

### Implementation (LangGraph Style)

```python
from typing import Literal
from pydantic import BaseModel

class SupervisorDecision(BaseModel):
    next_worker: Literal["researcher", "analyst", "writer", "reviewer", "FINISH"]
    instructions: str
    reasoning: str

class WorkerResult(BaseModel):
    worker: str
    output: str
    confidence: float

async def supervisor_loop(task: str, max_rounds: int = 10) -> str:
    state = {"task": task, "results": [], "messages": []}

    for round_num in range(max_rounds):
        # Supervisor decides next action
        decision = await supervisor_decide(state)

        if decision.next_worker == "FINISH":
            return await synthesize_results(state)

        # Delegate to worker
        result = await run_worker(
            worker=decision.next_worker,
            instructions=decision.instructions,
            context=state
        )
        state["results"].append(result)

    return await synthesize_results(state)

# Worker definitions
workers = {
    "researcher": {
        "system": "You are a research specialist. Find relevant information.",
        "tools": ["web_search", "database_query"]
    },
    "analyst": {
        "system": "You are a data analyst. Analyze data and find patterns.",
        "tools": ["calculate", "chart_create"]
    },
    "writer": {
        "system": "You are a technical writer. Create clear documentation.",
        "tools": ["format_document"]
    },
    "reviewer": {
        "system": "You are a quality reviewer. Check for accuracy and completeness.",
        "tools": ["validate", "score"]
    }
}
```

### When to Use
- Tasks requiring different expertise areas
- Need centralized coordination and quality control
- Complex workflows with dynamic task assignment

---

## Crew Pattern (CrewAI Style)

Agents with defined roles collaborate on a shared goal.

### Architecture

```
Goal → [Agent 1: Role A] → [Agent 2: Role B] → [Agent 3: Role C] → Output
       (with memory)        (with tools)        (with delegation)
```

### Implementation

```python
from dataclasses import dataclass
from typing import Callable

@dataclass
class Agent:
    role: str
    goal: str
    backstory: str
    tools: list[Callable]
    allow_delegation: bool = False
    verbose: bool = True

@dataclass
class Task:
    description: str
    expected_output: str
    agent: Agent
    context: list["Task"] | None = None  # Dependencies

@dataclass
class Crew:
    agents: list[Agent]
    tasks: list[Task]
    process: str = "sequential"  # or "hierarchical"

# Example: Content creation crew
researcher = Agent(
    role="Senior Research Analyst",
    goal="Uncover cutting-edge developments in AI",
    backstory="Expert at finding and synthesizing information from multiple sources.",
    tools=[web_search, arxiv_search],
)

writer = Agent(
    role="Tech Content Strategist",
    goal="Create compelling technical content",
    backstory="Known for making complex topics accessible and engaging.",
    tools=[format_markdown],
)

editor = Agent(
    role="Quality Editor",
    goal="Ensure accuracy and readability",
    backstory="Meticulous editor with deep technical knowledge.",
    tools=[grammar_check, fact_check],
)

# Tasks with dependencies
research_task = Task(
    description="Research the latest developments in {topic}",
    expected_output="Comprehensive research notes with sources",
    agent=researcher,
)

write_task = Task(
    description="Write an article based on the research",
    expected_output="Well-structured article, 1500-2000 words",
    agent=writer,
    context=[research_task],  # Depends on research
)

edit_task = Task(
    description="Review and polish the article",
    expected_output="Final edited article ready for publication",
    agent=editor,
    context=[write_task],
)

crew = Crew(
    agents=[researcher, writer, editor],
    tasks=[research_task, write_task, edit_task],
    process="sequential",
)
```

---

## Swarm Pattern (OpenAI Swarm / Anthropic Handoff)

Agents dynamically hand off conversations to each other.

### Architecture

```
User Input → [Agent A] ──handoff──→ [Agent B] ──handoff──→ [Agent C]
                │                      │                      │
                └──respond──→          └──respond──→          └──respond──→ Output
```

### Implementation

```python
@dataclass
class SwarmAgent:
    name: str
    instructions: str
    tools: list[dict]
    handoff_targets: list[str]  # Agents this one can hand off to

def create_handoff_tool(target_agent: str) -> dict:
    return {
        "name": f"transfer_to_{target_agent}",
        "description": f"Transfer conversation to {target_agent} agent",
        "input_schema": {
            "type": "object",
            "properties": {
                "reason": {"type": "string", "description": "Why transferring"},
                "context": {"type": "string", "description": "Key context for receiving agent"}
            },
            "required": ["reason"]
        }
    }

async def run_swarm(initial_agent: str, user_input: str, agents: dict[str, SwarmAgent]) -> str:
    current_agent = agents[initial_agent]
    messages = [{"role": "user", "content": user_input}]
    max_handoffs = 5

    for _ in range(max_handoffs):
        response = await call_llm(
            system=current_agent.instructions,
            tools=current_agent.tools + [
                create_handoff_tool(t) for t in current_agent.handoff_targets
            ],
            messages=messages,
        )

        # Check for handoff
        handoff = find_handoff_tool_call(response)
        if handoff:
            target = handoff.target_agent
            current_agent = agents[target]
            messages.append({"role": "system", "content": f"Transferred from {current_agent.name}. Context: {handoff.context}"})
            continue

        return extract_text(response)

    return "Max handoffs reached."
```

### When to Use
- Customer service with multiple departments
- Workflows where the right specialist isn't known upfront
- Conversational agents needing dynamic expertise routing

---

## Adversarial/Debate Pattern

Multiple agents argue different positions, a judge synthesizes.

### Architecture

```
Prompt → [Agent A: Pro] ──→ [Judge] ──→ Synthesized Output
         [Agent B: Con] ──→
         [Agent C: Alt] ──→
```

### Implementation

```python
async def debate(topic: str, rounds: int = 2) -> str:
    positions = []

    # Initial positions
    pro = await argue_position(topic, stance="in_favor")
    con = await argue_position(topic, stance="against")

    positions.append({"pro": pro, "con": con})

    # Debate rounds - each side responds to the other
    for _ in range(rounds):
        pro_rebuttal = await rebut(topic, stance="in_favor", opponent_argument=con)
        con_rebuttal = await rebut(topic, stance="against", opponent_argument=pro)
        positions.append({"pro": pro_rebuttal, "con": con_rebuttal})
        pro, con = pro_rebuttal, con_rebuttal

    # Judge synthesizes
    verdict = await judge_debate(topic, positions)
    return verdict
```

### When to Use
- Decisions requiring multiple perspectives
- Risk assessment and due diligence
- Creative brainstorming with diverse viewpoints

---

## Hierarchical Pattern

Multiple levels of management, each coordinating sub-teams.

### Architecture

```
[Executive Agent]
    ├── [Manager A]
    │   ├── [Worker A1]
    │   └── [Worker A2]
    └── [Manager B]
        ├── [Worker B1]
        └── [Worker B2]
```

### When to Use
- Very large, complex tasks
- Tasks spanning multiple domains
- When individual managers need autonomy over sub-tasks

---

## Communication Patterns Between Agents

### Shared State (Blackboard)

```python
class SharedState:
    """Central state accessible by all agents."""
    def __init__(self):
        self._state: dict = {}
        self._lock = asyncio.Lock()

    async def read(self, key: str) -> Any:
        async with self._lock:
            return self._state.get(key)

    async def write(self, key: str, value: Any, agent: str):
        async with self._lock:
            self._state[key] = {"value": value, "agent": agent, "timestamp": time.time()}
```

### Message Passing

```python
class AgentMessage:
    sender: str
    receiver: str
    content: str
    message_type: str  # "request", "response", "broadcast"
    correlation_id: str  # For request-response matching

class MessageBus:
    async def send(self, msg: AgentMessage): ...
    async def receive(self, agent_id: str, timeout: float = 30.0) -> AgentMessage: ...
    async def broadcast(self, msg: AgentMessage): ...
```

### Direct Tool Calling

```python
# Agent A can call Agent B as a tool
agent_b_tool = {
    "name": "consult_analyst",
    "description": "Ask the analyst agent for data analysis",
    "input_schema": {
        "type": "object",
        "properties": {
            "question": {"type": "string"},
            "data_context": {"type": "string"}
        },
        "required": ["question"]
    }
}
```

---

## Multi-Agent Anti-Patterns

| Anti-Pattern | Problem | Solution |
|-------------|---------|----------|
| Agent ping-pong | Agents hand off back and forth endlessly | Max handoff limit + handoff tracking |
| Role confusion | Agents with overlapping responsibilities | Clear, non-overlapping role definitions |
| Context loss | Information lost between agent transitions | Shared state or explicit context passing |
| Unbounded delegation | Supervisor keeps delegating without converging | Max rounds + convergence check |
| Single failure cascade | One agent failure crashes entire system | Error isolation + fallback agents |
| Overcoordination | Excessive communication overhead | Minimize inter-agent calls, batch messages |

---

## Pattern Selection Guide

```
How many agents do you need?
├─ 2-3 with fixed roles → Crew Pattern
├─ 2-3 with dynamic routing → Swarm Pattern
├─ 4+ with central coordination → Supervisor Pattern
├─ 4+ with sub-teams → Hierarchical Pattern
└─ 2-3 for quality/debate → Adversarial Pattern

How do agents communicate?
├─ Sequential handoff → Crew / Swarm
├─ Central coordinator → Supervisor
├─ Shared workspace → Blackboard
└─ Direct peer-to-peer → Message Bus
```
