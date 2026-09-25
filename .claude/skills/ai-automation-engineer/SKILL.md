---
name: ai-automation-engineer
description: |
  Professional AI automation engineering for production-grade intelligent workflows and agent systems.
  This skill should be used when users ask to design AI automations, build agent workflows,
  orchestrate multi-agent systems, create LLM pipelines, implement RAG systems, design AI-powered
  ETL processes, build autonomous agents, set up prompt chains, or architect AI-driven automation
  infrastructure.
  Trigger: /ai-automation-engineer, /ai-automation, /ai-agent, /ai-workflow, /ai-pipeline
---

# AI Automation Engineer

Design, build, and deploy production-grade AI automation systems with embedded expertise
in agent architectures, LLM orchestration, prompt engineering, and intelligent workflow design.

## What This Skill Does

- Architects AI agent systems (single-agent, multi-agent, hierarchical, swarm)
- Designs LLM-powered automation pipelines (sequential, parallel, conditional, iterative)
- Implements RAG (Retrieval-Augmented Generation) systems with vector stores
- Builds tool-using agents with function calling and structured output
- Creates prompt chains with template management and version control
- Orchestrates human-in-the-loop workflows with approval gates
- Implements guardrails, safety layers, and output validation
- Designs observability, evaluation, and cost optimization strategies
- Integrates with frameworks: LangChain/LangGraph, CrewAI, AutoGen, Semantic Kernel, Anthropic SDK

## What This Skill Does NOT Do

- Train or fine-tune foundation models
- Manage cloud infrastructure provisioning (use IaC tools)
- Replace domain-specific ML pipelines (computer vision, NLP model training)
- Handle real-time streaming ML inference at scale
- Manage vector database administration (schema design only)

---

## Before Implementation

Gather context to ensure successful implementation:

| Source | Gather |
|--------|--------|
| **Codebase** | Existing project structure, language/framework, API patterns, dependencies |
| **Conversation** | User's specific automation goal, scale requirements, constraints |
| **Skill References** | Agent patterns from `references/`, framework docs, best practices |
| **User Guidelines** | Team conventions, security policies, compliance requirements |

Ensure all required context is gathered before implementing.
Only ask user for THEIR specific requirements (domain expertise is in this skill).

---

## Required Clarifications

### Tier 1 (Ask First)

1. **Automation goal**: "What process are you automating and what's the desired outcome?"
2. **Architecture pattern**: "What type of AI system?"
   - Single agent with tools
   - Multi-agent orchestration
   - LLM pipeline / prompt chain
   - RAG system
   - Event-driven AI workflow
   - Hybrid (describe)

### Tier 2 (Ask After Understanding Goal)

3. **Tech stack**: "What's your environment?"
   - Language (Python, TypeScript, etc.)
   - Framework preference (LangChain, CrewAI, AutoGen, Anthropic SDK, custom)
   - LLM provider (Anthropic, OpenAI, Azure, local models)
4. **Scale & constraints**: "What are your operational requirements?"
   - Expected throughput (requests/min)
   - Latency requirements
   - Budget/cost constraints
   - Compliance requirements (PII, HIPAA, SOC2)

### Tier 3 (Optional, Ask If Complex)

5. **Integration points**: "What external systems connect to this?"
6. **Existing resources**: "Any existing prompts, schemas, or workflows to incorporate?"

### If User Skips Clarifications

- **Tier 1 skipped**: Cannot proceed — explain why automation goal and architecture pattern are essential
- **Tier 2 skipped**: Default to Python + Anthropic SDK, note assumption explicitly
- **Tier 3 skipped**: Proceed with standard integration patterns and no pre-existing resources

---

## Architecture Decision Tree

```
What are you building?
│
├─ Single task automation
│  ├─ Deterministic steps? → Prompt Chain (see references/prompt-engineering.md)
│  ├─ Needs external data? → RAG Pipeline (see references/rag-patterns.md)
│  └─ Needs tool use? → Tool-Using Agent (see references/agent-patterns.md)
│
├─ Multi-step workflow
│  ├─ Sequential processing? → DAG Pipeline
│  ├─ Conditional branching? → State Machine / LangGraph
│  └─ Human approval needed? → HITL Workflow
│
├─ Multi-agent system
│  ├─ Specialized roles? → Crew Pattern (see references/multi-agent-patterns.md)
│  ├─ Competitive/debate? → Adversarial Pattern
│  ├─ Hierarchical delegation? → Supervisor Pattern
│  └─ Dynamic collaboration? → Swarm Pattern
│
└─ Production system
   ├─ High reliability? → Add circuit breakers + fallbacks
   ├─ Cost sensitive? → Add caching + model routing
   ├─ Compliance required? → Add guardrails + audit logging
   └─ Scale required? → Add queuing + async processing
```

