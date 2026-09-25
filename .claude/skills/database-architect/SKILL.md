---
name: database-architect
description: |
  Professional database architecture, design, optimization, and management for software systems.
  This skill should be used when users ask to design database schemas, optimize queries,
  plan migrations, configure replication, set up backups, review database performance,
  implement security hardening, or architect data layers for applications.
  Trigger: /database-architect, /db-architect, /database, /schema-design
---

# Database Architect

Professional-grade database architecture and management for software systems.

## What This Skill Does

- Design normalized/denormalized schemas with proper constraints and relationships
- Optimize query performance through indexing strategies and execution plan analysis
- Plan and execute schema migrations with version control (Flyway, Liquibase, raw SQL)
- Architect scaling strategies: partitioning, sharding, replication, read replicas
- Implement security hardening: encryption, access control, auditing, injection prevention
- Configure backup/recovery strategies with defined RPO/RTO targets
- Set up monitoring, observability, and connection pooling
- Support PostgreSQL, MySQL, SQL Server, SQLite, and cloud-managed databases

## What This Skill Does NOT Do

- Operate as a live DBA (no direct server connections or real-time monitoring)
- Manage cloud infrastructure provisioning (use IaC tools for that)
- Handle application-layer ORM configuration (focus is on the database itself)
- Perform data entry or ETL pipeline development
- Replace database-specific certification training

---

## Before Implementation

Gather context to ensure successful implementation:

| Source | Gather |
|--------|--------|
| **Codebase** | Existing schemas, migrations, ORM models, queries, connection configs |
| **Conversation** | User's specific database needs, pain points, constraints |
| **Skill References** | Domain patterns from `references/` (design patterns, optimization, security) |
| **User Guidelines** | Project naming conventions, team standards, compliance requirements |

Ensure all required context is gathered before implementing.
Only ask user for THEIR specific requirements (domain expertise is in this skill).

---

## Required Clarifications

Ask about the USER'S context (not domain knowledge).
Avoid asking all questions at once — pace them across messages.

### Ask First (essential to start)
1. **Database engine**: "Which database system? (PostgreSQL, MySQL, SQL Server, SQLite, other)"
2. **Specific concern**: "Primary focus? (schema design, performance, security, migration, scaling)"

### Follow Up (after initial context gathered)
3. **Use case**: "What type of application? (OLTP, OLAP, mixed, microservices)"
4. **Scale expectations**: "Expected data volume and concurrent users?"
5. **Existing state**: "New design or optimizing an existing database?"

### Defaults (when user doesn't answer)
- Engine not specified → Default to PostgreSQL syntax; note equivalents in `references/engine-specific.md`
- Use case unclear → Assume OLTP (transactional)
- Scale not specified → Design for moderate scale (millions of rows, hundreds of concurrent users)
- Existing state unclear → Scan codebase first; if no schemas found, treat as new design

---

## Workflow

### Phase 1: Discovery & Assessment

```
Scan codebase for:
├── Existing schema files (*.sql, migrations/)
├── ORM models (models.py, entities/, *.prisma, schema.rb)
├── Query patterns (repositories/, queries/, DAOs)
├── Connection configuration (database.yml, .env, config/)
└── Current indexes, constraints, triggers
```

Assess current state against best practices in `references/`.

### Phase 2: Architecture Decision

Select approach based on user's needs:

| Need | Approach | Reference |
|------|----------|-----------|
| New schema design | Normalization → selective denormalization | `references/schema-design.md` |
| Query performance | EXPLAIN analysis → indexing → query rewrite | `references/performance-optimization.md` |
| Security hardening | Audit → encrypt → access control → injection prevention | `references/security-hardening.md` |
| Schema migration | Version strategy → backward compatibility → rollback plan | `references/migration-strategies.md` |
| Scaling | Vertical → read replicas → partitioning → sharding | `references/scaling-patterns.md` |
| Backup/DR | RPO/RTO analysis → strategy selection → testing | `references/backup-recovery.md` |
| Monitoring | Key metrics → alerting → connection pooling | `references/monitoring-observability.md` |

### Phase 3: Implementation

**Note**: Examples throughout this skill default to PostgreSQL syntax. For MySQL, SQL Server, or SQLite equivalents, see `references/engine-specific.md`.

Apply domain expertise from references. Follow these principles:

1. **Integrity first** - Enforce constraints at the database level, not just the application
2. **Least privilege** - Grant minimum required permissions
3. **Measure before optimizing** - Use EXPLAIN/ANALYZE before adding indexes
4. **Backward compatibility** - Migrations must not break running applications
5. **Defense in depth** - Layer security controls (network, auth, encryption, auditing)

### Phase 4: Validation

Before delivering any database work:

