# Agent Design Patterns

Comprehensive patterns for building single-agent AI systems.

---

## ReAct Pattern (Reason + Act)

The agent iteratively reasons about the task, takes actions, and observes results.

### Architecture

```
User Input → [Think → Act → Observe] → ... → Final Answer
```

### Implementation (Anthropic SDK)

```python
import anthropic

client = anthropic.Anthropic()

tools = [
    {
        "name": "search_database",
        "description": "Search the product database by query",
        "input_schema": {
            "type": "object",
            "properties": {
                "query": {"type": "string", "description": "Search query"},
                "limit": {"type": "integer", "default": 10}
            },
            "required": ["query"]
        }
    }
]

def run_agent(user_message: str, max_iterations: int = 10) -> str:
    messages = [{"role": "user", "content": user_message}]

    for i in range(max_iterations):
        response = client.messages.create(
            model="claude-sonnet-4-5-20250929",
            max_tokens=4096,
            system="You are a helpful assistant. Think step by step.",
            tools=tools,
            messages=messages,
        )

        # Check if agent wants to use a tool
        if response.stop_reason == "tool_use":
            tool_use = next(b for b in response.content if b.type == "tool_use")
            tool_result = execute_tool(tool_use.name, tool_use.input)

            messages.append({"role": "assistant", "content": response.content})
            messages.append({
                "role": "user",
                "content": [{"type": "tool_result", "tool_use_id": tool_use.id, "content": tool_result}]
            })
        else:
            # Agent is done
            return next(b.text for b in response.content if b.type == "text")

    return "Max iterations reached. Partial result available."
```

### When to Use
- Tasks requiring dynamic tool selection
- Information gathering with multiple sources
- Problems where steps aren't known in advance

### Anti-Patterns
- No max iteration limit (infinite loops)
- Too many tools (>15 causes selection confusion)
- No observation validation (trusting all tool outputs)

---

## Plan-and-Execute Pattern

Agent creates a plan first, then executes steps sequentially.

### Architecture

```
User Input → [Plan] → [Execute Step 1] → [Execute Step 2] → ... → [Synthesize] → Output
```

### Implementation

```python
from pydantic import BaseModel

class Plan(BaseModel):
    steps: list[str]
    reasoning: str

class StepResult(BaseModel):
    step: str
    result: str
    success: bool

async def plan_and_execute(task: str) -> str:
    # Phase 1: Create plan
    plan = await create_plan(task)

    # Phase 2: Execute each step
    results: list[StepResult] = []
    for step in plan.steps:
        result = await execute_step(step, context=results)
        results.append(result)

        # Re-plan if step failed
        if not result.success:
            plan = await replan(task, results)

    # Phase 3: Synthesize results
    return await synthesize(task, results)
```

### When to Use
- Complex multi-step tasks
- Tasks benefiting from upfront planning
- When you need visibility into the execution plan

---

## Router Pattern

Classify input and route to specialized handlers.

### Architecture

```
User Input → [Classifier] → Handler A / Handler B / Handler C → Output
```

### Implementation

```python
from enum import Enum
from pydantic import BaseModel

class RouteType(str, Enum):
    TECHNICAL = "technical"
    BILLING = "billing"
    GENERAL = "general"

class RouteDecision(BaseModel):
    route: RouteType
    confidence: float
    reasoning: str

async def route_request(user_input: str) -> str:
    # Classify with structured output
    decision = await classify(user_input, RouteDecision)

    # Route to specialist
    handlers = {
        RouteType.TECHNICAL: handle_technical,
        RouteType.BILLING: handle_billing,
        RouteType.GENERAL: handle_general,
    }

    handler = handlers[decision.route]
    return await handler(user_input)
```

### When to Use
- Multiple specialized domains
- Different processing requirements per category
- When you need routing transparency

---

## Chain Pattern

Fixed sequence of LLM calls, each transforming the output.

### Architecture

```
Input → [LLM Step 1] → [LLM Step 2] → [LLM Step 3] → Output
```

### Implementation

```python
async def research_chain(topic: str) -> dict:
    # Step 1: Generate research questions
    questions = await generate_questions(topic)

    # Step 2: Research each question (parallel)
    answers = await asyncio.gather(*[
        research_question(q) for q in questions
    ])

    # Step 3: Synthesize into report
    report = await synthesize_report(topic, questions, answers)

    # Step 4: Quality check
    validated = await validate_report(report)

    return validated
```

### When to Use
- Well-defined processing pipelines
- Each step has clear input/output contract
- Steps don't need dynamic decision-making

---

## Map-Reduce Pattern

Process items in parallel, then aggregate results.

### Architecture

