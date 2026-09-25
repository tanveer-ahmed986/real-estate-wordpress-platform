---
name: ai-agent-systems-architect
description: |
  Professional AI agent systems architecture for production-grade autonomous workflows.
  This skill should be used when users ask to design AI agent systems, build autonomous
  AI workers, create tool-using agents (CRM, email, APIs, dashboards), architect
  multi-agent collaboration workflows, implement decision logic and memory-based
  operations, or plan agent infrastructure.
  Trigger: /ai-agent-architect, /agent-architect, /agent-system, /multi-agent
---

# AI Agent Systems Architect

Design and build production-grade autonomous AI agent systems with tool integration, multi-agent collaboration, decision logic, and memory-based operations.

## What This Skill Does

- Architects autonomous AI worker systems that perform tasks independently
- Designs tool-using agents integrating CRM, email, APIs, dashboards, and external services
- Plans multi-agent collaboration workflows with defined topologies and communication
- Implements decision engines, routing logic, and memory-based operations
- Applies security hardening, observability, and production deployment patterns
- Supports all major frameworks: LangGraph, CrewAI, OpenAI Agents SDK, AutoGen, Semantic Kernel, Claude/Anthropic

## What This Skill Does NOT Do

- Train or fine-tune LLM models
- Manage cloud infrastructure provisioning (use IaC tools)
- Build frontend UIs (only agent backend/orchestration)
- Handle data engineering pipelines unrelated to agents
- Provide vendor-specific pricing or billing guidance

---

## Before Implementation

Gather context to ensure successful implementation:

| Source | Gather |
|--------|--------|
| **Codebase** | Existing agent code, tool definitions, API clients, state stores |
| **Conversation** | User's specific agent requirements, scale, constraints |
| **Skill References** | Framework patterns from `references/` (architecture, tools, memory, security) |
| **User Guidelines** | Team conventions, approved frameworks, deployment targets |

Ensure all required context is gathered before implementing.
Only ask user for THEIR specific requirements (domain expertise is in this skill).

---

## Required Clarifications

Ask about USER's context (not domain knowledge). Pace questions across messages.

### Ask First (essential before any design)

1. **Agent scope**: "What tasks should agents perform autonomously?"
2. **Tools needed**: "What external systems must agents interact with? (CRM, email, APIs, DBs, dashboards)"
3. **Collaboration model**: "Single agent, pipeline, or multi-agent collaboration?"

### Ask After (refine after understanding scope)

4. **Framework preference**: "Any preferred framework, or should I recommend one based on your requirements?"
5. **Language/stack**: "What's your tech stack? (Python, TypeScript, .NET)"
6. **Deployment target**: "Where will agents run? (serverless, containers, queues, local)"

### Defaults When Not Specified

| Question | Default |
|----------|---------|
| Framework | Recommend based on requirements (see Framework Selection Guide) |
| Language | Python (broadest agent framework support) |
| Deployment | Containerized with queue-based async execution |
| Collaboration | Start with single agent, evolve to multi-agent when complexity demands |

---

## Architecture Decision Workflow

```
1. Classify → What type of agent system?
2. Select Pattern → Which architecture pattern fits?
3. Design Topology → How do agents collaborate?
4. Define Tools → What integrations are needed?
5. Plan Memory → What state must persist?
6. Add Safety → Guardrails, permissions, error handling
7. Instrument → Observability, tracing, cost tracking
8. Deploy → Production patterns and scaling
```

### Step 1: Classify Agent System Type

| Type | Description | When to Use |
|------|-------------|-------------|
| **Single autonomous worker** | One agent with tools performing tasks | Simple automation, single-domain tasks |
| **Pipeline/chain** | Sequential agents, each handling a stage | ETL, content pipelines, approval flows |
| **Collaborative multi-agent** | Agents with distinct roles collaborating | Complex problems requiring specialization |
| **Hierarchical orchestration** | Supervisor delegates to worker agents | Dynamic task decomposition |
| **Swarm** | Agents hand off based on context | Customer support, routing-heavy systems |

### Step 2: Select Architecture Pattern

See `references/architecture-patterns.md` for detailed patterns:

| Pattern | How It Works | Best For |
|---------|-------------|----------|
| **ReAct** | Think → Act → Observe loop | General tool-using agents |
| **Plan-and-Execute** | Plan all steps, then execute sequentially | Complex multi-step tasks |
| **Routing** | Classify input → delegate to specialist | Multi-domain handling |
| **Parallelization** | Run multiple agents simultaneously | Speed, consensus, validation |
| **Orchestrator-Workers** | Central agent decomposes and delegates | Unpredictable task structures |
| **Evaluator-Optimizer** | Generate → evaluate → refine loop | Quality-critical outputs |
| **Prompt Chaining** | Sequential LLM calls with gates | Fixed multi-stage processing |

### Step 3: Design Agent Topology

See `references/multi-agent-topologies.md` for detailed topologies:

| Topology | Communication | Coordination |
|----------|--------------|--------------|
| **Sequential** | Linear handoff | Implicit ordering |
| **Hierarchical** | Supervisor ↔ workers | Central control |
| **Peer-to-peer** | Any agent ↔ any agent | Distributed |
| **Blackboard** | Shared state space | Read/write to shared state |
| **Swarm** | Handoff-based | Local tool-based selection |
| **Graph (DAG)** | Directed edges | Explicit flow definition |

