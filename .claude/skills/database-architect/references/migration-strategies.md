# Migration Strategies Reference

Schema versioning, migration tools, zero-downtime migrations, and rollback patterns.

---

## Migration Principles

1. **Version control**: Every schema change is a versioned migration file
2. **Idempotent**: Migrations can be re-run safely (use IF EXISTS / IF NOT EXISTS)
3. **Forward-only in production**: Prefer new forward migrations over rollbacks
4. **Backward compatible**: Old app version must work with new schema during deployment
5. **Tested**: Run migrations against a production-clone before applying to production
6. **Reversible**: Every migration has a corresponding rollback script

---

## Tool Selection

| Tool | Language | Format | Strengths |
|------|----------|--------|-----------|
| **Flyway** | Java/CLI | SQL or Java | Simple convention-based, fast adoption |
| **Liquibase** | Java/CLI | XML/YAML/JSON/SQL | Database-agnostic changesets, drift detection |
| **Alembic** | Python | Python | SQLAlchemy integration, autogenerate from models |
| **Prisma Migrate** | Node.js | Prisma schema | TypeScript projects, declarative schema |
| **Rails Migrations** | Ruby | Ruby DSL | Rails ecosystem, reversible by default |
| **Knex.js** | Node.js | JavaScript | Lightweight, promise-based |
| **golang-migrate** | Go | SQL | Minimal, works with any Go project |
| **dbmate** | Any | SQL | Language-agnostic, simple CLI |

---

## Flyway Patterns

### File Naming Convention
```
V1__create_user_account_table.sql        # Versioned (V<version>__<description>)
V2__add_email_index.sql
V3__create_order_tables.sql
R__refresh_reporting_views.sql           # Repeatable (R__<description>)
U2__undo_add_email_index.sql             # Undo (U<version>__<description>) [Teams+]
```

### Configuration (flyway.toml)
```toml
[environments.default]
url = "jdbc:postgresql://localhost:5432/mydb"
user = "flyway_user"
password = "${FLYWAY_PASSWORD}"
schemas = ["public"]

[flyway]
locations = ["filesystem:db/migrations"]
baselineOnMigrate = true
outOfOrder = false
validateOnMigrate = true
```

### Example Migration
```sql
-- V1__create_user_account_table.sql
CREATE TABLE user_account (
    user_account_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE UNIQUE INDEX idx_user_account_email ON user_account(email);
```

---

## Liquibase Patterns

### Changelog Structure
```yaml
# db/changelog/db.changelog-master.yaml
databaseChangeLog:
  - include:
      file: db/changelog/changes/001-create-user-account.yaml
  - include:
      file: db/changelog/changes/002-add-order-tables.yaml
```

### Changeset Example
```yaml
# db/changelog/changes/001-create-user-account.yaml
databaseChangeLog:
  - changeSet:
      id: 1
      author: developer_name
      changes:
        - createTable:
            tableName: user_account
            columns:
              - column:
                  name: user_account_id
                  type: BIGINT
                  autoIncrement: true
                  constraints:
                    primaryKey: true
              - column:
                  name: email
                  type: VARCHAR(255)
                  constraints:
                    nullable: false
                    unique: true
      rollback:
        - dropTable:
            tableName: user_account
```

---

## Zero-Downtime Migration Patterns

### Expand-Contract Pattern (Safe Column Rename)
```
Step 1 (Expand): Add new column, backfill, dual-write
Step 2 (Migrate): Update app to read from new column
Step 3 (Contract): Drop old column
```

```sql
-- Step 1: Add new column
ALTER TABLE user_account ADD COLUMN full_name VARCHAR(200);
-- Backfill
UPDATE user_account SET full_name = first_name || ' ' || last_name;
-- App now writes to BOTH old and new columns

-- Step 2: Deploy app reading from full_name
-- Verify data consistency

-- Step 3: Drop old columns (next release)
ALTER TABLE user_account DROP COLUMN first_name;
ALTER TABLE user_account DROP COLUMN last_name;
```

### Safe Column Addition
```sql
-- Safe: Adding nullable column (instant in most engines)
ALTER TABLE user_account ADD COLUMN phone VARCHAR(20);

-- Safe: Adding column with default (PG 11+, MySQL 8.0+ instant)
ALTER TABLE user_account ADD COLUMN is_verified BOOLEAN NOT NULL DEFAULT FALSE;

-- DANGEROUS: Adding NOT NULL without default on large table
-- This rewrites the entire table and locks it
ALTER TABLE user_account ADD COLUMN status VARCHAR(20) NOT NULL;  -- AVOID
```

### Safe Index Creation
```sql
-- PostgreSQL: Build index without locking writes
CREATE INDEX CONCURRENTLY idx_user_account_phone ON user_account(phone);

-- MySQL: Online DDL (InnoDB)
ALTER TABLE user_account ADD INDEX idx_phone (phone), ALGORITHM=INPLACE, LOCK=NONE;

-- SQL Server: Online index build
CREATE INDEX idx_user_account_phone ON user_account(phone) WITH (ONLINE = ON);
```

### Safe Table Rename
```sql
-- DON'T rename directly (breaks running app)
-- DO: Create new table, backfill, switch
CREATE TABLE customer AS SELECT * FROM user_account;  -- AVOID (loses constraints)

-- BETTER: Use view as abstraction layer
CREATE VIEW customer AS SELECT * FROM user_account;
-- Update app to use 'customer', then rename underlying table later
```

### Adding Foreign Keys to Large Tables
```sql
-- PostgreSQL: Validate separately to avoid long lock
ALTER TABLE order_item ADD CONSTRAINT fk_order_item_product
    FOREIGN KEY (product_id) REFERENCES product(product_id) NOT VALID;
-- Later (no lock):
ALTER TABLE order_item VALIDATE CONSTRAINT fk_order_item_product;
```

---

## Rollback Strategies

### Approach 1: Forward-Fix (Preferred)
```
Problem detected → Create NEW migration to fix → Deploy forward
```
Best when: The forward fix is simple and fast.

### Approach 2: Prepared Rollback Script
```sql
-- Migration: V5__add_loyalty_points.sql
ALTER TABLE user_account ADD COLUMN loyalty_points INT DEFAULT 0;

-- Rollback: rollback/V5__rollback.sql
ALTER TABLE user_account DROP COLUMN loyalty_points;
```

### Approach 3: Blue-Green Database
```
1. Clone database schema to "green" instance
2. Apply migration to green
3. Test green thoroughly
4. Switch traffic from blue to green
5. Keep blue as rollback target
```

---

## Migration Checklist

Before applying any migration to production:

- [ ] Migration runs successfully on a production-clone database
- [ ] Rollback script exists and has been tested
- [ ] Migration is backward-compatible with the currently deployed application
- [ ] Large table operations use CONCURRENTLY / ONLINE / NOT VALID patterns
- [ ] No `DROP TABLE` or `DROP COLUMN` without data verification
- [ ] Migration execution time estimated (test on production-size data)
- [ ] Application team notified of schema changes
- [ ] Monitoring dashboard ready to detect issues post-deployment
