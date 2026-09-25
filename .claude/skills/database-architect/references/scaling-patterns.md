# Scaling Patterns Reference

Database scaling strategies from vertical scaling to distributed architectures.

---

## Scaling Decision Tree

```
Performance problem identified
  │
  ├── Read bottleneck?
  │   ├── Add indexes → references/performance-optimization.md
  │   ├── Add read replicas
  │   ├── Add caching layer (Redis/Memcached)
  │   └── Materialized views for complex aggregations
  │
  ├── Write bottleneck?
  │   ├── Vertical scaling (bigger machine)
  │   ├── Table partitioning
  │   ├── Sharding
  │   └── Batch/async writes
  │
  ├── Storage bottleneck?
  │   ├── Data archival / tiered storage
  │   ├── Compression
  │   └── Partitioning with partition pruning
  │
  └── Connection bottleneck?
      ├── Connection pooling (PgBouncer, ProxySQL)
      └── Connection multiplexing
```

---

## Vertical Scaling

Increase resources on a single server. Always try this first.

| Resource | When to Scale | Target |
|----------|---------------|--------|
| RAM | Buffer pool hit ratio < 99% | shared_buffers = 25% RAM (PG), innodb_buffer_pool_size = 70-80% RAM (MySQL) |
| CPU | CPU > 80% sustained | More cores, faster clock |
| Storage | IOPS saturated | NVMe SSD, provisioned IOPS |
| Network | Bandwidth saturated | 10Gbps+, dedicated network |

**Limits**: Single-server vertical scaling typically maxes out at ~128 cores, 2TB RAM, 100K IOPS.

---

## Read Replicas

### PostgreSQL Streaming Replication
```
# primary: postgresql.conf
wal_level = replica
max_wal_senders = 10
wal_keep_size = 1GB

# replica: recovery.conf / standby.signal
primary_conninfo = 'host=primary_host port=5432 user=replicator password=xxx'
```

### MySQL Replication
```sql
-- Primary
CREATE USER 'replicator'@'replica_host' IDENTIFIED BY 'password';
GRANT REPLICATION SLAVE ON *.* TO 'replicator'@'replica_host';

-- Replica
CHANGE REPLICATION SOURCE TO
    SOURCE_HOST='primary_host',
    SOURCE_USER='replicator',
    SOURCE_PASSWORD='password',
    SOURCE_AUTO_POSITION=1;  -- GTID-based
START REPLICA;
```

### SQL Server Always On Availability Groups
```sql
-- Primary
CREATE AVAILABILITY GROUP MyAG
    WITH (AUTOMATED_BACKUP_PREFERENCE = SECONDARY)
    FOR DATABASE MyDB
    REPLICA ON
        'Primary' WITH (ENDPOINT_URL = 'TCP://primary:5022', AVAILABILITY_MODE = SYNCHRONOUS_COMMIT),
        'Secondary' WITH (ENDPOINT_URL = 'TCP://secondary:5022', AVAILABILITY_MODE = ASYNCHRONOUS_COMMIT,
                         ALLOW_CONNECTIONS = READ_ONLY);
```

### Application-Level Read/Write Splitting
```
                  ┌──────────────┐
                  │  Application │
                  └──────┬───────┘
                         │
              ┌──────────┴──────────┐
              │                     │
     ┌────────▼────────┐   ┌───────▼────────┐
     │  Primary (R/W)  │   │  Replica (RO)  │
     └─────────────────┘   └────────────────┘
     Writes + critical      Reports, search,
     reads                  analytics
```

**Replication Lag Considerations**:
- Synchronous: Zero lag but reduces write throughput
- Asynchronous: Milliseconds to seconds of lag; stale reads possible
- Semi-synchronous: At least one replica confirms before commit

---

## Partitioning

### Range Partitioning (Most Common)
```sql
-- PostgreSQL declarative partitioning
CREATE TABLE event_log (
    event_id BIGINT GENERATED ALWAYS AS IDENTITY,
    event_type VARCHAR(50) NOT NULL,
    payload JSONB,
    created_at TIMESTAMPTZ NOT NULL
) PARTITION BY RANGE (created_at);

CREATE TABLE event_log_2025_q1 PARTITION OF event_log
    FOR VALUES FROM ('2025-01-01') TO ('2025-04-01');
CREATE TABLE event_log_2025_q2 PARTITION OF event_log
    FOR VALUES FROM ('2025-04-01') TO ('2025-07-01');

-- Automatic partition creation: use pg_partman extension
```

