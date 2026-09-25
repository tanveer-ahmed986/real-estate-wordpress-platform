# Agent Architecture Patterns

Comprehensive patterns for designing AI agent architectures. Based on best practices from Anthropic, OpenAI, LangChain, and the broader agent ecosystem.

---

## Pattern 1: ReAct (Reasoning + Acting)

The foundational agent loop. Agent thinks about what to do, takes an action, observes the result, and repeats.

### How It Works

```
User Input → Think (reason about next step)
                → Act (call tool)
                    → Observe (process result)
                        → Think again (or return final answer)
```

### When to Use
- General-purpose tool-using agents
- Tasks requiring iterative exploration
- When the number of steps is unpredictable

### Implementation Pattern

```python
# LangGraph ReAct
from langgraph.prebuilt import create_react_agent

agent = create_react_agent(model, tools, prompt="You are a helpful assistant.")
result = agent.invoke({"messages": [("user", "Find sales data for Q4")]})

# OpenAI Agents SDK
from agents import Agent, Runner

agent = Agent(
    name="researcher",
    instructions="You research topics thoroughly.",
    tools=[web_search, file_reader]
)
result = Runner.run_sync(agent, "Research Q4 sales trends")
```

### Configuration Knobs
| Parameter | Purpose | Typical Value |
|-----------|---------|---------------|
| `max_iterations` | Prevent infinite loops | 10-25 |
| `early_stopping` | Stop when confident | "generate" or "force" |
| `return_intermediate_steps` | Debug visibility | True in dev |

### Pitfalls
- Can loop indefinitely without max iterations
- May repeat failed actions without guidance
- Token cost grows linearly with steps

---

## Pattern 2: Plan-and-Execute

Agent creates a complete plan first, then executes each step. Good for complex tasks where upfront planning reduces errors.

### How It Works

```
User Input → Planner Agent (creates step-by-step plan)
                → Executor Agent (executes step 1)
                    → Executor Agent (executes step 2)
                        → ...
                            → Re-planner (adjust remaining steps if needed)
                                → Final synthesis
```

### When to Use
- Complex multi-step tasks with clear sub-goals
- When step order matters
- Tasks where re-planning after partial execution improves outcomes

### Implementation Pattern

```python
# LangGraph Plan-and-Execute
from langgraph.graph import StateGraph

class PlanExecuteState(TypedDict):
    input: str
    plan: list[str]
    past_steps: list[tuple[str, str]]
    response: str

def planner(state):
    plan = llm.invoke(f"Create a plan for: {state['input']}")
    return {"plan": parse_steps(plan)}

def executor(state):
    current_step = state["plan"][len(state["past_steps"])]
    result = agent.invoke(current_step)
    return {"past_steps": [(current_step, result)]}

def replan(state):
    # Adjust remaining plan based on results so far
    revised = llm.invoke(f"Revise plan given results: {state['past_steps']}")
    return {"plan": parse_steps(revised)}
```

### Best Practices
- Keep plans at 3-7 steps (LLMs struggle with longer plans)
- Include re-planning checkpoints every 2-3 steps
- Use structured output for plan steps (numbered list, JSON)
- Allow executor to signal "step failed, needs replanning"

---

## Pattern 3: Routing (Input Classification)

Classifies input and routes to specialized handlers. Separation of concerns with optimal handling per category.

### How It Works

```
User Input → Router (classify intent/type)
                → Handler A (if type A)
                → Handler B (if type B)
                → Handler C (if type C)
                → Fallback (if unclassified)
```

### When to Use
- Multi-domain systems (support, sales, technical)
- When different inputs need fundamentally different handling
- Cost optimization (route simple queries to cheaper models)

### Implementation Pattern

```python
# LangGraph routing
def route_input(state):
    classification = llm.invoke(
        f"Classify this request into: billing, technical, sales, general\n"
        f"Request: {state['input']}\nCategory:"
    )
    return classification.strip().lower()

graph = StateGraph(State)
graph.add_node("billing_agent", billing_handler)
graph.add_node("technical_agent", technical_handler)
graph.add_node("sales_agent", sales_handler)
graph.add_conditional_edges("router", route_input, {
    "billing": "billing_agent",
    "technical": "technical_agent",
    "sales": "sales_agent",
})
```

### Routing Strategies

| Strategy | How | Pros | Cons |
|----------|-----|------|------|
| LLM classification | Prompt LLM to classify | Flexible, handles nuance | Slower, costs tokens |
| Embedding similarity | Compare to category embeddings | Fast, cheap | Less nuanced |
| Keyword/regex | Pattern match | Fastest, free | Brittle, limited |
| Hybrid | Keywords first, LLM fallback | Balanced | More complex |

---

## Pattern 4: Parallelization

Run multiple agents simultaneously for speed or consensus.

