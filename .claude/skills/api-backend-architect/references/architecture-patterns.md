# Architecture Patterns

Backend architecture patterns for production software systems.

---

## Pattern Selection Guide

```
Team size? Scale? Domain complexity?
│
├── Small team + Single domain + Getting started
│   → Modular Monolith
│
├── Multiple teams + Independent deployment needed
│   → Microservices
│
├── Variable load + Pay-per-use + Stateless operations
│   → Serverless
│
├── High-throughput async + Decoupled producers/consumers
│   → Event-Driven Architecture
│
├── Complex domain + Full audit trail + Read/write asymmetry
│   → Event Sourcing + CQRS
│
└── Continuous data flow + Real-time analytics
    → Streaming Architecture
```

---

## Modular Monolith

### When to Use
- Small-to-medium teams (2-15 developers)
- Single deployment unit acceptable
- Clear domain boundaries exist
- Starting a new project (evolve to microservices later if needed)

### Structure

```
src/
├── modules/
│   ├── users/
│   │   ├── controllers/
│   │   ├── services/
│   │   ├── repositories/
│   │   ├── models/
│   │   ├── events/
│   │   └── index.ts          # Public API of module
│   ├── orders/
│   │   ├── controllers/
│   │   ├── services/
│   │   ├── repositories/
│   │   ├── models/
│   │   ├── events/
│   │   └── index.ts
│   └── payments/
│       └── ...
├── shared/
│   ├── middleware/
│   ├── utils/
│   └── events/               # Event bus
├── infrastructure/
│   ├── database/
│   ├── cache/
│   └── messaging/
└── app.ts
```

### Key Rules
- Modules communicate through defined interfaces (not direct DB access)
- Each module owns its data (separate schemas or tables)
- Use an internal event bus for cross-module communication
- Clear dependency direction (no circular dependencies)

### Evolution Path
```
Monolith → Modular Monolith → Extract high-traffic modules → Microservices
```

---

## Microservices

### When to Use
- Multiple teams owning different services
- Independent deployment and scaling needed
- Different technology choices per service
- Organizational boundaries align with service boundaries

### Design Principles

| Principle | Description |
|-----------|-------------|
| Single Responsibility | Each service owns one bounded context |
| Autonomy | Services deploy, scale, and fail independently |
| Decentralized Data | Each service owns its database |
| Smart Endpoints | Logic in services, dumb pipes for communication |
| Design for Failure | Expect and handle downstream failures |
| Evolutionary | Start with fewer, larger services; split when needed |

### Communication Patterns

| Pattern | When to Use | Example |
|---------|-------------|---------|
| Synchronous (REST/gRPC) | Need immediate response | User lookup, auth check |
| Async messaging (queue) | Fire-and-forget, decoupling | Order placed → inventory, email |
| Event streaming | Multiple consumers, replay needed | Event sourcing, analytics |
| Saga pattern | Distributed transactions | Multi-service order processing |

### Service Decomposition

```
Decompose by:
1. Business capability (preferred)
   - User Management, Order Processing, Payments, Notifications
2. Subdomain (DDD)
   - Core domain, Supporting domain, Generic domain
3. Team ownership
   - Team A → Service A, Team B → Service B

Avoid decomposing by:
- Technical layer (API service, DB service)
- Size ("micro" doesn't mean tiny)
```

### Saga Pattern (Distributed Transactions)

```
Choreography (event-based):
  Order Created → Payment Service → Payment Processed → Inventory Service →
  Stock Reserved → Shipping Service → Shipment Created

  Compensation on failure:
  Payment Failed → Order Cancelled (compensating transaction)

Orchestration (central coordinator):
  Order Saga Orchestrator:
    1. Create Order (pending)
    2. Process Payment → success? → next : compensate
    3. Reserve Inventory → success? → next : compensate
    4. Create Shipment → success? → complete : compensate

  Compensate:
    3. Release Inventory
    2. Refund Payment
    1. Cancel Order
```

### Anti-Patterns

| Anti-Pattern | Problem | Fix |
|-------------|---------|-----|
| Distributed Monolith | Services tightly coupled, deploy together | Proper service boundaries, async communication |
| Shared Database | Services access same DB | Database per service |
| Synchronous Chain | A → B → C → D synchronous calls | Use async messaging, CQRS |
| Nano-services | Too many tiny services | Merge related services |
| No API Gateway | Direct client-to-service calls | Add API gateway for routing, auth, rate limiting |

---

## Serverless

### When to Use
- Variable/spiky workloads
- Event-driven processing (S3 uploads, queue messages, webhooks)
- Pay-per-execution cost model desired
- Stateless, short-running operations
- Rapid prototyping and MVPs

### Patterns

```
API Gateway → Lambda/Function → Database
           → Lambda/Function → External API
           → Lambda/Function → Message Queue → Lambda

Event Sources:
  - HTTP API Gateway
  - Message Queues (SQS, EventBridge)
  - File uploads (S3, Blob Storage)
  - Scheduled (cron-like)
  - Database streams (DynamoDB Streams, Change Streams)
```

### Considerations