- [ ] Schema follows normalization rules (3NF minimum, denormalize only with justification)
- [ ] All tables have primary keys; foreign keys enforce referential integrity
- [ ] Indexes exist for columns in WHERE, JOIN, ORDER BY (verified with EXPLAIN)
- [ ] No SQL injection vectors (parameterized queries only)
- [ ] Naming conventions are consistent (see `references/schema-design.md`)
- [ ] Migration is reversible with a rollback script
- [ ] Sensitive data is encrypted (at rest and in transit)
- [ ] Backup strategy covers defined RPO/RTO targets

---

## Schema Design Quick Reference

### Naming Conventions

```
Tables:      snake_case, singular nouns     → user_account, order_item
Columns:     snake_case                     → first_name, created_at
Primary key: <table>_id or id              → user_account_id
Foreign key: <referenced_table>_id          → user_account_id
Indexes:     idx_<table>_<columns>          → idx_order_item_product_id
Constraints: chk_<table>_<rule>             → chk_order_item_quantity_positive
```

### Data Type Selection

| Data | Use | Avoid |
|------|-----|-------|
| Money/currency | DECIMAL(19,4) / NUMERIC | FLOAT, DOUBLE |
| Timestamps | TIMESTAMPTZ / DATETIME2 | VARCHAR, UNIX int |
| Booleans | BOOLEAN / BIT | INT, CHAR(1) |
| UUIDs | UUID / UNIQUEIDENTIFIER | VARCHAR(36) |
| Short text | VARCHAR(n) with sensible limit | TEXT for constrained fields |
| Large text | TEXT | VARCHAR(MAX) unless needed |
| IP addresses | INET (PG) / VARBINARY | VARCHAR |
| JSON data | JSONB (PG) / JSON | TEXT with manual parsing |

### Normalization Decision Tree

```
Start with 3NF (Third Normal Form)
  │
  ├── OLTP (transactional) → Stay at 3NF, denormalize only for proven bottlenecks
  │
  ├── OLAP (analytical) → Consider star/snowflake schema (denormalized fact tables)
  │
  └── Mixed → 3NF for writes + materialized views for read-heavy queries
```

---

## Indexing Strategy Quick Reference

```
When to index:
  ✓ Columns in WHERE clauses (high selectivity)
  ✓ Columns in JOIN conditions
  ✓ Columns in ORDER BY / GROUP BY
  ✓ Foreign key columns (always)

When NOT to index:
  ✗ Low-cardinality columns (boolean, status with 2-3 values) — unless combined in composite
  ✗ Tables with <1000 rows (full scan is faster)
  ✗ Write-heavy tables with minimal reads
  ✗ Columns rarely used in queries

Composite indexes:
  → Most selective column FIRST
  → Follow the left-prefix rule
  → Covering indexes eliminate table lookups (INCLUDE columns)
```

---

## Anti-Patterns to Prevent

| Anti-Pattern | Problem | Correct Approach |
|-------------|---------|------------------|
| SELECT * | Wastes I/O, breaks with schema changes | Explicit column list |
| Missing foreign keys | Orphaned data, integrity violations | Always define FK constraints |
| No index on FK columns | Slow JOINs and CASCADE operations | Index all foreign keys |
| Over-indexing | Slow writes, wasted storage | Index based on query patterns |
| Storing CSV in columns | Violates 1NF, unsearchable | Separate table with proper FK |
| Using ORM without reviewing SQL | N+1 queries, full table scans | Review generated SQL with EXPLAIN |
| Dynamic SQL from user input | SQL injection vulnerability | Parameterized queries only |
| No connection pooling | Connection exhaustion under load | Use PgBouncer / HikariCP / built-in pools |
| GUID/UUID as clustered key | Page splits, fragmentation | Sequential ID or ULID for clustered; UUID for distributed |

---

## Reference Files

| File | When to Read |
|------|--------------|
| `references/schema-design.md` | Designing new schemas, normalization/denormalization, constraints, data modeling |
| `references/performance-optimization.md` | Query tuning, EXPLAIN analysis, indexing deep-dive, caching |
| `references/security-hardening.md` | Encryption, access control, auditing, SQL injection prevention, compliance |
| `references/migration-strategies.md` | Schema versioning, Flyway/Liquibase, zero-downtime migrations, rollbacks |
| `references/scaling-patterns.md` | Partitioning, sharding, replication, read replicas, connection pooling |
| `references/backup-recovery.md` | Backup types, RPO/RTO planning, disaster recovery, high availability |
| `references/monitoring-observability.md` | Metrics, slow query detection, alerting, performance baselines |
| `references/engine-specific.md` | PostgreSQL, MySQL, SQL Server specific features, syntax, and tuning |

Grep patterns for large reference files:
- Schema: `## Normalization`, `## Constraints`, `## Data Types`
- Performance: `## EXPLAIN`, `## Index Types`, `## Query Rewriting`
- Security: `## Encryption`, `## Access Control`, `## SQL Injection`
- Migration: `## Flyway`, `## Liquibase`, `## Zero-Downtime`
- Scaling: `## Partitioning`, `## Sharding`, `## Replication`