---

## Core Workflow

### Phase 1: Design

1. **Identify automation scope** — Map the process, inputs, outputs, decision points
2. **Select architecture** — Use decision tree above; read relevant `references/` files
3. **Design agent topology** — Define agents, roles, tools, communication patterns
4. **Design data flow** — Input sources → processing stages → output destinations
5. **Define safety boundaries** — Guardrails, rate limits, cost caps, approval gates

### Phase 2: Implement

6. **Build prompt templates** — Structured prompts with variables, few-shot examples
7. **Implement tool interfaces** — Function definitions, input/output schemas, error handling
8. **Wire orchestration logic** — Agent routing, state management, workflow control
9. **Add observability** — Tracing, logging, token counting, cost tracking
10. **Implement error handling** — Retries, fallbacks, circuit breakers, dead letter queues

### Phase 3: Validate & Deploy

11. **Write evaluations** — Deterministic tests + LLM-as-judge + human review
12. **Load test** — Throughput, latency, cost per operation
13. **Deploy with controls** — Feature flags, gradual rollout, kill switches
14. **Monitor & iterate** — Dashboards, alerts, feedback loops, prompt versioning

---

## Agent Design Patterns

| Pattern | When to Use | Complexity |
|---------|-------------|------------|
| **ReAct** | Agent needs to reason + act iteratively | Medium |
| **Plan-and-Execute** | Complex tasks needing upfront planning | Medium |
| **Tool-Use Loop** | Agent picks tools based on context | Low-Medium |
| **Router** | Classify input → route to specialist | Low |
| **Chain** | Fixed sequence of LLM calls | Low |
| **Map-Reduce** | Process items in parallel, aggregate | Medium |
| **Reflection** | Agent critiques and improves own output | Medium |
| **Supervisor** | Manager agent delegates to worker agents | High |
| **Swarm** | Agents dynamically hand off to each other | High |
| **Evaluator-Optimizer** | Generate → evaluate → refine loop | Medium |

See `references/agent-patterns.md` for implementation details.

---

## Standards Enforcement

### Must Follow

- [ ] Structured output with schema validation (Pydantic/Zod) for all LLM responses
- [ ] Retry with exponential backoff + jitter on transient failures
- [ ] Token/cost tracking on every LLM call
- [ ] Input sanitization to prevent prompt injection
- [ ] Timeout protection on all external calls (LLM, API, DB)
- [ ] Idempotency keys for stateful operations
- [ ] Graceful degradation with fallback chains
- [ ] Trace IDs propagated across all agent calls
- [ ] Prompt templates separated from business logic
- [ ] Environment-based configuration (no hardcoded keys/endpoints)

### Must Avoid

- Unbounded agent loops without max iteration limits
- Raw string concatenation for prompts (use templates)
- Swallowing LLM errors silently
- Storing PII in prompt logs without redaction
- Hardcoded model names (use config/env vars)
- Synchronous LLM calls in hot paths without caching
- Single point of failure without fallback providers
- Unvalidated LLM output used in downstream operations
- Exposing raw LLM responses to end users without sanitization
- Deploying without evaluation metrics baseline

---

## Error Handling

| Scenario | Detection | Recovery |
|----------|-----------|----------|
| LLM rate limit | 429 status / RateLimitError | Exponential backoff, queue overflow to DLQ |
| LLM timeout | Request exceeds timeout threshold | Retry with shorter prompt, fallback model |
| Invalid LLM output | Schema validation failure | Re-prompt with correction hint, max 3 retries |
| Tool execution failure | Exception in tool function | Log error, return error context to agent, retry |
| Token limit exceeded | TokenLimitError / context overflow | Truncate/summarize context, use larger model |
| Agent loop detected | Iteration count > max_iterations | Force stop, return partial result, alert |
| Prompt injection attempt | Input pattern detection / guardrail | Block request, log attempt, alert security |
| Cost threshold exceeded | Running cost > budget limit | Pause pipeline, notify operator, await approval |
| Model provider outage | Connection failure / 5xx errors | Failover to backup provider, serve cached results |
| Data quality issue | Validation on input data | Quarantine bad data, process valid subset, alert |

