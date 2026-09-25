# Performance Optimization Reference

Query tuning, indexing strategies, execution plan analysis, and caching patterns.

---

## EXPLAIN Analysis

### PostgreSQL
```sql
-- Basic plan
EXPLAIN SELECT * FROM order_item WHERE product_id = 42;

-- With actual runtime stats (executes the query)
EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT)
SELECT * FROM order_item WHERE product_id = 42;

-- JSON format for programmatic analysis
EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON)
SELECT * FROM order_item WHERE product_id = 42;
```

### MySQL
```sql
-- Basic plan
EXPLAIN SELECT * FROM order_item WHERE product_id = 42;

-- Extended format
EXPLAIN FORMAT=JSON SELECT * FROM order_item WHERE product_id = 42;

-- Actual execution stats (MySQL 8.0.18+)
EXPLAIN ANALYZE SELECT * FROM order_item WHERE product_id = 42;
```

### SQL Server
```sql
-- Estimated plan
SET SHOWPLAN_XML ON;
GO
SELECT * FROM order_item WHERE product_id = 42;
GO
SET SHOWPLAN_XML OFF;

-- Actual execution stats
SET STATISTICS IO ON;
SET STATISTICS TIME ON;
SELECT * FROM order_item WHERE product_id = 42;
```

### What to Look For in EXPLAIN Output

| Indicator | Problem | Action |
|-----------|---------|--------|
| Seq Scan / Full Table Scan | No usable index | Add appropriate index |
| Nested Loop with high rows | Inefficient join | Check join columns are indexed |
| Sort (on disk) | Memory insufficient | Increase work_mem or add index |
| Hash Join (unexpected) | Missing index on join column | Add index |
| High actual vs estimated rows | Stale statistics | Run ANALYZE / UPDATE STATISTICS |
| Bitmap Heap Scan | Multiple indexes being combined | Consider composite index |
| Index Scan (good) | Using index effectively | No action needed |
| Index Only Scan (best) | Covering index, no table access | Optimal |

---

## Index Types

### B-Tree (Default)
Best for: equality (=), range (<, >, BETWEEN), sorting (ORDER BY), prefix matching (LIKE 'abc%')
```sql
CREATE INDEX idx_order_created_at ON purchase_order(created_at);
```

### Hash (PostgreSQL)
Best for: equality-only lookups (=)
```sql
CREATE INDEX idx_session_token ON user_session USING HASH (session_token);
-- Faster than B-tree for equality, but no range queries
```

### GIN (Generalized Inverted Index - PostgreSQL)
Best for: full-text search, JSONB, arrays, tsvector
```sql
-- JSONB containment queries
CREATE INDEX idx_product_metadata ON product USING GIN (metadata jsonb_path_ops);
-- Full-text search
CREATE INDEX idx_article_search ON article USING GIN (to_tsvector('english', title || ' ' || body));
```

### GiST (Generalized Search Tree - PostgreSQL)
Best for: geometric data, range types, full-text (phrase search), nearest-neighbor
```sql
CREATE INDEX idx_location_geom ON location USING GIST (coordinates);
CREATE INDEX idx_booking_during ON room_booking USING GIST (during);
```

### Columnstore (SQL Server)
Best for: analytical queries on large datasets, aggregations
```sql
CREATE CLUSTERED COLUMNSTORE INDEX cci_sales ON fact_sales;
-- Up to 10x query performance for analytical workloads
-- Up to 7x data compression
```

### Filtered Index
Best for: queries that always filter on a specific condition
```sql
-- Only index active orders (reduces index size dramatically)
CREATE INDEX idx_order_active ON purchase_order(created_at)
    WHERE status = 'active';

-- Only index non-null values
CREATE INDEX idx_user_phone ON user_account(phone_number)
    WHERE phone_number IS NOT NULL;
```

### Covering Index (INCLUDE)
```sql
-- Avoids table lookup: all needed columns are in the index
CREATE INDEX idx_order_customer_covering
    ON purchase_order(customer_id)
    INCLUDE (order_date, total_amount, status);

-- Query satisfied entirely from index (Index Only Scan):
SELECT order_date, total_amount, status
FROM purchase_order WHERE customer_id = 42;
```

---

## Composite Index Design

### Column Order Matters (Left-Prefix Rule)
```sql
CREATE INDEX idx_order_item_composite ON order_item(order_id, product_id, quantity);

-- Uses index: (matches left prefix)
WHERE order_id = 1
WHERE order_id = 1 AND product_id = 5
WHERE order_id = 1 AND product_id = 5 AND quantity > 10

-- Does NOT use index: (skips leading column)
WHERE product_id = 5
WHERE quantity > 10
```

### Selectivity First
Place the most selective column (highest cardinality) first:
```sql
-- GOOD: user_id is highly selective, status has few values
CREATE INDEX idx_order_user_status ON purchase_order(user_id, status);

-- BAD: status first means scanning many rows before filtering by user
CREATE INDEX idx_order_status_user ON purchase_order(status, user_id);
```

