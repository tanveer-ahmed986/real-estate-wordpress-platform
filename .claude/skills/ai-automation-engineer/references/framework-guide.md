# AI Automation Framework Guide

Selection guide and implementation patterns for major AI automation frameworks.

**Last verified**: May 2025. Framework APIs evolve frequently — check official docs for breaking changes before implementation. Key areas that change: model IDs, SDK method signatures, default parameters.

---

## Framework Selection Matrix

| Criteria | Anthropic SDK | LangChain/LangGraph | CrewAI | AutoGen | Semantic Kernel |
|----------|--------------|-------------------|--------|---------|----------------|
| **Best For** | Direct API, simple agents | Complex workflows, DAGs | Role-based crews | Multi-agent conversations | Enterprise .NET/Python |
| **Complexity** | Low | Medium-High | Medium | Medium-High | Medium |
| **Flexibility** | High | Very High | Medium | High | High |
| **Multi-Agent** | Manual | LangGraph | Built-in | Built-in | Plugins |
| **Ecosystem** | Growing | Very Large | Growing | Growing | Microsoft |
| **Production Ready** | Yes | Yes | Maturing | Maturing | Yes |
| **Language** | Python, TS | Python, JS | Python | Python, .NET | Python, .NET |

### Decision Tree

```
What's your primary need?
│
├─ Direct LLM integration, minimal abstraction
│  └─ Anthropic SDK / OpenAI SDK
│
├─ Complex stateful workflows with branching
│  └─ LangGraph
│
├─ Role-based multi-agent collaboration
│  └─ CrewAI
│
├─ Multi-agent conversations and debates
│  └─ AutoGen
│
├─ Enterprise .NET integration
│  └─ Semantic Kernel
│
└─ Simple chains, basic RAG
   └─ LangChain or direct SDK
```

---

## Anthropic Claude SDK

### Direct API Pattern

```python
import anthropic

client = anthropic.Anthropic()  # Uses ANTHROPIC_API_KEY env var

# Simple completion
response = client.messages.create(
    model="claude-sonnet-4-5-20250929",
    max_tokens=1024,
    system="You are a helpful assistant.",
    messages=[{"role": "user", "content": "Hello"}],
)

# With tools
response = client.messages.create(
    model="claude-sonnet-4-5-20250929",
    max_tokens=1024,
    tools=[{
        "name": "get_weather",
        "description": "Get current weather for a location",
        "input_schema": {
            "type": "object",
            "properties": {
                "location": {"type": "string"}
            },
            "required": ["location"]
        }
    }],
    messages=[{"role": "user", "content": "What's the weather in Paris?"}],
)

# Streaming
with client.messages.stream(
    model="claude-sonnet-4-5-20250929",
    max_tokens=1024,
    messages=[{"role": "user", "content": "Tell me a story"}],
) as stream:
    for text in stream.text_stream:
        print(text, end="", flush=True)

# Extended thinking (Claude with reasoning)
response = client.messages.create(
    model="claude-sonnet-4-5-20250929",
    max_tokens=16000,
    thinking={
        "type": "enabled",
        "budget_tokens": 10000
    },
    messages=[{"role": "user", "content": "Solve this complex problem..."}],
)
```

### Batch API

```python
# For high-throughput, cost-efficient processing
batch = client.batches.create(
    requests=[
        {
            "custom_id": f"request-{i}",
            "params": {
                "model": "claude-sonnet-4-5-20250929",
                "max_tokens": 1024,
                "messages": [{"role": "user", "content": item}],
            }
        }
        for i, item in enumerate(items)
    ]
)

# Poll for results
result = client.batches.retrieve(batch.id)
```

### Best Practices
- Use `claude-sonnet-4-5-20250929` for most tasks (best cost/performance)
- Use `claude-opus-4-6` for complex reasoning
- Use `claude-haiku-4-5-20251001` for classification, routing, simple tasks
- Always set `max_tokens` appropriately
- Use `tool_choice` to force structured output
- Implement streaming for user-facing responses

---

## LangChain / LangGraph

### LangChain Basics

```python
from langchain_anthropic import ChatAnthropic
from langchain_core.prompts import ChatPromptTemplate
from langchain_core.output_parsers import PydanticOutputParser

# Model
llm = ChatAnthropic(model="claude-sonnet-4-5-20250929", temperature=0)

# Prompt template
prompt = ChatPromptTemplate.from_messages([
    ("system", "You are a helpful assistant specialized in {domain}."),
    ("human", "{question}"),
])

# Chain
chain = prompt | llm | PydanticOutputParser(pydantic_object=Answer)
result = chain.invoke({"domain": "AI", "question": "What is RAG?"})
```

### LangGraph Stateful Workflows

```python
from langgraph.graph import StateGraph, END
from typing import TypedDict, Annotated
import operator

class AgentState(TypedDict):
    messages: Annotated[list, operator.add]
    next_action: str
    iteration: int

def should_continue(state: AgentState) -> str:
    if state["iteration"] >= 5:
        return "end"
    if state["next_action"] == "finish":
        return "end"
    return "continue"

# Build graph
workflow = StateGraph(AgentState)

workflow.add_node("agent", agent_node)
workflow.add_node("tools", tool_node)
workflow.add_node("reflect", reflect_node)

workflow.set_entry_point("agent")
workflow.add_conditional_edges("agent", should_continue, {
    "continue": "tools",
    "end": END,
})
workflow.add_edge("tools", "reflect")
workflow.add_edge("reflect", "agent")

app = workflow.compile()
result = app.invoke({"messages": [user_message], "next_action": "", "iteration": 0})
```

