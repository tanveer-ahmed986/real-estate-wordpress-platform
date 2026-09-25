# Multi-Agent Topologies

Patterns for structuring communication and collaboration between multiple AI agents.

---

## Topology 1: Sequential (Pipeline)

Agents process in fixed order, each passing output to the next.

```
Agent A → Agent B → Agent C → Final Output
```

### When to Use
- Assembly-line processing (research → draft → review → publish)
- Each stage has distinct role/expertise
- Order is fixed and predictable

### Framework Implementations

```python
# CrewAI Sequential
crew = Crew(
    agents=[researcher, writer, editor],
    tasks=[research_task, write_task, edit_task],
    process=Process.sequential
)

# LangGraph Sequential
graph = StateGraph(State)
graph.add_node("research", research_agent)
graph.add_node("write", write_agent)
graph.add_node("edit", edit_agent)
graph.add_edge("research", "write")
graph.add_edge("write", "edit")

# AutoGen Sequential via GraphFlow
from autogen_agentchat.teams import GraphFlow
flow = GraphFlow(
    participants=[researcher, writer, editor],
    graph={"researcher": ["writer"], "writer": ["editor"]}
)
```

---

## Topology 2: Hierarchical (Supervisor-Workers)

A supervisor agent delegates tasks and synthesizes results from worker agents.

```
         Supervisor
        /    |     \
  Worker A  Worker B  Worker C
```

### When to Use
- Dynamic task decomposition
- Workers have specialized capabilities
- Central decision-making is needed
- Tasks require coordination

### Framework Implementations

```python
# LangGraph Supervisor
from langgraph.prebuilt import create_react_agent

def supervisor(state):
    response = llm.invoke(
        f"You manage these workers: {worker_descriptions}\n"
        f"Task: {state['task']}\n"
        f"Decide which worker to assign or respond FINISH if done."
    )
    return {"next": parse_worker(response)}

graph = StateGraph(State)
graph.add_node("supervisor", supervisor)
graph.add_node("researcher", researcher_agent)
graph.add_node("coder", coder_agent)
graph.add_conditional_edges("supervisor", route_to_worker)

# CrewAI Hierarchical
crew = Crew(
    agents=[researcher, coder, analyst],
    tasks=[task1, task2, task3],
    process=Process.hierarchical,
    manager_agent=manager_agent
)

# OpenAI Agents SDK Handoffs
supervisor = Agent(
    name="supervisor",
    instructions="Delegate tasks to specialists.",
    handoffs=[researcher, coder, analyst]
)
```

