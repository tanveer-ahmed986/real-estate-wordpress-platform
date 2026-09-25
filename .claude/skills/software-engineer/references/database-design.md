# Database Design

## SQL vs NoSQL Decision Matrix

| Factor | Choose SQL | Choose NoSQL |
|--------|-----------|-------------|
| Data structure | Structured, relational | Flexible, evolving schema |
| Relationships | Complex joins, referential integrity | Embedded documents, denormalized |
| Consistency | ACID transactions required | Eventual consistency acceptable |
| Query patterns | Complex queries, aggregations | Simple key-value or document lookups |
| Scale | Vertical scaling (read replicas for reads) | Horizontal scaling built-in |
| Examples | PostgreSQL, MySQL | MongoDB, DynamoDB, Redis, Cassandra |

### Database Selection Guide

| Use Case | Recommended DB | Why |
|----------|---------------|-----|
| General purpose web app | PostgreSQL | Rich features, JSON support, reliable |
| Simple CRUD, WordPress-like | MySQL/MariaDB | Widely supported, mature ecosystem |
| Document storage, CMS | MongoDB | Flexible schema, nested documents |
| Caching, sessions | Redis | In-memory, sub-millisecond latency |
| Time-series data | TimescaleDB, InfluxDB | Optimized for time-based queries |
| Search | Elasticsearch, Meilisearch | Full-text search, faceted search |
| Graph relationships | Neo4j, Amazon Neptune | Social networks, recommendation engines |
| High-write IoT/logs | Cassandra, ScyllaDB | Write-optimized, horizontal scale |
| Key-value at scale | DynamoDB, Redis | Single-digit ms latency at any scale |
| Embedded/local | SQLite | Zero-config, file-based, great for mobile/CLI |

---

## Schema Design (SQL)

### Normalization Levels

| Form | Rule | When to Denormalize |
|------|------|---------------------|
| 1NF | Atomic values, no repeating groups | Never violate |
| 2NF | No partial dependencies on composite keys | Rarely violate |
| 3NF | No transitive dependencies | Read-heavy reporting tables |
| BCNF | Every determinant is a candidate key | Analytics/OLAP systems |

**Rule of thumb**: Normalize for writes (OLTP), denormalize for reads (OLAP/reporting).

### Naming Conventions

```sql
-- Tables: plural, snake_case
CREATE TABLE user_profiles (...);
CREATE TABLE order_items (...);

-- Columns: snake_case, descriptive
user_id         -- Foreign key: referenced_table_singular_id
created_at      -- Timestamps: verb_at
is_active       -- Booleans: is_/has_/can_ prefix
email_address   -- Descriptive, not abbreviated

-- Indexes: idx_table_columns
CREATE INDEX idx_orders_user_id ON orders(user_id);
CREATE INDEX idx_orders_status_created ON orders(status, created_at);

-- Constraints: pk_/fk_/uq_/ck_ prefix
CONSTRAINT pk_users PRIMARY KEY (id)
CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id)
CONSTRAINT uq_users_email UNIQUE (email)
CONSTRAINT ck_orders_total CHECK (total >= 0)
```

### Essential Columns Pattern

```sql
CREATE TABLE orders (
  id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  -- Business columns
  user_id       UUID NOT NULL REFERENCES users(id),
  status        VARCHAR(20) NOT NULL DEFAULT 'pending',
  total         DECIMAL(10,2) NOT NULL CHECK (total >= 0),
  -- Audit columns (include on every table)
  created_at    TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at    TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  -- Soft delete (optional)
  deleted_at    TIMESTAMPTZ
);

-- Auto-update updated_at trigger
CREATE OR REPLACE FUNCTION update_updated_at()
RETURNS TRIGGER AS $$
BEGIN
  NEW.updated_at = NOW();
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trigger_orders_updated_at
  BEFORE UPDATE ON orders
  FOR EACH ROW EXECUTE FUNCTION update_updated_at();
```

---

## Indexing Strategy

### When to Create Indexes

```
Index columns used in:
✓ WHERE clauses (equality and range)
✓ JOIN conditions
✓ ORDER BY / GROUP BY
✓ Foreign keys (always index FK columns)

Do NOT index:
✗ Low-cardinality columns on small tables
✗ Frequently updated columns (write overhead)
✗ Columns rarely used in queries
```

### Index Types

| Type | Use Case | Example |
|------|----------|---------|
| B-tree (default) | Equality, range, sorting | `WHERE status = 'active'` |
| Hash | Equality only | `WHERE id = 123` |
| GIN | Full-text search, JSONB, arrays | `WHERE tags @> '{urgent}'` |
| GiST | Geometric, range types | PostGIS spatial queries |
| Partial | Index subset of rows | `WHERE status = 'active'` only |
| Covering (INCLUDE) | Avoid table lookups | Include non-indexed columns in result |

