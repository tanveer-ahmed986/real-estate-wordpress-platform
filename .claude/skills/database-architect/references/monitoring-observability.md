# Monitoring & Observability Reference

Database metrics, slow query detection, alerting, and performance baselines.

---

## Key Metrics to Monitor

### Connection Metrics
| Metric | Warning | Critical | Action |
|--------|---------|----------|--------|
| Active connections / max | > 70% | > 90% | Scale pool, check for leaks |
| Idle connections | > 50% of pool | > 80% | Reduce pool size, add timeout |
| Connection wait time | > 100ms | > 1s | Increase pool, optimize queries |
| Failed connections | Any | Sustained | Check auth, network, max_connections |

### Query Performance Metrics
| Metric | Warning | Critical | Action |
|--------|---------|----------|--------|
| p95 query latency | > 100ms | > 500ms | EXPLAIN, add indexes, optimize |
| p99 query latency | > 500ms | > 2s | Identify and fix outlier queries |
| Queries per second | Unusual spike/drop | 2x normal or near-zero | Investigate application behavior |
| Slow query count | Increasing trend | > 5% of total | Review slow query log |

### Resource Metrics
| Metric | Warning | Critical | Action |
|--------|---------|----------|--------|
| CPU utilization | > 70% sustained | > 90% | Optimize queries, vertical scale |
| Memory usage | > 80% | > 95% | Tune buffer pool, check for leaks |
| Disk I/O (IOPS) | > 70% capacity | > 90% | Add indexes, upgrade storage |
| Disk space | < 30% free | < 10% free | Archive, cleanup, add storage |
| Buffer/cache hit ratio | < 99% | < 95% | Increase buffer pool size |

### Replication Metrics
| Metric | Warning | Critical | Action |
|--------|---------|----------|--------|
| Replication lag | > 1s | > 10s | Check network, replica resources |
| WAL/binlog growth | Unusual spike | Disk filling | Check long transactions, archiving |
| Replication status | Any interruption | Replica stopped | Restart replication, check logs |

---

## Slow Query Detection

### PostgreSQL
```sql
-- Enable pg_stat_statements extension
CREATE EXTENSION IF NOT EXISTS pg_stat_statements;

-- Find slowest queries by total time
SELECT
    calls,
    round(total_exec_time::numeric, 2) AS total_ms,
    round(mean_exec_time::numeric, 2) AS mean_ms,
    round((100 * total_exec_time / sum(total_exec_time) OVER ())::numeric, 2) AS percent_total,
    left(query, 100) AS query_preview
FROM pg_stat_statements
ORDER BY total_exec_time DESC
LIMIT 20;

-- Find queries with high I/O
SELECT
    calls,
    shared_blks_read + shared_blks_hit AS total_blocks,
    round(shared_blks_hit::numeric / NULLIF(shared_blks_read + shared_blks_hit, 0) * 100, 2) AS cache_hit_pct,
    left(query, 100) AS query_preview
FROM pg_stat_statements
ORDER BY shared_blks_read DESC
LIMIT 20;

-- Log slow queries: postgresql.conf
-- log_min_duration_statement = 500   # ms, log queries slower than this
-- log_statement = 'none'             # Don't log all statements
```

### MySQL
```sql
-- Enable slow query log
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 0.5;  -- seconds
SET GLOBAL log_queries_not_using_indexes = 'ON';

-- Performance Schema: top queries by total latency
SELECT
    DIGEST_TEXT AS query,
    COUNT_STAR AS calls,
    ROUND(SUM_TIMER_WAIT / 1e12, 2) AS total_sec,
    ROUND(AVG_TIMER_WAIT / 1e12, 4) AS avg_sec,
    SUM_ROWS_EXAMINED AS rows_examined
FROM performance_schema.events_statements_summary_by_digest
ORDER BY SUM_TIMER_WAIT DESC
LIMIT 20;
```