### Design Considerations
- Supervisor needs clear descriptions of each worker's capabilities
- Define escalation paths when workers fail
- Set max delegation depth to prevent infinite delegation
- Consider fan-out limits (don't overwhelm with too many parallel workers)

---

## Topology 3: Peer-to-Peer (Flat Collaboration)

Any agent can communicate with any other agent directly.

```
Agent A ←→ Agent B
  ↕    ╲    ↕
Agent C ←→ Agent D
```

### When to Use
- Brainstorming / debate scenarios
- Agents need to cross-check each other's work
- No clear hierarchy exists

### Framework Implementations

```python
# AutoGen Group Chat
from autogen_agentchat.teams import SelectorGroupChat

team = SelectorGroupChat(
    participants=[agent_a, agent_b, agent_c],
    model_client=model,
    selector_prompt="Select the next speaker based on context."
)

# Semantic Kernel Group Chat Orchestration
from semantic_kernel.agents import GroupChatOrchestration
orchestration = GroupChatOrchestration(
    members=[agent_a, agent_b, agent_c],
    manager=group_chat_manager
)
```

### Challenges
- Can lead to circular conversations without termination conditions
- Harder to debug and trace execution
- Need clear speaker selection strategy

---

## Topology 4: Blackboard (Shared State)

Agents read/write to a shared state space. Each monitors the state and acts when relevant.

```
Agent A ──→ ┌─────────────┐ ←── Agent B
            │  Blackboard  │
Agent C ──→ │ (Shared State)│ ←── Agent D
            └─────────────┘
```

### When to Use
- Agents contribute different aspects to a shared artifact
- Asynchronous collaboration
- When agents need to observe each other's outputs

### Implementation Pattern

```python
# LangGraph with shared state
class BlackboardState(TypedDict):
    research_notes: list[str]
    draft_sections: dict[str, str]
    review_comments: list[str]
    status: dict[str, str]  # Track each agent's progress

def research_agent(state):
    # Read existing notes, add new findings
    new_findings = research(state["draft_sections"])
    return {"research_notes": state["research_notes"] + [new_findings]}

def writer_agent(state):
    # Read research notes, update draft
    section = write_section(state["research_notes"])
    return {"draft_sections": {**state["draft_sections"], "intro": section}}
```

---

## Topology 5: Swarm (Handoff-Based)

Agents hand off to each other based on context. Each agent decides who should handle next.

```
Agent A --handoff-→ Agent B --handoff-→ Agent C
    ↑                                      |
    └──────────handoff─────────────────────┘
```

### When to Use
- Customer support triage and routing
- Multi-department workflows
- When context determines which agent should respond
- Conversational systems with domain switching

### Framework Implementations

```python
# OpenAI Agents SDK Swarm
triage = Agent(
    name="triage",
    instructions="Route customer to the right department.",
    handoffs=[billing_agent, technical_agent, sales_agent]
)

billing_agent = Agent(
    name="billing",
    instructions="Handle billing inquiries.",
    handoffs=[triage],  # Can hand back
    tools=[lookup_invoice, process_refund]
)

# AutoGen Swarm
from autogen_agentchat.teams import Swarm
team = Swarm(
    participants=[triage, billing, technical, sales],
    # Handoffs defined via tool-based agent transfer
)

# LangGraph Swarm
from langgraph.prebuilt import create_swarm
swarm = create_swarm(
    agents=[triage, billing, technical, sales],
    default_agent="triage"
)
```

### Design Principles
- Every agent should have a clear handoff-back path
- Include a "catch-all" agent for unhandled cases
- Log every handoff for debugging
- Set max handoff depth to prevent loops

---

## Topology 6: Directed Acyclic Graph (DAG)

Explicit directed flow with possible branching and merging.

```
        Agent A
       /       \
  Agent B     Agent C
       \       /
        Agent D
```

### When to Use
- Complex workflows with branching and merging
- When some stages can run in parallel
- Build/CI-like pipelines
- ETL processing with conditional paths

### Framework Implementations

```python
# LangGraph DAG
graph = StateGraph(State)
graph.add_node("extract", extract_agent)
graph.add_node("validate", validate_agent)
graph.add_node("enrich", enrich_agent)
graph.add_node("merge", merge_agent)

graph.add_edge("extract", "validate")
graph.add_edge("extract", "enrich")  # Parallel with validate
graph.add_edge("validate", "merge")
graph.add_edge("enrich", "merge")

# AutoGen GraphFlow
flow = GraphFlow(
    participants=[extract, validate, enrich, merge],
    graph={
        "extract": ["validate", "enrich"],
        "validate": ["merge"],
        "enrich": ["merge"]
    }
)
```

---

## Topology Selection Guide

```
How many agents?
├── 1 → Single agent (no topology needed)
├── 2-3 with fixed flow → Sequential
├── 2-3 with dynamic flow → Swarm
└── 4+ agents:
    ├── Clear leader needed? → Hierarchical
    ├── All peers equal? → Peer-to-peer / Group Chat
    ├── Shared artifact? → Blackboard
    ├── Context-based routing? → Swarm
    └── Complex branching? → DAG
```

## Communication Patterns

| Pattern | Description | Use With |
|---------|-------------|----------|
| **Message passing** | Direct messages between agents | Sequential, Hierarchical |
| **Shared state** | Read/write to common store | Blackboard, DAG |
| **Handoffs** | Transfer conversation control | Swarm |
| **Broadcast** | One-to-many notifications | Peer-to-peer |
| **Request-response** | Synchronous query/answer | Hierarchical |
| **Event-driven** | React to state changes | Blackboard, DAG |

## Termination Strategies

| Strategy | How | Best For |
|----------|-----|----------|
| **Max rounds** | Fixed iteration limit | Group chat, peer-to-peer |
| **Completion signal** | Agent outputs "DONE" token | Sequential, hierarchical |
| **Quality threshold** | Evaluator scores above target | Evaluator-optimizer |
| **Consensus** | All agents agree | Voting, peer-to-peer |
| **Timeout** | Wall-clock limit | Production safety |
| **Human approval** | Pause for human decision | High-stakes tasks |
