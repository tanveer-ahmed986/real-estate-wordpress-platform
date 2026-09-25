# Architecture Patterns

## Pattern Decision Tree

```
Start
├── Simple CRUD, small team, MVP?
│   └── YES → Monolith (MVC or Clean Architecture)
├── Complex domain logic, many business rules?
│   └── YES → Hexagonal / Clean Architecture
├── Multiple teams, independent deployment needed?
│   └── YES → Microservices
├── Real-time event processing, streaming?
│   └── YES → Event-Driven Architecture
├── Sporadic traffic, cost-sensitive?
│   └── YES → Serverless (FaaS)
└── High read/write asymmetry, audit requirements?
    └── YES → CQRS + Event Sourcing
```

---

## Monolith (MVC / Clean Architecture)

### When to Use
- Small-to-medium applications (<10K concurrent users)
- Small teams (1-5 developers)
- Rapid prototyping and MVPs
- Simple deployment requirements

### Structure (Clean Architecture Layers)

```
┌──────────────────────────────────────┐
│           Presentation               │  ← Controllers, Views, API handlers
├──────────────────────────────────────┤
│           Application                │  ← Use cases, orchestration, DTOs
├──────────────────────────────────────┤
│             Domain                   │  ← Entities, value objects, domain services
├──────────────────────────────────────┤
│          Infrastructure              │  ← DB, external APIs, file system
└──────────────────────────────────────┘
```

**Dependency Rule**: Dependencies point INWARD only. Domain has zero external dependencies.

### Implementation Pattern

```
src/
├── presentation/
│   ├── controllers/      # HTTP handlers
│   ├── middleware/        # Auth, logging, error handling
│   └── validators/       # Request validation schemas
├── application/
│   ├── use-cases/        # Business operations
│   ├── dtos/             # Data transfer objects
│   └── interfaces/       # Port definitions (abstractions)
├── domain/
│   ├── entities/         # Core business objects
│   ├── value-objects/    # Immutable domain values
│   ├── events/           # Domain events
│   └── services/         # Domain logic spanning entities
└── infrastructure/
    ├── repositories/     # Database implementations
    ├── external/         # Third-party API clients
    └── config/           # Environment configuration
```

---

## Microservices

### When to Use
- Large teams (multiple squads)
- Independent deployment and scaling requirements
- Polyglot technology needs
- High availability requirements

### Design Principles
1. **Single Responsibility**: Each service owns one business capability
2. **Database per Service**: No shared databases between services
3. **API Gateway**: Single entry point for clients
4. **Service Discovery**: Dynamic registration and lookup
5. **Circuit Breaker**: Prevent cascade failures (Hystrix pattern)
6. **Saga Pattern**: Distributed transactions via orchestration or choreography

### Communication Patterns

| Pattern | Use When | Protocol |
|---------|----------|----------|
| Synchronous | Real-time response needed | REST, gRPC |
| Asynchronous | Fire-and-forget, eventual consistency | Message Queue (RabbitMQ, Kafka) |
| Event-Driven | Loose coupling, multiple consumers | Event Bus (Kafka, EventBridge) |

### Service Decomposition

Decompose by:
- **Business Capability**: Auth service, Payment service, Notification service
- **Subdomain** (DDD): Bounded contexts become services
- **Data Ownership**: Each service owns its data store

### Anti-Patterns to Avoid
- **Distributed Monolith**: Tightly coupled services that must deploy together
- **Shared Database**: Multiple services accessing same DB tables
- **Chatty Services**: Too many inter-service calls for single operation
- **Nano-services**: Services too small to justify operational overhead

---

## Event-Driven Architecture

### When to Use
- Real-time data processing
- Decoupled systems that react to state changes
- Audit trail and event replay requirements
- Complex workflows with multiple downstream consumers

### Patterns

#### Event Notification
Producer emits event, consumers react independently.
```
Order Service → [OrderPlaced event] → Inventory Service
                                    → Notification Service
                                    → Analytics Service
```

#### Event Sourcing
Store state as sequence of events, not current state.
```
Events: [AccountCreated] → [MoneyDeposited($100)] → [MoneyWithdrawn($30)]
Current State: Balance = $70
```

#### CQRS (Command Query Responsibility Segregation)
Separate read and write models for different optimization.
```
Commands (Write) → Write DB (normalized, ACID)
Queries (Read)   → Read DB (denormalized, optimized views)
                   ↑ Sync via events
```

---

## Hexagonal Architecture (Ports & Adapters)

### Core Concept
Application core defines PORTS (interfaces). External systems connect via ADAPTERS.

```
            ┌─────────────────────────┐
  HTTP ───→ │ Port    ┌──────────┐    │ ←── Port ← Database
  CLI  ───→ │ (in)    │  Domain  │    │ ←── Port ← Message Queue
  gRPC ───→ │         │   Core   │    │ ←── Port ← External API
            │         └──────────┘    │
            └─────────────────────────┘
         Driving Adapters          Driven Adapters
         (input)                   (output)
```

### Benefits
- Domain logic is completely isolated from infrastructure
- Easy to swap databases, frameworks, or external services
- Highly testable (mock adapters for testing)

---

## Serverless Architecture

### When to Use
- Sporadic or unpredictable traffic patterns
- Cost optimization (pay-per-execution)
- Event-driven workloads (S3 triggers, API calls, schedules)
- Rapid development without infrastructure management

### Considerations
- **Cold Starts**: 100ms-10s latency on first invocation
- **Execution Limits**: Max 15 min (AWS Lambda), 9 min (Azure Functions)
- **State Management**: Functions are stateless; use external stores
- **Vendor Lock-in**: Abstract cloud-specific APIs behind interfaces

### Pattern: Backend for Frontend (BFF)

```
Mobile App → Mobile BFF (Lambda) → Shared Services
Web App   → Web BFF (Lambda)    → Shared Services
```

---

## Scalability Patterns

### Horizontal Scaling
- **Load Balancer**: Distribute traffic across instances (round-robin, least connections)
- **Stateless Services**: No server-side session state (use JWT, Redis)
- **Database Sharding**: Partition data by tenant, region, or hash

### Vertical Scaling
- **Resource Optimization**: Profile and optimize before scaling up
- **Caching Layers**: L1 (in-memory) → L2 (Redis) → L3 (CDN)
- **Read Replicas**: Scale reads independently from writes

### Caching Strategy

| Layer | What to Cache | TTL |
|-------|--------------|-----|
| Browser | Static assets, API responses | Hours-days |
| CDN | Static files, rendered pages | Minutes-hours |
| Application | Computed results, session data | Seconds-minutes |
| Database | Query results, materialized views | Seconds-minutes |

**Cache Invalidation Strategies**:
- TTL-based (simplest, eventual consistency)
- Event-based (publish invalidation events on write)
- Write-through (update cache on every write)

---

## Sources
- Martin Fowler: Patterns of Enterprise Application Architecture
- Sam Newman: Building Microservices
- Vaughn Vernon: Implementing Domain-Driven Design
- 12factor.net: The Twelve-Factor App Methodology
- Microsoft Azure Architecture Center
- AWS Well-Architected Framework
