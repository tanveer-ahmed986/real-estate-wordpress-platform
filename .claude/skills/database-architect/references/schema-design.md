# Schema Design Reference

Comprehensive database schema design patterns, normalization theory, and data modeling best practices.

---

## Normalization

### First Normal Form (1NF)
- Each column contains atomic (indivisible) values
- No repeating groups or arrays in a single column
- Each row is unique (has a primary key)

```sql
-- BAD: Violates 1NF (repeating group)
CREATE TABLE orders (
    order_id INT PRIMARY KEY,
    product_names VARCHAR(500)  -- "Widget, Gadget, Gizmo"
);

-- GOOD: Atomic values, separate table
CREATE TABLE order_item (
    order_item_id INT PRIMARY KEY,
    order_id INT REFERENCES orders(order_id),
    product_id INT REFERENCES product(product_id),
    quantity INT NOT NULL CHECK (quantity > 0)
);
```

### Second Normal Form (2NF)
- Must be in 1NF
- Every non-key column depends on the ENTIRE primary key (no partial dependencies)

```sql
-- BAD: product_name depends only on product_id, not full PK
CREATE TABLE order_item (
    order_id INT,
    product_id INT,
    product_name VARCHAR(100),  -- Partial dependency
    quantity INT,
    PRIMARY KEY (order_id, product_id)
);

-- GOOD: Remove partial dependency to separate table
CREATE TABLE product (
    product_id INT PRIMARY KEY,
    product_name VARCHAR(100) NOT NULL
);
CREATE TABLE order_item (
    order_id INT,
    product_id INT REFERENCES product(product_id),
    quantity INT NOT NULL,
    PRIMARY KEY (order_id, product_id)
);
```

### Third Normal Form (3NF)
- Must be in 2NF
- No transitive dependencies (non-key column depending on another non-key column)

```sql
-- BAD: city depends on zip_code, not directly on customer_id
CREATE TABLE customer (
    customer_id INT PRIMARY KEY,
    zip_code VARCHAR(10),
    city VARCHAR(100)  -- Transitive dependency via zip_code
);

-- GOOD: Separate lookup table
CREATE TABLE zip_code_lookup (
    zip_code VARCHAR(10) PRIMARY KEY,
    city VARCHAR(100) NOT NULL,
    state VARCHAR(50) NOT NULL
);
CREATE TABLE customer (
    customer_id INT PRIMARY KEY,
    zip_code VARCHAR(10) REFERENCES zip_code_lookup(zip_code)
);
```

### When to Denormalize

| Scenario | Strategy | Trade-off |
|----------|----------|-----------|
| Read-heavy reporting | Materialized views | Stale data (refresh interval) |
| Frequently joined data | Redundant columns | Write complexity, consistency risk |
| Analytics/OLAP | Star/snowflake schema | Storage increase |
| Caching layer | Precomputed aggregates | Maintenance overhead |
| High-traffic lookups | Embedding in parent table | Update anomalies |

**Rule**: Always start normalized. Denormalize only after measuring a real bottleneck with EXPLAIN.

---

## Constraint Architecture

### Primary Keys
```sql
-- Surrogate key (preferred for most OLTP)
CREATE TABLE user_account (
    user_account_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE
);

-- Natural key (when business key is stable and unique)
CREATE TABLE country (
    country_code CHAR(2) PRIMARY KEY,  -- ISO 3166-1 alpha-2
    country_name VARCHAR(100) NOT NULL
);

-- Composite key (junction/bridge tables)
CREATE TABLE user_role (
    user_account_id BIGINT REFERENCES user_account(user_account_id),
    role_id INT REFERENCES role(role_id),
    assigned_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (user_account_id, role_id)
);
```

### Foreign Key Actions
```sql
-- CASCADE: Delete child rows when parent is deleted
FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE

-- SET NULL: Nullify FK when parent is deleted
FOREIGN KEY (manager_id) REFERENCES employee(employee_id) ON DELETE SET NULL

-- RESTRICT: Prevent parent deletion if children exist (safest default)
FOREIGN KEY (department_id) REFERENCES department(department_id) ON DELETE RESTRICT

-- SET DEFAULT: Set FK to default value when parent is deleted
FOREIGN KEY (category_id) REFERENCES category(category_id) ON DELETE SET DEFAULT
```

**Guidelines**:
- Use CASCADE for dependent/owned entities (order → order_items)
- Use RESTRICT for reference data (employee → department)
- Use SET NULL for optional relationships (employee → manager)
- Always define ON DELETE and ON UPDATE explicitly

