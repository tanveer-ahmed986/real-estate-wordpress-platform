---
name: software-engineer
description: |
  Professional software engineering assistant for building production-grade applications and dashboards.
  This skill should be used when users ask to build apps, create dashboards, architect systems,
  review code, optimize performance, design APIs, set up CI/CD, or follow best practices.
  Trigger: /software-engineer, /swe, /build-app, /build-dashboard, /architect, /dev-guide,
  /code-review, /optimize, /best-practices
allowed-tools: Read, Write, Edit, Glob, Grep, Bash, WebFetch, WebSearch
---

# Software Engineer

Build production-grade software with industry best practices, official documentation standards, and battle-tested architecture patterns.

## What This Skill Does

- Architect and build full-stack applications (frontend, backend, database, API)
- Design and implement professional dashboards with data visualization
- Enforce code quality (SOLID, DRY, KISS, clean code principles)
- Apply security hardening (OWASP Top 10:2025 compliance)
- Optimize performance (Core Web Vitals, database queries, caching)
- Design scalable architectures (microservices, event-driven, serverless)
- Set up CI/CD pipelines and DevOps workflows
- Conduct thorough code reviews with actionable feedback

## What This Skill Does NOT Do

- Deploy to production environments (provides deployment guides only)
- Manage cloud infrastructure directly
- Handle billing or licensing decisions
- Replace domain-specific security audits

---

## Before Implementation

Gather context to ensure successful implementation:

| Source | Gather |
|--------|--------|
| **Codebase** | Existing structure, patterns, conventions, tech stack, dependencies |
| **Conversation** | User's specific requirements, constraints, timeline, scale expectations |
| **Skill References** | Domain patterns from `references/` (architecture, security, performance) |
| **User Guidelines** | Project-specific conventions, team standards, CLAUDE.md rules |

Ensure all required context is gathered before implementing.
Only ask user for THEIR specific requirements (domain expertise is in this skill).

---

## Required Clarifications

Before building, gather user-specific context.

**Question pacing**: Start with Project Type and Tech Stack. Ask Scale and Existing Codebase only if relevant to the task or if the answer isn't inferrable from the codebase/conversation context. Avoid asking all questions at once.

### 1. Project Type
"What are you building?"
- **Web Application** → Full-stack app (SPA, SSR, or hybrid)
- **Dashboard** → Data visualization, analytics, real-time monitoring
- **API/Backend** → REST, GraphQL, gRPC service
- **CLI Tool** → Command-line application
- **Library/Package** → Reusable module

### 2. Tech Stack
"What's your preferred tech stack?" (or let me recommend based on requirements)
- Frontend framework (React, Vue, Angular, Svelte, Next.js, Nuxt)
- Backend runtime — choose based on requirements:

  | Need | Recommended | Why |
  |------|-------------|-----|
  | Rapid API development, Python ecosystem | **FastAPI** | Auto docs, type-safe, async, see `references/fastapi-patterns.md` |
  | JavaScript full-stack, large ecosystem | **Express / Fastify / Hono** | Mature, vast middleware ecosystem |
  | Type-safe full-stack, SSR + API | **Next.js API Routes / tRPC** | Shared types, co-located frontend |
  | Maximum performance, compiled | **Go (Chi/Gin) / Rust (Axum)** | Low latency, small binaries |
  | Enterprise, large teams | **Java (Spring) / .NET** | Battle-tested, enterprise tooling |

- Database (PostgreSQL, MySQL, MongoDB, Redis, SQLite)
- Deployment target (Docker, Kubernetes, serverless, VPS)

### 3. Scale & Constraints (ask only if relevant)
"What scale are you targeting?"
- Users: expected concurrent users
- Data: expected data volume
- Performance: latency requirements
- Budget: infrastructure constraints

**Default if not specified**: Assume production-scale with standard patterns — PostgreSQL, Redis caching, horizontal scaling ready, structured logging.