### Best Practices
- Use LangGraph for stateful, multi-step workflows
- Use LCEL (LangChain Expression Language) for simple chains
- Implement checkpointing for long-running workflows
- Use LangSmith for observability

---

## CrewAI

### Basic Crew Setup

```python
from crewai import Agent, Task, Crew, Process

# Define agents
researcher = Agent(
    role="Senior Research Analyst",
    goal="Find comprehensive and accurate information",
    backstory="You are an expert researcher with 10 years of experience.",
    tools=[search_tool, scrape_tool],
    llm="anthropic/claude-sonnet-4-5-20250929",
    verbose=True,
    allow_delegation=False,
)

analyst = Agent(
    role="Data Analyst",
    goal="Analyze data and provide actionable insights",
    backstory="You are a data analyst known for finding patterns.",
    tools=[calculator_tool, chart_tool],
    llm="anthropic/claude-sonnet-4-5-20250929",
)

# Define tasks
research_task = Task(
    description="Research {topic} and gather key findings",
    expected_output="Comprehensive research report with sources",
    agent=researcher,
)

analysis_task = Task(
    description="Analyze the research findings and provide insights",
    expected_output="Analysis report with actionable recommendations",
    agent=analyst,
    context=[research_task],
)

# Create crew
crew = Crew(
    agents=[researcher, analyst],
    tasks=[research_task, analysis_task],
    process=Process.sequential,
    verbose=True,
)

result = crew.kickoff(inputs={"topic": "AI automation trends"})
```

### Best Practices
- Define clear, non-overlapping agent roles
- Use `expected_output` to guide agent behavior
- Set `allow_delegation=False` unless needed
- Use `context` to chain task dependencies

---

## AutoGen

### Multi-Agent Conversation

```python
from autogen import ConversableAgent, UserProxyAgent

# Create agents
assistant = ConversableAgent(
    name="assistant",
    system_message="You are a helpful AI assistant.",
    llm_config={"model": "claude-sonnet-4-5-20250929"},
)

user_proxy = UserProxyAgent(
    name="user_proxy",
    human_input_mode="NEVER",
    max_consecutive_auto_reply=5,
    code_execution_config={"work_dir": "workspace"},
)

# Two-agent conversation
user_proxy.initiate_chat(
    assistant,
    message="Create a Python script that analyzes CSV data.",
)
```

### Group Chat

```python
from autogen import GroupChat, GroupChatManager

planner = ConversableAgent(name="planner", system_message="Plan the approach...")
coder = ConversableAgent(name="coder", system_message="Write the code...")
reviewer = ConversableAgent(name="reviewer", system_message="Review the code...")

group_chat = GroupChat(
    agents=[planner, coder, reviewer],
    messages=[],
    max_round=10,
    speaker_selection_method="auto",
)

manager = GroupChatManager(groupchat=group_chat)
planner.initiate_chat(manager, message="Build a web scraper for news articles.")
```

---

## Semantic Kernel

### Basic Setup (Python)

```python
import semantic_kernel as sk
from semantic_kernel.connectors.ai.anthropic import AnthropicChatCompletion

kernel = sk.Kernel()
kernel.add_service(AnthropicChatCompletion(
    model_id="claude-sonnet-4-5-20250929",
    api_key=os.environ["ANTHROPIC_API_KEY"],
))

# Semantic function
summarize = kernel.create_function_from_prompt(
    "Summarize the following text in 3 bullet points:\n{{$input}}",
    function_name="summarize",
    plugin_name="text",
)

result = await kernel.invoke(summarize, input="Long text here...")
```

### Best Practices
- Use plugins for reusable function groups
- Use planners for dynamic task orchestration
- Integrate with Azure services for enterprise

---

## Framework Comparison: Same Task

### Task: Classify and route customer support tickets

**Anthropic SDK (Direct)**:
```python
# Most control, least abstraction
response = client.messages.create(
    model="claude-sonnet-4-5-20250929",
    tools=[classify_tool],
    tool_choice={"type": "tool", "name": "classify_ticket"},
    messages=[{"role": "user", "content": ticket_text}],
)
```

**LangChain**:
```python
# Good for chains, composable
chain = prompt | llm.with_structured_output(TicketClassification)
result = chain.invoke({"ticket": ticket_text})
```

**CrewAI**:
```python
# Best when multiple agents needed
# Overkill for single classification
```

### Recommendation by Use Case

| Use Case | Recommended | Why |
|----------|-------------|-----|
| Simple LLM calls | Anthropic SDK | Minimal overhead |
| Prompt chains | LangChain LCEL | Composable, clean |
| Stateful workflows | LangGraph | Built-in state management |
| Role-based teams | CrewAI | Natural agent definition |
| Agent conversations | AutoGen | Built-in conversation patterns |
| Enterprise .NET | Semantic Kernel | Microsoft ecosystem |
| Custom everything | Anthropic SDK + custom | Full control |