| Factor | Guidance |
|--------|----------|
| Cold starts | Keep functions warm, minimize dependencies, use provisioned concurrency |
| Timeouts | Max execution time limits (15 min AWS Lambda); offload long tasks |
| State | Externalize to database/cache; functions are stateless |
| Vendor lock-in | Abstract provider-specific code behind interfaces |
| Debugging | Structured logging + distributed tracing essential |
| Cost | Economical at low-medium scale; can be expensive at high sustained load |

---

## Event-Driven Architecture

### Core Components

```
Producers → Event Broker → Consumers
             (Kafka, RabbitMQ, SNS/SQS, EventBridge)

Event Types:
1. Domain Events  - "OrderPlaced", "UserRegistered" (business facts)
2. Integration Events - Cross-service communication
3. Commands - Directed instructions to specific service
4. Notifications - Fire-and-forget informational
```

### Event Design

```json
{
  "event_id": "evt_abc123",
  "event_type": "order.placed",
  "event_version": "1.0",
  "source": "order-service",
  "timestamp": "2024-01-15T10:30:00Z",
  "correlation_id": "req_xyz789",
  "data": {
    "order_id": "order_123",
    "user_id": "user_456",
    "total": 99.99,
    "items": [...]
  },
  "metadata": {
    "trace_id": "trace_001",
    "environment": "production"
  }
}
```

### Event Design Rules
- Events are immutable facts (past tense: "OrderPlaced" not "PlaceOrder")
- Include all data consumers need (avoid event-driven lookback)
- Version events for schema evolution
- Include correlation_id for tracing across services
- Use consistent event envelope format

### Message Queue Selection

| Queue | Best For | Key Feature |
|-------|----------|-------------|
| RabbitMQ | Task queues, routing | Flexible routing, acknowledgments |
| Apache Kafka | Event streaming, high throughput | Log-based, replay, partitioning |
| AWS SQS | Simple queuing, serverless | Managed, auto-scaling, dead letter |
| Redis Streams | Lightweight streaming | In-memory speed, consumer groups |
| NATS | Cloud-native, low latency | Lightweight, JetStream persistence |

---

## Event Sourcing + CQRS

### When to Use
- Full audit trail required (financial, compliance)
- Complex domain with rich business rules
- Need to reconstruct historical state
- Read and write patterns differ significantly

### Event Sourcing

```
# Instead of storing current state, store events
Events for Order aggregate:
  1. OrderCreated { orderId, userId, items, timestamp }
  2. ItemAdded { orderId, item, timestamp }
  3. PaymentProcessed { orderId, paymentId, amount, timestamp }
  4. OrderShipped { orderId, trackingNumber, timestamp }

# Current state = replay all events
Order = fold(events) → { id, items, status: "shipped", ... }

# Event Store
┌──────────────────────────────────────────────┐
│ stream_id │ version │ event_type  │ data     │
├───────────┼─────────┼─────────────┼──────────┤
│ order_123 │ 1       │ OrderCreated│ {...}    │
│ order_123 │ 2       │ ItemAdded   │ {...}    │
│ order_123 │ 3       │ Paid        │ {...}    │
│ order_123 │ 4       │ Shipped     │ {...}    │
└──────────────────────────────────────────────┘
```

### CQRS (Command Query Responsibility Segregation)

```
                    ┌─── Command ──→ Write Model ──→ Event Store
Client Request ─────┤
                    └─── Query ───→ Read Model ───→ Read Database
                                       ↑
                                  Projections
                                  (Event → Read Model)
```

| Side | Database | Optimized For |
|------|----------|---------------|
| Write (Command) | Event Store / normalized DB | Consistency, validation |
| Read (Query) | Denormalized / materialized views | Query performance |

---

## Cross-Cutting Patterns

### API Gateway

```
Client → API Gateway → Service A
                     → Service B
                     → Service C

Gateway responsibilities:
- Request routing
- Authentication/Authorization
- Rate limiting
- Request/response transformation
- Load balancing
- Circuit breaking
- Logging and monitoring
- API versioning
- SSL termination
```

### Service Mesh

```
Service A ←→ Sidecar Proxy ←→ Sidecar Proxy ←→ Service B
                    ↕                    ↕
              Control Plane (Istio, Linkerd, Consul Connect)

Handles: mTLS, load balancing, retries, observability, traffic management
Use when: Large microservices deployment, complex networking requirements
```

### Backend for Frontend (BFF)

```
Web App → Web BFF → Services
Mobile App → Mobile BFF → Services
Desktop App → Desktop BFF → Services

Each BFF tailors the API to its client's needs:
- Aggregates data from multiple services
- Shapes responses for specific UI needs
- Handles client-specific auth flows
```

### Strangler Fig (Migration Pattern)

```
# Gradually migrate from legacy to new system
Phase 1: Route all traffic through facade → legacy
Phase 2: Migrate feature A → new system
Phase 3: Migrate feature B → new system
Phase N: Decommission legacy

Facade routes:
  /api/users → new system (migrated)
  /api/orders → legacy (not yet migrated)
  /api/products → new system (migrated)
```