### Step 4: Define Tool Integration

See `references/tool-integration.md` for patterns:

| Tool Category | Examples | Integration Pattern |
|--------------|----------|-------------------|
| **Communication** | Email, Slack, SMS | API client with auth |
| **CRM** | Salesforce, HubSpot | REST/GraphQL with pagination |
| **Data** | SQL, MongoDB, vector DBs | Connection pool with retry |
| **External APIs** | Weather, payment, maps | Rate-limited HTTP client |
| **Code execution** | Python, shell, sandboxed | Isolated runtime with timeout |
| **File systems** | S3, local, GDrive | Streaming with size limits |
| **Dashboards** | Grafana, Metabase | API-driven query/update |

### Step 5: Plan Memory System

See `references/memory-systems.md` for detailed patterns:

| Memory Type | Scope | Storage | Use Case |
|-------------|-------|---------|----------|
| **Working memory** | Current task | In-context window | Active reasoning |
| **Short-term** | Current session | In-memory / Redis | Conversation continuity |
| **Long-term** | Cross-session | Vector DB / SQL | Knowledge accumulation |
| **Episodic** | Past experiences | Vector DB | Learning from history |
| **Semantic** | Domain knowledge | Vector DB / graph | Facts and relationships |
| **Entity** | Tracked objects | Key-value / SQL | User/object profiles |

### Step 6: Add Safety & Guardrails

See `references/security-guardrails.md`:

- Input validation and prompt injection defense
- Output filtering and content moderation
- Permission models (least-privilege tool access)
- Rate limiting and cost budgets
- Human-in-the-loop checkpoints
- Sandboxed execution environments

### Step 7: Instrument Observability

See `references/observability-production.md`:

- Structured logging per agent step
- Distributed tracing (OpenTelemetry / LangSmith)
- Token usage and cost tracking
- Latency monitoring per tool call
- Error rate dashboards and alerting

### Step 8: Deploy for Production

See `references/observability-production.md#production-deployment`:

- Queue-based async execution
- Container orchestration patterns
- Circuit breakers for LLM/tool failures
- Horizontal scaling strategies
- A/B testing agent configurations

---

## Framework Selection Guide

| Framework | Language | Best For | Multi-Agent | Memory |
|-----------|----------|----------|-------------|--------|
| **LangGraph** | Python/JS | Stateful graph workflows, complex flows | Supervisor, swarm, custom | Checkpointing, vector |
| **CrewAI** | Python | Role-based team collaboration | Hierarchical, sequential crews | Short/long/entity |
| **OpenAI Agents SDK** | Python | OpenAI-native, handoff patterns | Handoffs, guardrails | Sessions (SQL/Redis) |
| **AutoGen/AG2** | Python | Research, code generation, group chat | Selector, swarm, graph | Conversation, RAG |
| **Semantic Kernel** | .NET/Python/Java | Enterprise, Azure integration | Concurrent, sequential, handoff, group | Plugins, embeddings |
| **Custom (Anthropic)** | Any | Maximum control, Claude-optimized | Manual orchestration | Custom implementation |

---

## Anti-Patterns to Avoid

| Anti-Pattern | Problem | Instead |
|-------------|---------|---------|
| God agent | One agent doing everything | Decompose into specialists |
| Over-abstraction | Too many agent layers | Start simple, add layers when needed |
| Unbounded loops | Agent runs forever | Set max iterations and timeouts |
| Shared mutable state | Race conditions in multi-agent | Use message passing or locks |
| No error boundaries | One failure cascades | Circuit breakers per tool/agent |
| Prompt-only memory | Context window overflow | Externalize to vector/SQL stores |
| No cost controls | Runaway token spending | Budget limits and monitoring |
| Skipping evaluation | No quality feedback | Add evaluator agents or metrics |

---

## Output Checklist

Before delivering agent system design, verify:

- [ ] Agent roles and responsibilities clearly defined
- [ ] Architecture pattern selected and justified
- [ ] Tool integrations specified with auth and error handling
- [ ] Memory system designed for appropriate scope
- [ ] Multi-agent topology defined (if applicable)
- [ ] Decision/routing logic documented
- [ ] Security guardrails and permissions in place
- [ ] Observability and tracing configured
- [ ] Error handling and graceful degradation planned
- [ ] Production deployment strategy defined
- [ ] Cost estimation and budget controls set
- [ ] Human-in-the-loop checkpoints identified

---

## Reference Files

| File | When to Read |
|------|--------------|
| `references/architecture-patterns.md` | Selecting agent architecture (ReAct, Plan-Execute, routing, etc.) |
| `references/multi-agent-topologies.md` | Designing multi-agent collaboration and communication |
| `references/tool-integration.md` | Integrating CRM, email, APIs, dashboards, code execution |
| `references/memory-systems.md` | Designing memory (working, short-term, long-term, episodic, semantic) |
| `references/security-guardrails.md` | Security hardening, permissions, prompt injection defense |
| `references/observability-production.md` | Logging, tracing, deployment, scaling, cost management |
| `references/framework-patterns.md` | Framework-specific code patterns (LangGraph, CrewAI, OpenAI, AutoGen, SK) |
| `references/decision-routing.md` | Decision engines, conditional logic, state machines |