### List Partitioning
```sql
CREATE TABLE customer_data (
    customer_id BIGINT,
    region VARCHAR(20) NOT NULL,
    data JSONB
) PARTITION BY LIST (region);

CREATE TABLE customer_data_us PARTITION OF customer_data FOR VALUES IN ('us-east', 'us-west');
CREATE TABLE customer_data_eu PARTITION OF customer_data FOR VALUES IN ('eu-west', 'eu-central');
CREATE TABLE customer_data_ap PARTITION OF customer_data FOR VALUES IN ('ap-south', 'ap-east');
```

### Hash Partitioning
```sql
-- Distribute evenly across N partitions
CREATE TABLE session_data (
    session_id UUID NOT NULL,
    user_id BIGINT NOT NULL,
    data JSONB
) PARTITION BY HASH (user_id);

CREATE TABLE session_data_p0 PARTITION OF session_data FOR VALUES WITH (MODULUS 4, REMAINDER 0);
CREATE TABLE session_data_p1 PARTITION OF session_data FOR VALUES WITH (MODULUS 4, REMAINDER 1);
CREATE TABLE session_data_p2 PARTITION OF session_data FOR VALUES WITH (MODULUS 4, REMAINDER 2);
CREATE TABLE session_data_p3 PARTITION OF session_data FOR VALUES WITH (MODULUS 4, REMAINDER 3);
```

### When to Partition

| Indicator | Threshold |
|-----------|-----------|
| Table size | > 10-50 GB or > 100M rows |
| Query patterns | Most queries filter on partition key |
| Maintenance | Need to drop/archive old data efficiently |
| Index size | Indexes exceed available RAM |

**Partition Pruning**: Ensure queries include the partition key in WHERE clause so the planner can skip irrelevant partitions.

---

## Sharding

### Shard Key Selection
| Criteria | Good Key | Bad Key |
|----------|----------|---------|
| Even distribution | user_id (hash) | created_at (hotspot on recent shard) |
| Query locality | tenant_id (queries scoped to tenant) | random UUID (queries span all shards) |
| Growth handling | Sequential with rebalancing | Static ranges |

### Sharding Strategies
```
Application-Level Sharding:
  App → Shard Router → Shard 1 (users 1-1M)
                     → Shard 2 (users 1M-2M)
                     → Shard 3 (users 2M-3M)

Proxy-Level Sharding:
  App → Proxy (Vitess, Citus, ProxySQL) → Shards

Database-Native Sharding:
  App → Citus (PG) / MySQL Cluster / CockroachDB / TiDB
```

### Cross-Shard Query Handling
- Avoid cross-shard JOINs (design schema to keep related data on same shard)
- For aggregations across shards: scatter-gather pattern or materialized views
- For cross-shard transactions: use distributed transactions (2PC) or eventual consistency

---

## Connection Pooling

### PgBouncer Configuration
```ini
[databases]
mydb = host=localhost port=5432 dbname=mydb

[pgbouncer]
listen_addr = 0.0.0.0
listen_port = 6432
auth_type = scram-sha-256
auth_file = /etc/pgbouncer/userlist.txt

pool_mode = transaction           # Best for web apps
max_client_conn = 2000            # Max app connections
default_pool_size = 25            # Connections per user/database pair
min_pool_size = 5                 # Keep warm connections
reserve_pool_size = 5             # Emergency overflow
reserve_pool_timeout = 3          # Seconds before using reserve
server_idle_timeout = 300         # Close idle backend connections
```

### Pool Sizing Formula
```
Optimal connections = (CPU cores * 2) + effective_disk_spindles

Example: 8-core server with NVMe SSD
  → 8 * 2 + 4 = 20 connections
  → Set pool_size = 20, allow slight buffer
```

**Key rule**: More connections ≠ more throughput. Beyond the optimal point, context switching and lock contention degrade performance.

---

## Caching Architecture

```
Application
  │
  ├── L1: Application-Level Cache (in-process, ms latency)
  │   └── Small, hot data: config, feature flags, session
  │
  ├── L2: Distributed Cache (Redis/Memcached, <5ms latency)
  │   └── Query results, computed aggregates, user profiles
  │
  └── L3: Database (disk, 1-100ms latency)
      └── Source of truth, complex queries, transactions
```

### Cache Invalidation Strategies
| Strategy | When to Use | Trade-off |
|----------|-------------|-----------|
| Cache-aside (lazy) | General purpose | Stale reads on first miss after update |
| Write-through | Strong consistency needed | Higher write latency |
| Write-behind | Write-heavy, eventual consistency OK | Risk of data loss |
| TTL-based | Acceptable staleness window | Simple but imprecise |
| Event-driven (CDC) | Real-time consistency | Infrastructure complexity |