### Composite Index Order

```sql
-- Rule: Most selective column first, equality before range
-- Query: WHERE status = 'active' AND created_at > '2025-01-01' ORDER BY total
CREATE INDEX idx_orders_status_created_total
  ON orders(status, created_at, total);

-- Composite index supports queries on leading columns:
-- ✓ WHERE status = 'active'
-- ✓ WHERE status = 'active' AND created_at > ...
-- ✓ WHERE status = 'active' AND created_at > ... ORDER BY total
-- ✗ WHERE created_at > ... (doesn't use index, status is leading)
```

---

## Query Optimization

### Common Anti-Patterns

```sql
-- BAD: SELECT * (fetches unnecessary columns)
SELECT * FROM users WHERE id = 1;
-- GOOD: Select only needed columns
SELECT id, name, email FROM users WHERE id = 1;

-- BAD: Function on indexed column (prevents index usage)
SELECT * FROM orders WHERE YEAR(created_at) = 2025;
-- GOOD: Use range condition
SELECT * FROM orders WHERE created_at >= '2025-01-01' AND created_at < '2026-01-01';

-- BAD: LIKE with leading wildcard (full table scan)
SELECT * FROM users WHERE name LIKE '%john%';
-- GOOD: Use full-text search or trigram index
SELECT * FROM users WHERE name_tsvector @@ to_tsquery('john');

-- BAD: N+1 queries in application code
-- GOOD: Use JOINs or batch queries

-- BAD: No LIMIT on potentially large result sets
SELECT * FROM logs WHERE level = 'error';
-- GOOD: Always paginate
SELECT * FROM logs WHERE level = 'error' ORDER BY created_at DESC LIMIT 50;
```

### EXPLAIN ANALYZE

```sql
EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT)
SELECT u.name, COUNT(o.id) as order_count
FROM users u
JOIN orders o ON o.user_id = u.id
WHERE o.created_at > '2025-01-01'
GROUP BY u.id
ORDER BY order_count DESC
LIMIT 10;

-- Look for:
-- Seq Scan → Missing index (consider adding one)
-- Nested Loop → OK for small joins, bad for large
-- Hash Join → Good for large equality joins
-- Sort → Consider index for ORDER BY
-- Rows (actual vs estimated) → Stale statistics? Run ANALYZE
```

---

## Migration Best Practices

### Rules
1. **Always reversible**: Include `up` and `down` migrations
2. **One concern per migration**: Don't mix schema + data changes
3. **Zero-downtime compatible**: No locking operations on large tables
4. **Idempotent**: Safe to run multiple times
5. **Tested**: Test migrations against production-size data

### Zero-Downtime Patterns

```sql
-- Adding a column (safe)
ALTER TABLE users ADD COLUMN phone VARCHAR(20);

-- Adding NOT NULL column (safe pattern):
-- Step 1: Add nullable column
ALTER TABLE users ADD COLUMN phone VARCHAR(20);
-- Step 2: Backfill data
UPDATE users SET phone = '' WHERE phone IS NULL;
-- Step 3: Add constraint (after deployment)
ALTER TABLE users ALTER COLUMN phone SET NOT NULL;

-- Renaming a column (unsafe - use transition period):
-- Step 1: Add new column, dual-write
-- Step 2: Migrate data
-- Step 3: Switch reads to new column
-- Step 4: Remove old column (next release)

-- Adding index (safe with CONCURRENTLY):
CREATE INDEX CONCURRENTLY idx_users_email ON users(email);
```

### Migration Tools

| Stack | Tool |
|-------|------|
| Node.js | Prisma Migrate, Knex, TypeORM |
| Python | Alembic (SQLAlchemy), Django Migrations |
| Go | golang-migrate, goose |
| Ruby | ActiveRecord Migrations |
| Java | Flyway, Liquibase |

---

## Connection Management

```
Application → Connection Pool → Database

Pool Configuration:
- min: 2 (keep connections warm)
- max: (cpu_cores * 2) + spindles (typically 5-20)
- idle_timeout: 30s (release idle connections)
- connection_timeout: 5s (fail fast if pool exhausted)

Tools:
- PostgreSQL: pgBouncer (transaction pooling), built-in pool
- MySQL: ProxySQL, MySQL Router
- Application-level: HikariCP (Java), knex pool (Node.js)
```

---

## Sources
- PostgreSQL Documentation: https://www.postgresql.org/docs/
- Use The Index, Luke: https://use-the-index-luke.com/
- Markus Winand: SQL Performance Explained
- MongoDB Schema Design Best Practices