```
Input Items → [Map: Process Each] → [Reduce: Aggregate] → Output
```

### Implementation

```python
import asyncio

async def map_reduce_analysis(documents: list[str]) -> str:
    # Map: Analyze each document in parallel
    analyses = await asyncio.gather(*[
        analyze_document(doc) for doc in documents
    ], return_exceptions=True)

    # Filter out failures
    successful = [a for a in analyses if not isinstance(a, Exception)]
    failed = [a for a in analyses if isinstance(a, Exception)]

    if failed:
        logger.warning(f"{len(failed)} documents failed analysis")

    # Reduce: Combine analyses
    summary = await combine_analyses(successful)

    return summary
```

### When to Use
- Processing multiple items independently
- Summarizing large document sets
- Parallel data extraction tasks

---

## Reflection Pattern

Agent generates output, critiques it, then improves.

### Architecture

```
Input → [Generate] → [Critique] → [Improve] → ... → Output
```

### Implementation

```python
async def reflect_and_improve(
    task: str,
    max_iterations: int = 3,
    quality_threshold: float = 0.8
) -> str:
    output = await generate_initial(task)

    for i in range(max_iterations):
        # Critique
        critique = await evaluate_output(task, output)

        if critique.score >= quality_threshold:
            break

        # Improve based on critique
        output = await improve_output(task, output, critique.feedback)

    return output
```

### When to Use
- High-quality output requirements
- Creative or writing tasks
- Code generation with correctness requirements

---

## Evaluator-Optimizer Pattern

Generate multiple candidates, evaluate, select or combine best.

### Architecture

```
Input → [Generate N Candidates] → [Evaluate Each] → [Select/Combine Best] → Output
```

### Implementation

```python
async def evaluate_and_optimize(task: str, n_candidates: int = 3) -> str:
    # Generate candidates in parallel
    candidates = await asyncio.gather(*[
        generate_candidate(task, temperature=0.7 + i * 0.1)
        for i in range(n_candidates)
    ])

    # Evaluate each
    evaluations = await asyncio.gather(*[
        evaluate_candidate(task, c) for c in candidates
    ])

    # Select best or combine
    best_idx = max(range(len(evaluations)), key=lambda i: evaluations[i].score)

    if evaluations[best_idx].score < 0.7:
        # Combine insights from top candidates
        return await combine_best(task, candidates, evaluations)

    return candidates[best_idx]
```

---

## State Machine Agent

Agent transitions between defined states with clear triggers.

### Architecture

```
[State A] --trigger--> [State B] --trigger--> [State C]
    |                      |
    +--error--> [Error]    +--timeout--> [Timeout]
```

### Implementation

```python
from enum import Enum
from dataclasses import dataclass, field
from typing import Any

class AgentState(Enum):
    GATHERING_INFO = "gathering_info"
    ANALYZING = "analyzing"
    GENERATING = "generating"
    REVIEWING = "reviewing"
    COMPLETE = "complete"
    ERROR = "error"

@dataclass
class AgentContext:
    state: AgentState = AgentState.GATHERING_INFO
    data: dict = field(default_factory=dict)
    history: list = field(default_factory=list)
    error_count: int = 0
    max_errors: int = 3

async def state_machine_agent(task: str) -> str:
    ctx = AgentContext()

    transitions = {
        AgentState.GATHERING_INFO: gather_info,
        AgentState.ANALYZING: analyze,
        AgentState.GENERATING: generate,
        AgentState.REVIEWING: review,
    }

    while ctx.state not in (AgentState.COMPLETE, AgentState.ERROR):
        handler = transitions.get(ctx.state)
        if not handler:
            ctx.state = AgentState.ERROR
            break

        try:
            ctx = await handler(ctx, task)
        except Exception as e:
            ctx.error_count += 1
            if ctx.error_count >= ctx.max_errors:
                ctx.state = AgentState.ERROR
            logger.error(f"State {ctx.state}: {e}")

    return ctx.data.get("result", "Agent failed to complete.")
```

---

## Pattern Selection Guide

```
Is the task well-defined with fixed steps?
├─ Yes → Chain Pattern
└─ No
   ├─ Does it need multiple data sources?
   │  ├─ Process independently? → Map-Reduce
   │  └─ Sequential reasoning? → ReAct
   ├─ Does it need high quality output?
   │  ├─ Self-improvement? → Reflection
   │  └─ Multiple candidates? → Evaluator-Optimizer
   ├─ Does it need input classification?
   │  └─ Yes → Router
   ├─ Is it complex with many steps?
   │  └─ Yes → Plan-and-Execute
   └─ Does it have clear states/transitions?
      └─ Yes → State Machine
```