### SQL Server
```sql
-- Query Store (SQL Server 2016+)
SELECT TOP 20
    qt.query_sql_text,
    rs.count_executions,
    rs.avg_duration / 1000.0 AS avg_duration_ms,
    rs.avg_logical_io_reads,
    rs.avg_cpu_time / 1000.0 AS avg_cpu_ms
FROM sys.query_store_query_text qt
JOIN sys.query_store_query q ON qt.query_text_id = q.query_text_id
JOIN sys.query_store_plan p ON q.query_id = p.query_id
JOIN sys.query_store_runtime_stats rs ON p.plan_id = rs.plan_id
ORDER BY rs.avg_duration DESC;

-- Enable Query Store
ALTER DATABASE MyDB SET QUERY_STORE = ON;
ALTER DATABASE MyDB SET QUERY_STORE (
    OPERATION_MODE = READ_WRITE,
    DATA_FLUSH_INTERVAL_SECONDS = 900,
    INTERVAL_LENGTH_MINUTES = 60,
    MAX_STORAGE_SIZE_MB = 1000
);
```

---

## Database Health Checks

### PostgreSQL Health Query
```sql
-- Table bloat estimation
SELECT
    schemaname || '.' || tablename AS table_name,
    pg_size_pretty(pg_total_relation_size(schemaname || '.' || tablename)) AS total_size,
    n_dead_tup AS dead_tuples,
    n_live_tup AS live_tuples,
    ROUND(n_dead_tup::numeric / NULLIF(n_live_tup + n_dead_tup, 0) * 100, 2) AS dead_pct,
    last_autovacuum
FROM pg_stat_user_tables
ORDER BY n_dead_tup DESC
LIMIT 20;

-- Index usage statistics
SELECT
    schemaname || '.' || tablename AS table_name,
    indexrelname AS index_name,
    idx_scan AS times_used,
    pg_size_pretty(pg_relation_size(indexrelid)) AS index_size
FROM pg_stat_user_indexes
ORDER BY idx_scan ASC
LIMIT 20;  -- Least-used indexes (candidates for removal)

-- Cache hit ratio
SELECT
    sum(blks_hit) / NULLIF(sum(blks_hit) + sum(blks_read), 0) AS cache_hit_ratio
FROM pg_stat_database;
-- Target: > 0.99 (99%)
```

---

## Alerting Rules

### Critical Alerts (Page On-Call)
| Condition | Threshold | Action |
|-----------|-----------|--------|
| Database unreachable | Any | Check server, failover |
| Replication broken | Replica stopped | Restart replication |
| Disk space critical | < 5% free | Emergency cleanup, add storage |
| Connection exhaustion | > 95% max_connections | Kill idle, scale pool |
| Corruption detected | Any checksum failure | Stop writes, restore from backup |

### Warning Alerts (Review Next Business Day)
| Condition | Threshold | Action |
|-----------|-----------|--------|
| Slow query spike | 2x baseline | Review query log |
| Replication lag | > 5s sustained | Check replica resources |
| Cache hit ratio drop | < 98% | Tune buffer pool |
| Dead tuple buildup | > 20% of table | Run VACUUM |
| Backup failure | Any | Investigate, re-run |

---

## Performance Baseline Process

### Establish Baseline
1. Monitor for 2-4 weeks during normal operation
2. Record: query latency (p50, p95, p99), QPS, connections, CPU, memory, IOPS
3. Document peak hours and seasonal patterns
4. Store baseline metrics for comparison

### Compare Against Baseline
```
Current metric vs Baseline:
  < 80% of baseline → Investigate (possible data loss, app issue)
  80-120% of baseline → Normal
  > 120% of baseline → Investigate (traffic spike, inefficient query)
  > 200% of baseline → Alert (significant deviation)
```

### Regular Review Cadence
| Review | Frequency | Focus |
|--------|-----------|-------|
| Slow query review | Weekly | Top 10 slowest, new slow queries |
| Index usage review | Monthly | Unused indexes, missing indexes |
| Storage growth | Monthly | Projection, archival needs |
| Full performance audit | Quarterly | Baselines, capacity planning |
