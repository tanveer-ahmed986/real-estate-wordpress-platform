# Engine-Specific Reference

PostgreSQL, MySQL, SQL Server, and SQLite specific features, syntax differences, and tuning parameters.

## Official Documentation

| Engine | URL | Notes |
|--------|-----|-------|
| PostgreSQL | https://www.postgresql.org/docs/current/ | Comprehensive, version-selectable |
| MySQL | https://dev.mysql.com/doc/refman/8.0/en/ | Reference manual with examples |
| SQL Server | https://learn.microsoft.com/en-us/sql/ | Microsoft Learn, covers Azure SQL too |
| SQLite | https://www.sqlite.org/docs.html | Compact, single-page references |

For edge cases or features not covered in this skill's references, consult the official docs above.

---

## PostgreSQL

### Key Configuration Parameters
```
# Memory
shared_buffers = '4GB'              # 25% of RAM (start here)
effective_cache_size = '12GB'       # 75% of RAM (tells planner about OS cache)
work_mem = '64MB'                   # Per-operation sort/hash memory
maintenance_work_mem = '1GB'        # VACUUM, CREATE INDEX, ALTER TABLE
huge_pages = try                    # Reduce TLB misses on large RAM

# WAL
wal_level = replica                 # Required for replication + PITR
max_wal_size = '4GB'                # Before forced checkpoint
min_wal_size = '1GB'                # Minimum WAL retention
checkpoint_completion_target = 0.9  # Spread checkpoint I/O

# Query Planner
random_page_cost = 1.1              # SSD (default 4.0 for HDD)
effective_io_concurrency = 200      # SSD (default 1 for HDD)
default_statistics_target = 200     # More histogram buckets for better plans

# Connections
max_connections = 200               # Keep low, use PgBouncer for more
```

### Unique PostgreSQL Features
| Feature | Use Case | Example |
|---------|----------|---------|
| JSONB | Semi-structured data, flexible schema | `metadata JSONB`, GIN indexes |
| Array types | Multi-value columns without join tables | `tags TEXT[]` |
| Range types | Date ranges, numeric ranges | `TSTZRANGE`, exclusion constraints |
| Table inheritance | Shared schema across related tables | `INHERITS (parent_table)` |
| Declarative partitioning | Large table management | `PARTITION BY RANGE/LIST/HASH` |
| Row-level security | Multi-tenant isolation | `CREATE POLICY` |
| LISTEN/NOTIFY | Real-time event notification | Pub/sub within database |
| Foreign data wrappers | Query external data sources | `postgres_fdw`, `file_fdw` |
| Generated columns | Computed/derived values | `GENERATED ALWAYS AS (expr) STORED` |
| Exclusion constraints | Complex uniqueness (no overlaps) | `EXCLUDE USING GIST` |

### PostgreSQL Extensions
| Extension | Purpose |
|-----------|---------|
| `pg_stat_statements` | Query performance statistics (ESSENTIAL) |
| `pgcrypto` | Column-level encryption |
| `postgis` | Geographic/spatial data |
| `pg_trgm` | Trigram similarity search |
| `uuid-ossp` / `pgcrypto` | UUID generation |
| `pgaudit` | Audit logging |
| `pg_partman` | Automatic partition management |
| `timescaledb` | Time-series optimization |
| `citus` | Distributed/sharded PostgreSQL |
| `pgvector` | Vector similarity search (AI/ML) |

---

## MySQL (InnoDB)

### Key Configuration Parameters
```
# Memory
innodb_buffer_pool_size = 12G       # 70-80% of RAM
innodb_buffer_pool_instances = 8    # 1 per GB of buffer pool (max 64)
innodb_log_buffer_size = 64M        # WAL buffer before flush

# I/O
innodb_io_capacity = 2000           # IOPS for background tasks (SSD)
innodb_io_capacity_max = 4000       # Peak IOPS
innodb_flush_method = O_DIRECT      # Bypass OS cache (Linux)
innodb_flush_log_at_trx_commit = 1  # 1=safest, 2=faster (1s window)

# Redo Log
innodb_redo_log_capacity = 4G       # MySQL 8.0.30+ (replaces log_file_size)

# Connections
max_connections = 500
thread_cache_size = 32              # Reuse threads

# Query
join_buffer_size = 8M               # Per-join buffer for non-indexed joins
sort_buffer_size = 4M               # Per-sort buffer
tmp_table_size = 256M               # Max in-memory temp table
max_heap_table_size = 256M          # Must match tmp_table_size
```