### Equality Before Range
```sql
-- GOOD: equality columns first, range column last
CREATE INDEX idx_order_search ON purchase_order(status, customer_id, created_at);
-- For: WHERE status = 'active' AND customer_id = 42 AND created_at > '2025-01-01'

-- BAD: range column in middle stops index usage for subsequent columns
CREATE INDEX idx_order_search ON purchase_order(status, created_at, customer_id);
```

---

## Query Rewriting Patterns

### Avoid SELECT *
```sql
-- BAD: fetches all columns, prevents covering index usage
SELECT * FROM user_account WHERE department_id = 5;

-- GOOD: fetch only needed columns
SELECT user_account_id, email, first_name FROM user_account WHERE department_id = 5;
```

### Replace Correlated Subqueries with JOINs
```sql
-- SLOW: correlated subquery executes per row
SELECT o.order_id, (SELECT SUM(quantity) FROM order_item oi WHERE oi.order_id = o.order_id)
FROM purchase_order o;

-- FAST: single join with aggregation
SELECT o.order_id, COALESCE(SUM(oi.quantity), 0) AS total_quantity
FROM purchase_order o
LEFT JOIN order_item oi ON o.order_id = oi.order_id
GROUP BY o.order_id;
```

### Use EXISTS Instead of IN for Subqueries
```sql
-- SLOW with large subquery result
SELECT * FROM product WHERE product_id IN (SELECT product_id FROM order_item);

-- FAST: stops at first match
SELECT * FROM product p WHERE EXISTS (
    SELECT 1 FROM order_item oi WHERE oi.product_id = p.product_id
);
```

### Pagination Optimization
```sql
-- SLOW: OFFSET scans and discards rows
SELECT * FROM product ORDER BY product_id LIMIT 20 OFFSET 10000;

-- FAST: keyset/cursor pagination
SELECT * FROM product WHERE product_id > 10000 ORDER BY product_id LIMIT 20;
```

### Batch Operations
```sql
-- SLOW: individual inserts
INSERT INTO log_entry (message) VALUES ('msg1');
INSERT INTO log_entry (message) VALUES ('msg2');

-- FAST: batch insert
INSERT INTO log_entry (message) VALUES ('msg1'), ('msg2'), ('msg3');
-- Or use COPY (PostgreSQL) / LOAD DATA INFILE (MySQL) for bulk
```

---

## Caching Strategies

### Materialized Views (PostgreSQL)
```sql
CREATE MATERIALIZED VIEW mv_monthly_sales AS
SELECT date_trunc('month', order_date) AS month,
       SUM(total_amount) AS revenue,
       COUNT(*) AS order_count
FROM purchase_order
WHERE status = 'completed'
GROUP BY date_trunc('month', order_date);

-- Refresh (full)
REFRESH MATERIALIZED VIEW mv_monthly_sales;
-- Refresh concurrently (no lock, requires unique index)
REFRESH MATERIALIZED VIEW CONCURRENTLY mv_monthly_sales;
```

### Query Result Caching
- **MySQL**: Query cache (removed in 8.0; use ProxySQL or application-level cache)
- **PostgreSQL**: No built-in query cache; use pgpool-II or application-level cache (Redis/Memcached)
- **SQL Server**: Automatic plan cache; use In-Memory OLTP for hot data

### Connection Pooling
| Database | Tool | Configuration |
|----------|------|---------------|
| PostgreSQL | PgBouncer | `pool_mode = transaction`, `max_client_conn = 1000`, `default_pool_size = 25` |
| MySQL | ProxySQL / MySQL Router | Connection multiplexing, query routing |
| SQL Server | Built-in (.NET) / HikariCP (Java) | `max_pool_size = 100`, `min_pool_size = 10` |
| Any | HikariCP (JVM) | `maximumPoolSize = 10 * CPU_cores` |

**Formula**: Optimal pool size = (CPU cores * 2) + effective_spindle_count
For SSD: effective_spindle_count ≈ number of parallel I/O channels

---

## Statistics and Maintenance

### PostgreSQL
```sql
-- Update statistics for query planner
ANALYZE table_name;
ANALYZE;  -- All tables

-- Reclaim dead tuples and update visibility map
VACUUM ANALYZE table_name;

-- Full table rewrite (locks table, use sparingly)
VACUUM FULL table_name;

-- Reindex to fix bloated indexes
REINDEX INDEX idx_name;
REINDEX TABLE table_name CONCURRENTLY;  -- PG 12+, no lock
```

### MySQL
```sql
-- Update statistics
ANALYZE TABLE table_name;

-- Reclaim space and defragment
OPTIMIZE TABLE table_name;

-- Check for corruption
CHECK TABLE table_name;
```

### SQL Server
```sql
-- Update statistics
UPDATE STATISTICS table_name;
UPDATE STATISTICS table_name WITH FULLSCAN;  -- Most accurate

-- Rebuild indexes (reclaims space, updates stats)
ALTER INDEX ALL ON table_name REBUILD;

-- Reorganize indexes (online, less thorough)
ALTER INDEX ALL ON table_name REORGANIZE;
```