### Check Constraints
```sql
-- Value ranges
CHECK (quantity > 0)
CHECK (discount_percent BETWEEN 0 AND 100)
CHECK (start_date < end_date)

-- Enumerated values
CHECK (status IN ('draft', 'active', 'archived'))
CHECK (priority IN ('low', 'medium', 'high', 'critical'))

-- Pattern matching (PostgreSQL)
CHECK (email ~ '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}$')
CHECK (phone ~ '^\+?[1-9]\d{1,14}$')
```

### Exclusion Constraints (PostgreSQL)
```sql
-- Prevent overlapping date ranges for the same room
CREATE TABLE room_booking (
    booking_id INT PRIMARY KEY,
    room_id INT NOT NULL,
    during TSTZRANGE NOT NULL,
    EXCLUDE USING GIST (room_id WITH =, during WITH &&)
);
```

---

## Data Modeling Patterns

### Soft Deletes
```sql
CREATE TABLE user_account (
    user_account_id BIGINT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    deleted_at TIMESTAMPTZ,
    -- Partial unique index: unique email among non-deleted
    CONSTRAINT uq_user_account_email_active UNIQUE (email) WHERE (deleted_at IS NULL)
    -- Note: PostgreSQL supports partial unique indexes; others use filtered index
);
```

### Audit Trail (created/updated tracking)
```sql
CREATE TABLE order (
    order_id BIGINT PRIMARY KEY,
    -- ... business columns ...
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    created_by BIGINT NOT NULL REFERENCES user_account(user_account_id),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_by BIGINT NOT NULL REFERENCES user_account(user_account_id)
);
-- Use trigger or application layer to auto-set updated_at
```

### Self-Referencing Hierarchy
```sql
-- Adjacency list (simple, good for shallow trees)
CREATE TABLE category (
    category_id INT PRIMARY KEY,
    parent_category_id INT REFERENCES category(category_id),
    name VARCHAR(100) NOT NULL
);

-- Materialized path (fast reads, slower writes)
CREATE TABLE category (
    category_id INT PRIMARY KEY,
    path VARCHAR(500) NOT NULL,  -- '/1/5/23/'
    name VARCHAR(100) NOT NULL
);
-- Query descendants: WHERE path LIKE '/1/5/%'
```

### Multi-Tenant Patterns
```sql
-- Row-level isolation (shared tables with tenant column)
CREATE TABLE invoice (
    invoice_id BIGINT PRIMARY KEY,
    tenant_id INT NOT NULL REFERENCES tenant(tenant_id),
    -- ... business columns ...
);
CREATE INDEX idx_invoice_tenant_id ON invoice(tenant_id);
-- Enforce via Row-Level Security (PostgreSQL):
ALTER TABLE invoice ENABLE ROW LEVEL SECURITY;
CREATE POLICY tenant_isolation ON invoice
    USING (tenant_id = current_setting('app.tenant_id')::INT);

-- Schema-per-tenant (stronger isolation, more operational overhead)
CREATE SCHEMA tenant_acme;
CREATE TABLE tenant_acme.invoice (...);
```

### Polymorphic Associations
```sql
-- PREFERRED: Separate FK per type (explicit, enforced by DB)
CREATE TABLE comment (
    comment_id BIGINT PRIMARY KEY,
    body TEXT NOT NULL,
    article_id BIGINT REFERENCES article(article_id),
    video_id BIGINT REFERENCES video(video_id),
    CHECK (
        (article_id IS NOT NULL AND video_id IS NULL) OR
        (article_id IS NULL AND video_id IS NOT NULL)
    )
);

-- AVOID: Generic FK (no referential integrity)
-- commentable_type VARCHAR + commentable_id INT → Cannot enforce FK
```

---

## Naming Conventions

### Tables
- snake_case, singular nouns: `user_account`, `order_item`, `product_category`
- Junction tables: `<table1>_<table2>` alphabetically: `product_tag`, `role_permission`
- Avoid reserved words: `user` → `user_account`, `order` → `purchase_order`

### Columns
- snake_case: `first_name`, `created_at`, `is_active`
- Boolean prefix: `is_`, `has_`, `can_`: `is_active`, `has_subscription`
- Timestamp suffix: `_at`: `created_at`, `updated_at`, `deleted_at`
- Date suffix: `_on` or `_date`: `shipped_on`, `birth_date`
- Foreign keys: `<referenced_table>_id`: `user_account_id`, `category_id`

### Indexes
```
idx_<table>_<column(s)>           → idx_order_item_product_id
idx_<table>_<column(s)>_<type>    → idx_user_account_email_unique
```

### Constraints
```
pk_<table>                        → pk_user_account
fk_<table>_<referenced_table>     → fk_order_item_product
uq_<table>_<column(s)>            → uq_user_account_email
chk_<table>_<rule>                → chk_order_item_quantity_positive
```