### 4. Existing Codebase (infer from context when possible)
"Is this greenfield or adding to existing code?"
- Greenfield → Recommend optimal structure
- Existing → Analyze and integrate with current patterns

**Default if not specified**: Scan for existing `package.json`, `requirements.txt`, `go.mod`, `CLAUDE.md`, `.eslintrc`, or `tsconfig.json` to infer stack and conventions before asking.

---

## Error Recovery

If an implementation approach fails, is rejected by the user, or hits a blocker:

1. **Reassess architecture** — Re-evaluate pattern selection; the chosen pattern may not fit the actual constraints
2. **Check assumptions** — Verify tech stack compatibility, dependency versions, and environment requirements
3. **Simplify** — Fall back to a simpler pattern (e.g., monolith instead of microservices, REST instead of gRPC)
4. **Ask the user** — Surface the blocker clearly, explain what failed and why, and offer 2-3 alternative approaches
5. **Never force** — Do not retry the same failing approach; pivot to an alternative

---

## Architecture Decision Workflow

```
Requirements → Scale Analysis → Pattern Selection → Tech Stack → Project Structure → Implementation
```

### Pattern Selection Matrix

| Requirement | Pattern | When to Use |
|-------------|---------|-------------|
| Simple CRUD app | Monolith (MVC/Clean) | <10K users, small team, rapid MVP |
| Complex business logic | Hexagonal/Clean Architecture | Domain-heavy, testability critical |
| High scalability | Microservices | Independent scaling, large teams |
| Real-time features | Event-Driven + WebSockets | Chat, live dashboards, notifications |
| Data-intensive | CQRS + Event Sourcing | Audit trails, complex read/write patterns |
| Serverless workloads | Function-as-a-Service | Sporadic traffic, cost optimization |
| Data pipeline | ETL/ELT + orchestration | Batch/stream processing (Airflow, Dagster, Kafka Streams) |
| ML service | Model serving + API | Inference endpoint (FastAPI + ONNX/TensorRT, BentoML) |
| Embedded/IoT | Constrained runtime | Resource-limited devices (Rust, C, MicroPython) |

See `references/architecture-patterns.md` for detailed patterns and decision trees.

---

## Implementation Standards

### Code Quality (Every File)

| Standard | Enforcement |
|----------|-------------|
| Type Safety | TypeScript strict mode, Python type hints, Go static types |
| Error Handling | Typed errors, no silent catches, structured error responses |
| Naming | Descriptive, consistent (camelCase JS/TS, snake_case Python/Go) |
| Functions | Single responsibility, <30 lines, max 3-4 parameters |
| Dependencies | Minimal, audited, pinned versions |

See `references/code-quality.md` for SOLID principles and clean code patterns.

### Security (Every Project)

Apply OWASP Top 10:2025 mitigations:

| Risk | Mitigation |
|------|------------|
| A01: Broken Access Control | RBAC/ABAC, principle of least privilege, server-side enforcement |
| A02: Security Misconfiguration | Hardened defaults, remove unused features, security headers |
| A03: Supply Chain Failures | Lock files, dependency scanning, SRI for CDN assets |
| A04: Cryptographic Failures | TLS 1.3, bcrypt/argon2 for passwords, no custom crypto |
| A05: Injection | Parameterized queries, input validation, output encoding |
| A06: Insecure Design | Threat modeling, secure design patterns, abuse case testing |
| A07: Authentication Failures | MFA, rate limiting, secure session management |
| A08: Data Integrity Failures | Signed updates, CI/CD integrity, serialization validation |
| A09: Logging Failures | Structured logging, alerting, audit trails (no sensitive data) |
| A10: Exception Mishandling | Graceful degradation, generic error messages to users |

See `references/security-practices.md` for implementation details.

### API Design

| Principle | Implementation |
|-----------|----------------|
| Versioning | URL path (`/v1/`) or header-based |
| Pagination | Cursor-based for large datasets, offset for simple cases |
| Error Format | RFC 7807 Problem Details (`type`, `title`, `status`, `detail`) |
| Rate Limiting | Token bucket, return `429` with `Retry-After` header |
| Documentation | OpenAPI 3.1 spec, auto-generated from code annotations |

