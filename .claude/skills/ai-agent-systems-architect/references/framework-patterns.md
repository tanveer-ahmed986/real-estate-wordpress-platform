# Framework-Specific Agent Patterns

Code patterns and best practices for each major AI agent framework.

---

## LangGraph

### Core Concepts
- **StateGraph**: Define agent workflows as directed graphs
- **Nodes**: Functions that process and update state
- **Edges**: Connections between nodes (conditional or fixed)
- **Checkpointing**: Persist state for resume, time-travel, human-in-loop

### Basic Agent Pattern

```python
from langgraph.graph import StateGraph, MessagesState, START, END
from langgraph.prebuilt import ToolNode, tools_condition

def agent(state: MessagesState):
    response = model.invoke(state["messages"])
    return {"messages": [response]}

tools_node = ToolNode(tools=[search, calculator])

graph = StateGraph(MessagesState)
graph.add_node("agent", agent)
graph.add_node("tools", tools_node)
graph.add_edge(START, "agent")
graph.add_conditional_edges("agent", tools_condition)
graph.add_edge("tools", "agent")

app = graph.compile(checkpointer=SqliteSaver.from_conn_string(":memory:"))
```

### Multi-Agent Supervisor

```python
from langgraph.graph import StateGraph
from typing import Literal

class SupervisorState(TypedDict):
    messages: list
    next: str

def supervisor(state):
    response = llm.invoke([
        SystemMessage("You manage: researcher, coder. Choose who to route to, or FINISH."),
        *state["messages"]
    ])
    return {"next": parse_next(response)}

def route(state) -> Literal["researcher", "coder", "__end__"]:
    return state["next"]

graph = StateGraph(SupervisorState)
graph.add_node("supervisor", supervisor)
graph.add_node("researcher", researcher_agent)
graph.add_node("coder", coder_agent)
graph.add_conditional_edges("supervisor", route)
graph.add_edge("researcher", "supervisor")
graph.add_edge("coder", "supervisor")
```

### Human-in-the-Loop

```python
from langgraph.types import interrupt, Command

def human_approval_node(state):
    decision = interrupt({"question": "Approve this action?", "details": state["action"]})
    if decision == "approve":
        return Command(goto="execute")
    return Command(goto="cancel")
```

### Best Practices
- Use `MessagesState` for chat-based agents
- Use `TypedDict` for structured workflow state
- Add checkpointing for any multi-step workflow
- Use `interrupt` for human-in-the-loop, not custom solutions
- Keep node functions pure (state in, state out)

---

## CrewAI

### Core Concepts
- **Agent**: Autonomous unit with role, goal, backstory
- **Task**: Work item assigned to an agent with expected output
- **Crew**: Team of agents executing tasks together
- **Process**: Execution strategy (sequential, hierarchical)

### Basic Crew Pattern

```python
from crewai import Agent, Task, Crew, Process

researcher = Agent(
    role="Senior Research Analyst",
    goal="Uncover cutting-edge developments in AI",
    backstory="Expert analyst at a leading tech think tank",
    tools=[search_tool, scrape_tool],
    verbose=True,
    memory=True,
    reasoning=True  # Enable step-by-step reasoning
)

writer = Agent(
    role="Tech Content Strategist",
    goal="Craft compelling content on tech advancements",
    backstory="Renowned content strategist for tech publications",
    tools=[file_tool],
    verbose=True
)

research_task = Task(
    description="Research latest AI agent frameworks in 2025",
    expected_output="Comprehensive report with key findings",
    agent=researcher,
    output_file="research.md"
)

writing_task = Task(
    description="Write a blog post based on the research",
    expected_output="4-paragraph blog post in markdown",
    agent=writer,
    context=[research_task]  # Depends on research
)

crew = Crew(
    agents=[researcher, writer],
    tasks=[research_task, writing_task],
    process=Process.sequential,
    memory=True,
    verbose=True
)

result = crew.kickoff()
```

### CrewAI Flow (Event-Driven)

```python
from crewai.flow.flow import Flow, listen, start

class ContentFlow(Flow):
    @start()
    def research(self):
        result = research_crew.kickoff()
        return result

    @listen(research)
    def write_draft(self, research_result):
        self.state["research"] = research_result
        return writing_crew.kickoff(inputs={"research": research_result})

    @listen(write_draft)
    def review(self, draft):
        return review_crew.kickoff(inputs={"draft": draft})

flow = ContentFlow()
result = flow.kickoff()
```