### MySQL-Specific Features
| Feature | Use Case |
|---------|----------|
| Generated columns (VIRTUAL/STORED) | Computed fields, functional indexes |
| JSON data type | Semi-structured data with JSON_EXTRACT |
| Invisible indexes | Test index removal without dropping |
| Descending indexes | Optimized ORDER BY ... DESC |
| Window functions | Analytics without self-joins |
| Common Table Expressions (CTE) | Recursive queries, readability |
| EXPLAIN ANALYZE | Actual execution statistics (8.0.18+) |
| MySQL Shell | Admin utilities, InnoDB Cluster setup |
| Group Replication | Multi-primary HA cluster |
| Clone Plugin | Fast full copy for replica provisioning |

### MySQL vs PostgreSQL Syntax Differences
| Operation | MySQL | PostgreSQL |
|-----------|-------|------------|
| Auto-increment | `INT AUTO_INCREMENT` | `INT GENERATED ALWAYS AS IDENTITY` |
| Upsert | `INSERT ... ON DUPLICATE KEY UPDATE` | `INSERT ... ON CONFLICT DO UPDATE` |
| String concat | `CONCAT(a, b)` | `a \|\| b` |
| Current timestamp | `NOW()` | `NOW()` or `CURRENT_TIMESTAMP` |
| Boolean | `TINYINT(1)` | `BOOLEAN` |
| LIMIT with offset | `LIMIT 10 OFFSET 20` | `LIMIT 10 OFFSET 20` |
| Full-text search | `FULLTEXT INDEX` + `MATCH AGAINST` | `tsvector` + `GIN INDEX` |
| JSON access | `JSON_EXTRACT(col, '$.key')` or `col->'$.key'` | `col->'key'` or `col->>'key'` |

---

## SQL Server

### Key Configuration Parameters
```sql
-- Memory
EXEC sp_configure 'max server memory (MB)', 12288;  -- Leave 2-4GB for OS
RECONFIGURE;

-- Parallelism
EXEC sp_configure 'max degree of parallelism', 4;   -- Usually = CPU cores / 2
EXEC sp_configure 'cost threshold for parallelism', 50;  -- Raise from default 5
RECONFIGURE;

-- TempDB
-- Best practice: 1 data file per CPU core (up to 8), equal size, on fast storage
-- Move tempdb to dedicated SSD

-- Query Store (enable for all production databases)
ALTER DATABASE MyDB SET QUERY_STORE = ON;
```

### SQL Server-Specific Features
| Feature | Use Case |
|---------|----------|
| Always Encrypted | Client-side encryption (data invisible to server) |
| Columnstore indexes | Analytics, 10x performance for aggregations |
| In-Memory OLTP | Ultra-low latency for hot tables |
| Temporal tables | Built-in history tracking (system-versioned) |
| Dynamic data masking | Hide sensitive data from unprivileged users |
| Stretch Database | Transparent cold data archival to Azure |
| Query Store | Query plan history, regression detection |
| Automatic tuning | Auto-create/drop indexes, force good plans |
| Availability Groups | HA + read replicas + automatic failover |
| PolyBase | Query external data (Hadoop, Azure Blob, S3) |

### Temporal Tables (System-Versioned)
```sql
-- Automatic history tracking
CREATE TABLE employee (
    employee_id INT PRIMARY KEY,
    name NVARCHAR(100) NOT NULL,
    salary DECIMAL(10,2) NOT NULL,
    department_id INT NOT NULL,
    valid_from DATETIME2 GENERATED ALWAYS AS ROW START NOT NULL,
    valid_to DATETIME2 GENERATED ALWAYS AS ROW END NOT NULL,
    PERIOD FOR SYSTEM_TIME (valid_from, valid_to)
) WITH (SYSTEM_VERSIONING = ON (HISTORY_TABLE = dbo.employee_history));

-- Query historical data
SELECT * FROM employee FOR SYSTEM_TIME AS OF '2025-01-15T10:00:00';
SELECT * FROM employee FOR SYSTEM_TIME BETWEEN '2025-01-01' AND '2025-06-30';
```

---

## SQLite

### When to Use
- Embedded applications, mobile apps, desktop software
- Single-writer workloads (one write at a time)
- Databases < 1TB (practical limit ~281 TB)
- Development/testing environments
- Edge computing, IoT devices

### When NOT to Use
- High-concurrency write workloads
- Client-server architecture with multiple writers
- Databases > 1TB with complex queries
- Need for replication or distributed access

### Key Pragmas
```sql
PRAGMA journal_mode = WAL;          -- Write-Ahead Log (concurrent reads + writes)
PRAGMA synchronous = NORMAL;        -- Good durability with WAL
PRAGMA cache_size = -64000;         -- 64MB cache (negative = KB)
PRAGMA mmap_size = 268435456;       -- 256MB memory-mapped I/O
PRAGMA foreign_keys = ON;           -- Enable FK enforcement (OFF by default!)
PRAGMA busy_timeout = 5000;         -- 5s retry on lock
PRAGMA auto_vacuum = INCREMENTAL;   -- Reclaim space incrementally
```