See `references/api-design.md` for REST, GraphQL, and gRPC patterns.

### Database Design

| Decision | Guidance |
|----------|----------|
| SQL vs NoSQL | SQL: relationships, ACID, complex queries. NoSQL: flexible schema, horizontal scale |
| Indexing | Index WHERE/JOIN/ORDER columns, composite indexes for multi-column queries |
| Migrations | Versioned, reversible, zero-downtime compatible |
| Connection Pooling | Always pool (pgBouncer, HikariCP), size = (cores * 2) + disk spindles |

See `references/database-design.md` for normalization, query optimization, and schema patterns.

---

## Dashboard Development

When building dashboards, follow these principles:

| Principle | Implementation |
|-----------|----------------|
| Data-Ink Ratio | Maximize data, minimize chart junk and decoration |
| Visual Hierarchy | KPIs top, trends middle, details bottom |
| Responsiveness | CSS Grid/Flexbox, breakpoints at 768px/1024px/1440px |
| Real-Time Updates | WebSocket or SSE for live data, polling fallback with 30s+ intervals |
| Chart Selection | Comparison→Bar, Trend→Line, Proportion→Pie/Donut, Correlation→Scatter |
| Accessibility | WCAG 2.1 AA, color-blind safe palettes, keyboard navigable |

See `references/dashboard-development.md` for visualization libraries, layout patterns, and real-time architectures.

---

## Testing Strategy

Follow the testing pyramid:

```
        /  E2E  \        ← Few: Critical user journeys only
       / Integration \    ← Some: API contracts, DB queries, service boundaries
      /    Unit Tests   \ ← Many: Business logic, utilities, pure functions
```

| Layer | Coverage Target | Tools |
|-------|----------------|-------|
| Unit | >80% business logic | Jest, Vitest, pytest, Go testing |
| Integration | API contracts, DB | Supertest, TestContainers |
| E2E | Critical paths | Playwright, Cypress |

See `references/testing-strategies.md` for TDD/BDD workflows, mocking patterns, and CI integration.

---

## Performance Optimization

### Frontend (Core Web Vitals Targets)

| Metric | Target | How |
|--------|--------|-----|
| LCP (Largest Contentful Paint) | <2.5s | Optimize images, preload critical assets, SSR/SSG |
| INP (Interaction to Next Paint) | <200ms | Minimize main thread work, use web workers |
| CLS (Cumulative Layout Shift) | <0.1 | Set dimensions on images/embeds, avoid dynamic injection |

### Backend

| Technique | When |
|-----------|------|
| Response Caching | GET endpoints with stable data (Redis, CDN) |
| Query Optimization | N+1 queries, missing indexes, explain analyze |
| Connection Pooling | Always for database connections |
| Async Processing | Heavy tasks → message queue (jobs, emails, reports) |

See `references/performance-optimization.md` for detailed optimization techniques.

---

## DevOps & CI/CD

### Pipeline Stages

```
Commit → Lint → Test → Build → Security Scan → Deploy (Staging) → Smoke Test → Deploy (Prod)
```

### Deployment Strategies

| Strategy | Risk | Downtime | Use When |
|----------|------|----------|----------|
| Blue-Green | Low | Zero | Critical apps, instant rollback needed |
| Canary | Low | Zero | Gradual rollout, monitoring-heavy |
| Rolling | Medium | Zero | Stateless services, Kubernetes |
| Recreate | High | Yes | Dev/staging only, database migrations |

See `references/devops-cicd.md` for pipeline templates and IaC patterns.

---

## Project Structure

### Feature-Based (Recommended for Most Projects)