### Best Practices
- Write detailed backstories (improves agent behavior significantly)
- Use `expected_output` in tasks to guide format
- Enable `memory=True` for multi-task crews
- Use `context` to create task dependencies
- Set `allow_delegation=True` only when agents should collaborate
- Use hierarchical process for 4+ agents with a manager

---

## OpenAI Agents SDK

### Core Concepts
- **Agent**: LLM with instructions, tools, and handoffs
- **Runner**: Executes agent loop (handles tool calls, handoffs)
- **Handoffs**: Transfer control between agents
- **Guardrails**: Input/output validation running in parallel

### Basic Agent Pattern

```python
from agents import Agent, Runner

agent = Agent(
    name="customer_support",
    instructions="""You are a helpful customer support agent.
    Use tools to look up orders and process requests.
    If the question is about billing, hand off to billing_agent.""",
    tools=[lookup_order, check_status],
    handoffs=[billing_agent, technical_agent]
)

# Synchronous
result = Runner.run_sync(agent, "Where is my order #12345?")

# Asynchronous
result = await Runner.run(agent, "Where is my order #12345?")

# Streaming
async for event in Runner.run_streamed(agent, "Where is my order #12345?"):
    if event.type == "agent_updated_stream_event":
        print(f"Agent: {event.new_agent.name}")
    elif event.type == "run_item_stream_event":
        if event.item.type == "tool_call_item":
            print(f"Tool: {event.item.raw_item.name}")
```

### Handoff Pattern (Swarm)

```python
billing = Agent(
    name="billing",
    instructions="Handle billing questions. Use tools to look up invoices.",
    tools=[get_invoice, process_refund],
    handoffs=[triage_agent]  # Can hand back
)

technical = Agent(
    name="technical",
    instructions="Handle technical issues. Troubleshoot problems.",
    tools=[check_system_status, create_ticket],
    handoffs=[triage_agent]
)

triage = Agent(
    name="triage",
    instructions="Determine if this is billing or technical. Route accordingly.",
    handoffs=[billing, technical]
)
```

### Guardrails

```python
from agents import input_guardrail, output_guardrail, GuardrailFunctionOutput

@input_guardrail
async def check_safety(ctx, agent, input):
    result = await Runner.run(safety_agent, input, context=ctx)
    return GuardrailFunctionOutput(
        output_info={"safe": result.final_output == "safe"},
        tripwire_triggered=(result.final_output != "safe")
    )

agent = Agent(
    name="assistant",
    instructions="Help users.",
    input_guardrails=[check_safety],
    output_guardrails=[check_pii]
)
```

### Best Practices
- Keep agent instructions focused and specific
- Use handoffs instead of complex routing logic
- Guardrails run in parallel with agent execution (no latency cost)
- Use sessions for persistent memory across runs
- Use MCP servers for standardized tool integration

---

## AutoGen / AG2

### Core Concepts
- **ConversableAgent**: Base agent that can send/receive messages
- **AssistantAgent**: LLM-powered agent
- **Teams**: SelectorGroupChat, Swarm, GraphFlow
- **Code execution**: Built-in sandboxed code execution

### Group Chat Pattern

```python
from autogen_agentchat.agents import AssistantAgent
from autogen_agentchat.teams import SelectorGroupChat
from autogen_agentchat.conditions import TextMentionTermination

planner = AssistantAgent("planner", model_client=model,
    system_message="You plan tasks and coordinate the team.")
coder = AssistantAgent("coder", model_client=model,
    system_message="You write Python code to solve problems.")
reviewer = AssistantAgent("reviewer", model_client=model,
    system_message="You review code for correctness and style.")

termination = TextMentionTermination("APPROVED")

team = SelectorGroupChat(
    participants=[planner, coder, reviewer],
    model_client=model,
    termination_condition=termination,
    selector_prompt="Select the next speaker based on the conversation."
)

result = await team.run(task="Build a data analysis script for sales.csv")
```

### GraphFlow (Explicit Workflow)