### Variants

**Sectioning**: Break task into independent parallel subtasks.
```
Input → [Agent A: subtask 1] + [Agent B: subtask 2] + [Agent C: subtask 3]
            → Synthesizer (combine results)
```

**Voting**: Run same task multiple times for reliability.
```
Input → [Agent 1: same task] + [Agent 2: same task] + [Agent 3: same task]
            → Aggregator (majority vote / best-of-N)
```

### When to Use
- Independent subtasks that can run concurrently
- Quality-critical outputs needing consensus
- Content moderation (multiple safety checks)
- Code review (security + style + correctness in parallel)

### Implementation Pattern

```python
# LangGraph parallel with fan-out/fan-in
import asyncio

async def parallel_execution(state):
    tasks = [
        security_agent.ainvoke(state),
        style_agent.ainvoke(state),
        correctness_agent.ainvoke(state),
    ]
    results = await asyncio.gather(*tasks)
    return {"reviews": results}
```

---

## Pattern 5: Orchestrator-Workers

Central orchestrator dynamically decomposes tasks and delegates to specialized workers.

### How It Works

```
Input → Orchestrator (analyze, decompose, delegate)
            → Worker A (execute subtask) → result
            → Worker B (execute subtask) → result
            → Orchestrator (synthesize, decide next)
                → Worker C (if needed) → result
                    → Orchestrator (final synthesis)
```

### When to Use
- Tasks with unpredictable subtask structure
- Coding across multiple files
- Research requiring dynamic exploration

### Implementation Pattern

```python
# CrewAI hierarchical process
from crewai import Agent, Task, Crew, Process

manager = Agent(role="Project Manager", goal="Coordinate team", backstory="...")
researcher = Agent(role="Researcher", goal="Find information", tools=[search])
writer = Agent(role="Writer", goal="Write content", tools=[file_write])

crew = Crew(
    agents=[manager, researcher, writer],
    tasks=[research_task, writing_task],
    process=Process.hierarchical,
    manager_agent=manager
)
result = crew.kickoff()
```

---

## Pattern 6: Evaluator-Optimizer

One agent generates, another evaluates, iterating until quality threshold met.

### How It Works

```
Input → Generator (produce output)
            → Evaluator (score/critique)
                → If score < threshold:
                    → Generator (revise based on feedback)
                        → Evaluator (re-score)
                → If score >= threshold:
                    → Return final output
```

### When to Use
- Content generation requiring quality control
- Code generation with test validation
- Translation with fluency/accuracy checks
- Any task with clear evaluation criteria

### Implementation Pattern

```python
def evaluator_optimizer_loop(task, max_rounds=3, threshold=0.8):
    output = generator.invoke(task)
    for round in range(max_rounds):
        evaluation = evaluator.invoke(f"Evaluate:\n{output}")
        score = parse_score(evaluation)
        if score >= threshold:
            return output
        feedback = parse_feedback(evaluation)
        output = generator.invoke(f"Revise based on feedback:\n{feedback}\n\nOriginal:\n{output}")
    return output  # Best effort after max rounds
```

---

## Pattern 7: Prompt Chaining

Sequential LLM calls where each step processes the previous output, with optional verification gates between steps.

### How It Works

```
Input → LLM Step 1 → [Gate: verify] → LLM Step 2 → [Gate: verify] → LLM Step 3 → Output
```

### When to Use
- Fixed, predictable multi-stage processing
- When each stage has distinct instructions
- Content pipelines (draft → edit → format)
- Data extraction (parse → validate → enrich)

### Best Practices
- Add programmatic gates between steps (not just LLM checks)
- Keep each step focused on a single transformation
- Use structured output for reliable parsing between steps
- Consider parallel steps where stages are independent

---

## Pattern Selection Decision Tree

```
Is the task structure predictable?
├── YES: How many stages?
│   ├── 1 stage with tools → ReAct
│   ├── Fixed multi-stage → Prompt Chaining
│   └── Multi-stage with quality needs → Evaluator-Optimizer
├── NO: Is input varied?
│   ├── YES: Different types need different handling → Routing
│   └── NO: Complex decomposition needed?
│       ├── YES → Orchestrator-Workers
│       └── NO: Can subtasks run in parallel?
│           ├── YES → Parallelization
│           └── NO → Plan-and-Execute
```

---

## Combining Patterns

Production systems often combine multiple patterns:

| Combination | Example |
|-------------|---------|
| Routing + ReAct | Route to specialized ReAct agents per domain |
| Plan-Execute + Parallelization | Plan steps, execute independent ones in parallel |
| Orchestrator + Evaluator | Orchestrator delegates, evaluator checks quality |
| Routing + Prompt Chain | Route then apply domain-specific pipeline |
| ReAct + Human-in-Loop | Agent works, pauses for human approval at key points |