```
src/
├── features/
│   ├── auth/
│   │   ├── components/    # UI components
│   │   ├── hooks/         # Custom hooks
│   │   ├── services/      # API calls
│   │   ├── utils/         # Feature utilities
│   │   └── __tests__/     # Feature tests
│   └── dashboard/
│       ├── components/
│       ├── hooks/
│       ├── services/
│       └── __tests__/
├── shared/                # Cross-feature shared code
│   ├── components/        # Reusable UI components
│   ├── hooks/             # Shared hooks
│   ├── utils/             # Shared utilities
│   └── types/             # Shared type definitions
├── infrastructure/        # Framework & config
│   ├── api/               # API client setup
│   ├── auth/              # Auth provider
│   ├── database/          # DB connection & migrations
│   └── config/            # Environment config
└── app.ts                 # Entry point
```

See `references/project-structure.md` for backend, monorepo, and microservice layouts.

---

## Output Checklist

Before delivering any implementation, verify:

### Functional
- [ ] Core requirements implemented and working
- [ ] Error states handled gracefully with user-friendly messages
- [ ] Loading states present for async operations
- [ ] Edge cases covered (empty states, large datasets, network failures)

### Code Quality
- [ ] Type-safe (no `any` in TypeScript, type hints in Python)
- [ ] Functions are small, focused, well-named
- [ ] No code duplication; shared logic extracted appropriately
- [ ] Consistent naming conventions throughout

### Security
- [ ] Input validated and sanitized at system boundaries
- [ ] Authentication and authorization enforced server-side
- [ ] No secrets in code or client bundles
- [ ] SQL injection, XSS, CSRF protections applied
- [ ] Dependencies audited (`npm audit`, `pip audit`)

### Performance
- [ ] No N+1 queries; database queries optimized
- [ ] Assets optimized (images, bundles, lazy loading)
- [ ] Caching strategy applied where appropriate
- [ ] Core Web Vitals targets met for frontend

### Testing
- [ ] Unit tests for business logic
- [ ] Integration tests for API endpoints
- [ ] Error scenarios tested
- [ ] CI pipeline runs all tests

### Accessibility (UI)
- [ ] Semantic HTML elements used
- [ ] ARIA labels on interactive elements
- [ ] Color contrast ratio ≥4.5:1 (text), ≥3:1 (UI)
- [ ] Keyboard navigable; focus indicators visible
- [ ] Screen reader tested

---

## Reference Files

| File | When to Read |
|------|--------------|
| `references/architecture-patterns.md` | System design, pattern selection, scalability |
| `references/api-design.md` | REST, GraphQL, gRPC design guidelines |
| `references/security-practices.md` | OWASP Top 10:2025, auth, encryption |
| `references/testing-strategies.md` | Testing pyramid, TDD/BDD, CI testing |
| `references/performance-optimization.md` | Core Web Vitals, caching, query optimization |
| `references/dashboard-development.md` | Charts, layouts, real-time data, visualization |
| `references/database-design.md` | Schema design, indexing, migrations, SQL vs NoSQL |
| `references/devops-cicd.md` | Pipelines, deployment strategies, IaC, Docker |
| `references/project-structure.md` | File organization, monorepo, feature-based layout |
| `references/code-quality.md` | SOLID, clean code, naming, refactoring patterns |
| `references/fastapi-patterns.md` | FastAPI: search `## Configuration` (settings), `## Database` (SQLModel/async), `## Model Patterns` (multi-model), `## Dependency Injection` (Depends), `## Authentication` (JWT/OAuth2), `## Middleware`, `## Testing`, `## Anti-Patterns` |

**Note**: Always verify framework/library versions before implementing. Check official docs for the latest APIs, as patterns may evolve between major versions.

For patterns not covered here, search official documentation:
- **Web**: MDN Web Docs, web.dev
- **React/Vue/Angular**: Official framework docs
- **Node.js/Python/Go/Rust**: Official language docs
- **Cloud**: AWS Well-Architected, Azure Architecture Center, GCP Best Practices
- **Security**: OWASP.org, NIST guidelines
