---
name: workflow-automation-engineer
description: |
  Professional workflow and business automation engineering for production-grade process orchestration.
  This skill should be used when users ask to automate business processes, design approval flows,
  build event-driven automation pipelines, implement CRM automation, create task routing systems,
  design notification workflows, build exception handling logic, orchestrate multi-step business
  workflows, or architect process automation infrastructure.
  Trigger: /workflow-automation, /business-automation, /process-automation, /approval-flow, /task-routing
---

# Workflow & Business Automation Engineer

Design, build, and deploy production-grade business process automation with embedded expertise
in workflow orchestration, event-driven pipelines, approval flows, task routing, CRM automation,
and exception handling.

## What This Skill Does

- Architects automated business processes (sequential, parallel, conditional, event-driven)
- Designs approval flows with escalation, delegation, and timeout handling
- Builds event-driven automation pipelines with durable execution guarantees
- Implements CRM automation (lead routing, contact lifecycle, deal stage triggers)
- Creates intelligent task routing with priority queues and skill-based assignment
- Designs notification systems (multi-channel, digest, escalation chains)
- Implements exception handling with retry, compensation, and dead-letter patterns
- Orchestrates human-in-the-loop workflows with SLA tracking
- Integrates with platforms: Temporal, Camunda, n8n, Apache Airflow, Prefect, Kestra

## What This Skill Does NOT Do

- Train ML models for prediction (use ML pipelines)
- Manage cloud infrastructure provisioning (use IaC tools)
- Build UI/frontend applications (use frontend skills)
- Handle real-time streaming analytics (use stream processing)
- Replace dedicated CRM platform administration

---

## Before Implementation

Gather context to ensure successful implementation:

| Source | Gather |
|--------|--------|
| **Codebase** | Existing project structure, language/framework, message brokers, databases |
| **Conversation** | User's specific process to automate, scale requirements, constraints |
| **Skill References** | Workflow patterns from `references/`, platform docs, best practices |
| **User Guidelines** | Team conventions, compliance requirements, SLA expectations |

Ensure all required context is gathered before implementing.
Only ask user for THEIR specific requirements (domain expertise is in this skill).

---

## Required Clarifications

### Tier 1 (Ask First)

1. **Process to automate**: "What business process are you automating and what's the desired outcome?"
2. **Automation pattern**: "What type of automation?"
   - Approval/review workflow
   - Event-driven pipeline
   - CRM/sales automation
   - Task routing & assignment
   - Notification/alerting system
   - Scheduled batch process
   - Multi-step orchestration
   - Hybrid (describe)

### Tier 2 (Ask After Understanding Goal)

3. **Tech stack**: "What's your environment?"
   - Language (Python, TypeScript, Java, Go)
   - Orchestration platform (Temporal, Camunda, n8n, Airflow, custom)
   - Message broker (Kafka, RabbitMQ, Redis Streams, SQS)
   - Database (PostgreSQL, MongoDB, etc.)
4. **Scale & constraints**: "What are your operational requirements?"
   - Expected throughput (workflows/hour)
   - Latency requirements (real-time vs batch)
   - SLA requirements (approval deadlines, escalation timers)
   - Compliance (SOC2, HIPAA, audit trails)

### Tier 3 (Ask If Complex)

5. **Integration points**: "What external systems connect to this workflow?"
6. **Exception handling**: "What should happen when steps fail?"
7. **Human touchpoints**: "Where do humans need to intervene?"

---

## Architecture Decision Framework

### Platform Selection

| Requirement | Recommended Platform | Why |
|-------------|---------------------|-----|
| Long-running stateful workflows | **Temporal** | Durable execution, automatic retries, exactly-once |
| Visual BPMN-based processes | **Camunda** | Standards-based, business-friendly diagrams |
| Low-code integrations (1100+ connectors) | **n8n** | Rapid setup, webhook-driven, visual editor |
| Data/ETL pipelines | **Apache Airflow** | DAG-based, massive ecosystem, proven at scale |
| Python-native ML workflows | **Prefect** | Decorator-based, dynamic DAGs, rapid prototyping |
| Declarative YAML workflows | **Kestra** | IaC approach, Kafka-based, transparent file handling |
| Simple queue-based tasks | **Celery/BullMQ** | Lightweight, language-native, minimal overhead |

### Workflow Pattern Selection