---

## Output Checklist

Before delivering any AI automation implementation, verify:

### Architecture
- [ ] Architecture pattern matches use case complexity
- [ ] Agent topology clearly defined (roles, tools, communication)
- [ ] Data flow documented (input → processing → output)
- [ ] Failure modes identified with recovery strategies

### Implementation
- [ ] Prompts templated with variables (no hardcoded content)
- [ ] Structured output with schema validation
- [ ] All tools have input/output schemas and error handling
- [ ] Retry logic with backoff on all external calls
- [ ] Max iteration limits on all agent loops
- [ ] Timeouts on all async operations

### Safety & Security
- [ ] Input sanitization / prompt injection prevention
- [ ] Output validation before downstream use
- [ ] PII redaction in logs and traces
- [ ] Cost controls (per-request and aggregate budgets)
- [ ] Rate limiting on user-facing endpoints

### Observability
- [ ] Trace IDs across all agent calls
- [ ] Token usage and cost tracking
- [ ] Latency metrics per step
- [ ] Error rate monitoring
- [ ] Evaluation metrics baseline established

### Production Readiness
- [ ] Evaluation suite (deterministic + LLM-judge tests)
- [ ] Fallback providers configured
- [ ] Graceful degradation paths tested
- [ ] Kill switch / circuit breaker in place
- [ ] Documentation for operators

---

## Official Documentation

| Resource | URL | Use For |
|----------|-----|---------|
| Anthropic SDK | https://docs.anthropic.com | Claude API, tool use, structured output, batch API |
| Anthropic Cookbook | https://github.com/anthropics/anthropic-cookbook | Agent patterns, RAG, function calling examples |
| LangChain | https://python.langchain.com/docs | Chains, agents, LCEL composition |
| LangGraph | https://langchain-ai.github.io/langgraph/ | Stateful multi-step agent workflows |
| LangSmith | https://docs.smith.langchain.com | Tracing, evaluation, monitoring |
| CrewAI | https://docs.crewai.com | Role-based multi-agent crews |
| AutoGen | https://microsoft.github.io/autogen/ | Multi-agent conversations, group chat |
| Semantic Kernel | https://learn.microsoft.com/en-us/semantic-kernel/ | Enterprise AI orchestration (.NET/Python) |
| OpenTelemetry | https://opentelemetry.io/docs/ | Distributed tracing for AI pipelines |
| Pydantic | https://docs.pydantic.dev | Structured output validation, schemas |

For patterns not covered in this skill's references, fetch from official docs above.

---

## Reference Files

| File | When to Read |
|------|--------------|
| `references/agent-patterns.md` | Designing single-agent or multi-agent architectures |
| `references/multi-agent-patterns.md` | Building crew, supervisor, or swarm systems |
| `references/prompt-engineering.md` | Crafting prompts, chains, few-shot examples, templates |
| `references/rag-patterns.md` | Building retrieval-augmented generation systems |
| `references/framework-guide.md` | Choosing and using LangChain, CrewAI, AutoGen, Anthropic SDK |
| `references/tool-use-patterns.md` | Implementing function calling and tool interfaces |
| `references/observability-and-eval.md` | Tracing, logging, evaluation frameworks, metrics |
| `references/security-and-guardrails.md` | Prompt injection prevention, PII handling, safety layers |
| `references/cost-optimization.md` | Token optimization, caching, model routing, budget controls |
| `references/production-deployment.md` | Deployment patterns, scaling, circuit breakers, monitoring |

### Searching References

For specific topics across reference files, use grep:
- Agent patterns: `grep -rl "ReAct\|Router\|Chain\|Supervisor\|Swarm" references/`
- RAG: `grep -rl "chunking\|embedding\|vector\|retrieval\|rerank" references/`
- Security: `grep -rl "injection\|PII\|guardrail\|sanitiz" references/`
- Cost: `grep -rl "cache\|budget\|token\|routing\|batch" references/`
- Deployment: `grep -rl "circuit.breaker\|retry\|fallback\|health.check\|kill.switch" references/`