```python
from autogen_agentchat.teams import GraphFlow

flow = GraphFlow(
    participants=[researcher, analyst, writer],
    graph={
        "researcher": ["analyst"],
        "analyst": ["writer"],
        "writer": []  # Terminal
    }
)
result = await flow.run(task="Analyze market trends and write a report")
```

### Best Practices
- Use `SelectorGroupChat` for dynamic collaboration
- Use `GraphFlow` for deterministic workflows
- Use `Swarm` for handoff-based routing
- Set clear termination conditions (avoid infinite conversations)
- Use code execution for data analysis tasks

---

## Semantic Kernel

### Core Concepts
- **Agent**: Abstract base (ChatCompletionAgent, OpenAIAssistantAgent)
- **Plugins**: Functions exposed to agents
- **AgentThread**: Conversation state abstraction
- **Orchestration**: Concurrent, Sequential, Handoff, GroupChat patterns

### Basic Agent Pattern

```python
from semantic_kernel.agents import ChatCompletionAgent
from semantic_kernel import Kernel

kernel = Kernel()
kernel.add_plugin(CRMPlugin(), "crm")
kernel.add_plugin(EmailPlugin(), "email")

agent = ChatCompletionAgent(
    kernel=kernel,
    name="sales_assistant",
    instructions="Help sales reps manage their pipeline.",
    service_id="openai-chat"
)

thread = None
async for response in agent.invoke("Show me my top 5 deals"):
    print(response.content)
    thread = response.thread
```

### Multi-Agent Orchestration

```python
from semantic_kernel.agents import (
    HandoffOrchestration, SequentialOrchestration, ConcurrentOrchestration
)

# Handoff-based (like swarm)
orchestration = HandoffOrchestration(
    members=[triage_agent, billing_agent, tech_agent],
    description="Customer support system"
)

# Sequential pipeline
orchestration = SequentialOrchestration(
    members=[research_agent, analysis_agent, report_agent]
)

# Concurrent (parallel execution)
orchestration = ConcurrentOrchestration(
    members=[security_reviewer, style_reviewer, perf_reviewer]
)

runtime = InProcessRuntime()
runtime.start()
result = await orchestration.invoke("Review this PR", runtime=runtime)
```

### Best Practices
- Use Kernel plugins for tool integration
- Use declarative YAML specs for agent configuration
- Choose orchestration pattern based on workflow needs
- Use AgentThread for conversation state management
- Leverage Azure AI integration for enterprise deployments

---

## Anthropic / Claude (Custom Implementation)

### Core Concepts
- **Tool use**: JSON schema-based function definitions
- **Agentic loop**: Recursive tool_use → tool_result cycle
- **MCP**: Model Context Protocol for standardized tool servers

### Agentic Loop Pattern

```python
import anthropic

client = anthropic.Anthropic()

tools = [
    {
        "name": "search_crm",
        "description": "Search CRM contacts by name or email",
        "input_schema": {
            "type": "object",
            "properties": {
                "query": {"type": "string", "description": "Search term"}
            },
            "required": ["query"]
        }
    }
]

def run_agent(user_message: str, max_steps: int = 10):
    messages = [{"role": "user", "content": user_message}]

    for step in range(max_steps):
        response = client.messages.create(
            model="claude-sonnet-4-20250514",
            max_tokens=4096,
            system="You are a helpful CRM assistant.",
            tools=tools,
            messages=messages
        )

        # Check if agent wants to use tools
        if response.stop_reason == "tool_use":
            # Process each tool call
            tool_results = []
            for block in response.content:
                if block.type == "tool_use":
                    result = execute_tool(block.name, block.input)
                    tool_results.append({
                        "type": "tool_result",
                        "tool_use_id": block.id,
                        "content": json.dumps(result)
                    })
            messages.append({"role": "assistant", "content": response.content})
            messages.append({"role": "user", "content": tool_results})
        else:
            # Agent is done
            return response.content[0].text

    return "Max steps reached"
```

### Best Practices
- Keep tool descriptions clear and specific
- Use structured output (JSON mode) for reliable parsing
- Implement max iterations to prevent runaway loops
- Use extended thinking for complex reasoning tasks
- Consider MCP for standardized tool management
- Follow Anthropic's "building effective agents" patterns (simplicity, transparency)