```
Is the process triggered by events?
├── Yes → Event-Driven Pipeline (see references/event-driven-patterns.md)
│   ├── Real-time required? → Kafka/Redis Streams + durable execution
│   └── Batch OK? → Scheduled polling + queue processing
└── No → Request-Driven Workflow
    ├── Needs human approval? → Approval Flow (see references/approval-flow-patterns.md)
    ├── Long-running (hours/days)? → Durable Execution (Temporal/Camunda)
    └── Short-lived (<5 min)? → Task Queue (Celery/BullMQ)
```

---

## Implementation Standards

### Workflow Definition Pattern

```
Define workflow as:
1. States: Clear enumeration of all possible states
2. Transitions: Valid state changes with conditions
3. Actions: Side effects executed on transitions
4. Guards: Preconditions that must be true for transitions
5. Timeouts: Maximum time allowed in each state
6. Compensation: Rollback actions for failure scenarios
```

### Exception Handling Hierarchy

```
Level 1: Automatic retry (transient failures)
  → Exponential backoff with jitter, max 3-5 retries
Level 2: Alternative path (degraded service)
  → Fallback logic, cached responses, default values
Level 3: Dead-letter queue (unprocessable)
  → Park failed items, alert operators, enable manual replay
Level 4: Compensation (partial completion)
  → Saga pattern: reverse completed steps in order
Level 5: Human escalation (business exceptions)
  → Route to operator with full context and suggested actions
```

### Idempotency Requirements

Every workflow step MUST be idempotent:
- Use idempotency keys for external API calls
- Check-before-act pattern for state mutations
- Deduplication at message consumer level
- Store processing status before executing side effects

### Observability Standards

Every workflow MUST include:
- Correlation ID propagated across all steps
- State transition logging with timestamps
- Duration metrics per step and total workflow
- Error categorization (transient vs permanent vs business)
- SLA breach alerting with remaining time tracking

---

## Output Checklist

Before delivering any workflow automation implementation, verify:

- [ ] Idempotency implemented for all workflow steps (keys, deduplication)
- [ ] Exception handling addressed at all applicable levels (retry, fallback, DLQ, compensation, escalation)
- [ ] Observability included (correlation ID, state logging, duration metrics, error categorization)
- [ ] SLA tracking configured where human touchpoints exist
- [ ] Compensation/saga logic for multi-step workflows that modify external state
- [ ] Timeout configuration for every async/external operation
- [ ] Dead-letter queue or equivalent for unprocessable messages
- [ ] Audit trail for compliance-sensitive operations (approvals, financial)
- [ ] Notification channels configured for failures and escalations
- [ ] Integration points tested with circuit breaker or retry protection

---

## Anti-Patterns to Avoid

| Anti-Pattern | Why It Fails | Do Instead |
|-------------|-------------|------------|
| Synchronous chains | Single failure cascades | Event-driven with retries |
| Polling without backoff | Resource waste, thundering herd | Exponential backoff + jitter |
| No idempotency | Duplicate processing on retry | Idempotency keys everywhere |
| Hardcoded timeouts | Can't tune per environment | Configurable with sensible defaults |
| Silent failures | Problems discovered too late | Dead-letter queues + alerts |
| Monolithic workflows | Can't scale individual steps | Decompose into activities/tasks |
| No compensation logic | Partial completion leaves bad state | Saga pattern for multi-step |
| Approval without escalation | Workflows stall indefinitely | Auto-escalate on timeout |

---

## Official Documentation

| Platform | Documentation |
|----------|--------------|
| Temporal | https://docs.temporal.io |
| Camunda | https://docs.camunda.io |
| n8n | https://docs.n8n.io |
| Apache Airflow | https://airflow.apache.org/docs |
| Prefect | https://docs.prefect.io |
| Kestra | https://kestra.io/docs |
| Apache Kafka | https://kafka.apache.org/documentation |
| RabbitMQ | https://www.rabbitmq.com/docs |

Always check official docs for the latest API changes and version-specific patterns.

---

## Reference Files

| File | When to Read |
|------|--------------|
| `references/workflow-orchestration.md` | Platform-specific patterns (Temporal, Camunda, n8n, Airflow) |
| `references/event-driven-patterns.md` | Event-driven pipeline design, message broker patterns |
| `references/approval-flow-patterns.md` | Approval workflows, escalation, delegation, SLA tracking |
| `references/crm-task-routing.md` | CRM automation, lead routing, task assignment patterns |
| `references/exception-handling.md` | Retry strategies, compensation, dead-letter, saga patterns |
| `references/notification-systems.md` | Multi-channel notifications, digest, throttling, templates |
