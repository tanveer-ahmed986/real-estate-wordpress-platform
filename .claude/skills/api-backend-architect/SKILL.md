---
name: api-backend-architect
description: |
  Professional API and backend architecture for production-grade web and desktop software.
  This skill should be used when users ask to design APIs, architect backend systems,
  build REST/GraphQL/gRPC/WebSocket services, plan microservices, implement authentication,
  optimize performance, set up caching, configure message queues, design database layers,
  harden security, or create scalable backend infrastructure.
  Trigger: /api-backend-architect, /api-architect, /backend-architect, /api-design,
  /backend-design, /api, /backend
---

# API & Backend Architect

Design, build, and optimize production-grade APIs and backend systems for web and desktop software.

## What This Skill Does

- Designs RESTful, GraphQL, gRPC, and WebSocket APIs with industry standards
- Architects backend systems (monolith, microservices, serverless, event-driven)
- Implements authentication/authorization (JWT, OAuth 2.0, OIDC, API keys, RBAC/ABAC)
- Applies OWASP API Security Top 10 hardening
- Designs caching strategies, rate limiting, and throttling
- Plans database integration, message queues, and event streaming
- Configures monitoring, logging, error handling, and observability
- Optimizes for performance, scalability, and reliability
- Follows 12-Factor App, AWS Well-Architected, and Azure Architecture best practices

## What This Skill Does NOT Do

- Frontend UI/UX design or component creation
- Mobile app development
- DevOps/CI-CD pipeline configuration (recommends patterns only)
- Cloud infrastructure provisioning (IaC is out of scope)
- Database administration or DBA tasks
- Network infrastructure or DNS configuration

---

## Before Implementation

Gather context to ensure successful implementation:

| Source | Gather |
|--------|--------|
| **Codebase** | Existing API structure, frameworks, ORM, middleware, conventions |
| **Conversation** | User's specific requirements, scale expectations, constraints |
| **Skill References** | Domain patterns from `references/` (API design, security, architecture) |
| **User Guidelines** | Project-specific conventions, team standards, compliance requirements |

Ensure all required context is gathered before implementing.
Only ask user for THEIR specific requirements (domain expertise is in this skill).

---

## Required Clarifications

Ask about the USER'S context (not domain knowledge):

### 1. Project Type
"What are you building?"
- Web application backend
- Desktop application backend
- API-first platform / BaaS
- Microservice within larger system
- Real-time application

### 2. API Style
"What API protocol(s) do you need?"
- REST (most common, broad client support)
- GraphQL (flexible queries, complex data graphs)
- gRPC (high-performance, service-to-service)
- WebSocket (real-time bidirectional)
- Hybrid (multiple protocols)

### 3. Tech Stack
"What's your technology stack?"
- Language/runtime (Node.js, Python, Go, Rust, Java, C#, etc.)
- Framework (Express, Fastify, FastAPI, Django, Gin, Actix, Spring, ASP.NET, etc.)
- Database (PostgreSQL, MySQL, MongoDB, Redis, etc.)
- Existing infrastructure (cloud provider, containerized, serverless)

### 4. Scale & Performance
"What are your scale expectations?"
- Users/requests: Expected concurrent users and RPS
- Data volume: Size of datasets
- Latency requirements: Response time SLAs
- Availability target: 99.9%, 99.99%, etc.

### 5. Security Requirements
"Any specific security or compliance needs?"
- Authentication method preference
- Compliance standards (SOC2, HIPAA, GDPR, PCI-DSS)
- Multi-tenancy requirements
- Data residency constraints

---

## Architecture Decision Workflow

```
1. Clarify Requirements
   ↓
2. Select Architecture Pattern
   ↓
3. Design API Layer
   ↓
4. Plan Data Layer
   ↓
5. Implement Security
   ↓
6. Add Observability
   ↓
7. Optimize & Harden
   ↓
8. Validate Against Checklist
```

---

## Architecture Pattern Selection

| Signal | Pattern | Reference |
|--------|---------|-----------|
| Small team, single domain, MVP/startup | **Modular Monolith** | `references/architecture-patterns.md` |
| Multiple teams, independent deployment | **Microservices** | `references/architecture-patterns.md` |
| Variable/spiky load, pay-per-use | **Serverless** | `references/architecture-patterns.md` |
| High-throughput async processing | **Event-Driven** | `references/architecture-patterns.md` |
| Complex domain with audit trail | **Event Sourcing + CQRS** | `references/architecture-patterns.md` |
| Real-time data, streaming | **Streaming Architecture** | `references/architecture-patterns.md` |

**Default recommendation**: Start with Modular Monolith unless clear signals for alternatives.

---

## API Design Decision Tree

```
Client type?
├── Browser/Mobile → REST or GraphQL
│   ├── Simple CRUD → REST
│   ├── Complex nested data → GraphQL
│   └── Real-time updates needed?
│       ├── Yes → Add WebSocket / SSE
│       └── No → REST / GraphQL only
├── Service-to-service → gRPC or REST
│   ├── High performance, typed → gRPC
│   └── Simple integration → REST
└── Mixed clients → Hybrid (REST + gRPC / GraphQL + REST)
```

---

## Security Enforcement (OWASP API Security Top 10)

### Must Follow
- [ ] Broken Object Level Authorization (BOLA) - validate ownership on every object access
- [ ] Broken Authentication - use proven auth libraries, enforce MFA for sensitive ops
- [ ] Broken Object Property Level Authorization - filter response properties by role
- [ ] Unrestricted Resource Consumption - rate limit, paginate, cap query complexity
- [ ] Broken Function Level Authorization - enforce RBAC/ABAC on every endpoint
- [ ] Server-Side Request Forgery (SSRF) - validate and allowlist outbound URLs
- [ ] Security Misconfiguration - disable debug, harden headers, minimize exposure
- [ ] Lack of Inventory Management - document and version all APIs
- [ ] Unsafe API Consumption - validate third-party API responses

### Must Avoid
- Hardcoded secrets or credentials in code
- Verbose error messages exposing internals to clients
- SQL/NoSQL injection via string concatenation
- Missing input validation on any endpoint
- Disabled CORS or wildcard `*` origins in production
- JWT with `none` algorithm or weak signing keys
- Sensitive data in URL query parameters or logs

See `references/security-hardening.md` for complete OWASP coverage.

---

## Performance Optimization Checklist

| Layer | Technique | When to Apply |
|-------|-----------|---------------|
| **API** | Response compression (gzip/brotli) | Always |
| **API** | Pagination (cursor-based preferred) | List endpoints |
| **API** | Field selection / sparse fieldsets | Large responses |
| **Cache** | HTTP caching (ETags, Cache-Control) | Read-heavy endpoints |
| **Cache** | Application cache (Redis/Memcached) | Expensive computations |
| **Cache** | CDN for static/semi-static content | Public content |
| **Database** | Connection pooling | Always |
| **Database** | Query optimization + indexes | Slow queries (>100ms) |
| **Database** | Read replicas | Read-heavy workloads |
| **Async** | Message queues for heavy operations | Long-running tasks |
| **Async** | Background job processing | Non-blocking operations |
| **Infra** | Horizontal scaling + load balancing | High traffic |
| **Infra** | Auto-scaling policies | Variable load |

See `references/performance-scalability.md` for detailed patterns.

---

## 12-Factor Compliance

| Factor | Requirement |
|--------|-------------|
| Codebase | One codebase per service, tracked in VCS |
| Dependencies | Explicitly declare and isolate |
| Config | Store in environment variables, never in code |
| Backing Services | Treat as attached resources (DB, cache, queue) |
| Build/Release/Run | Strictly separate stages |
| Processes | Execute as stateless processes |
| Port Binding | Export services via port binding |
| Concurrency | Scale out via process model |
| Disposability | Fast startup, graceful shutdown |
| Dev/Prod Parity | Keep environments as similar as possible |
| Logs | Treat as event streams |
| Admin Processes | Run as one-off processes |

---

## Output Checklist

Before delivering any API/backend implementation, verify:

### Architecture
- [ ] Architecture pattern matches requirements and scale
- [ ] Clear service boundaries and responsibilities
- [ ] Stateless design (session externalized if needed)
- [ ] Graceful shutdown handling

### API Design
- [ ] Consistent resource naming (plural nouns, kebab-case)
- [ ] Proper HTTP methods and status codes
- [ ] Pagination on all list endpoints
- [ ] API versioning strategy defined
- [ ] Request/response validation with schemas
- [ ] Idempotency keys for mutating operations
- [ ] Comprehensive error response format

### Security
- [ ] OWASP API Security Top 10 addressed
- [ ] Authentication and authorization on all protected endpoints
- [ ] Input validation and sanitization
- [ ] Rate limiting and throttling configured
- [ ] CORS properly configured (no wildcards in production)
- [ ] Secrets managed via environment variables
- [ ] Security headers set (HSTS, CSP, X-Content-Type-Options)

### Data Layer
- [ ] Database schema with proper indexes
- [ ] Connection pooling configured
- [ ] Migration strategy defined
- [ ] Backup and recovery plan

### Observability
- [ ] Structured logging with correlation IDs
- [ ] Health check endpoints (/health, /ready)
- [ ] Metrics collection (latency, error rate, throughput)
- [ ] Distributed tracing for multi-service setups
- [ ] Alerting thresholds defined

### Reliability
- [ ] Retry with exponential backoff for transient failures
- [ ] Circuit breaker for downstream dependencies
- [ ] Timeout configuration on all external calls
- [ ] Graceful degradation strategy
- [ ] Error handling with proper classification

---

## Reference Files

| File | When to Read |
|------|--------------|
| `references/api-design-patterns.md` | Designing REST, GraphQL, gRPC, or WebSocket APIs |
| `references/architecture-patterns.md` | Choosing and implementing architecture patterns |
| `references/security-hardening.md` | OWASP compliance, auth, input validation, encryption |
| `references/authentication-authorization.md` | JWT, OAuth 2.0, OIDC, RBAC, ABAC, API keys |
| `references/performance-scalability.md` | Caching, optimization, scaling, load balancing |
| `references/data-layer-patterns.md` | Database design, ORMs, migrations, message queues |
| `references/observability-reliability.md` | Logging, monitoring, tracing, circuit breakers, error handling |
| `references/cloud-best-practices.md` | AWS Well-Architected, Azure Architecture, 12-Factor |
