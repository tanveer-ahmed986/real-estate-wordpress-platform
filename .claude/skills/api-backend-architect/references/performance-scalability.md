# Performance & Scalability

Caching strategies, optimization techniques, scaling patterns, and load balancing.

---

## Caching Strategies

### Cache Decision Tree

```
Is data frequently read and rarely changed?
├── Yes → Is it user-specific?
│   ├── Yes → Application cache (Redis) with user-scoped keys
│   └── No → Is it publicly accessible?
│       ├── Yes → CDN + HTTP cache headers
│       └── No → Application cache (Redis/Memcached)
├── Somewhat → Cache with short TTL + invalidation strategy
└── No (frequently changing) → Don't cache, optimize query instead
```

### HTTP Caching

```typescript
// Cache-Control headers
// Immutable static assets (hashed filenames)
res.setHeader('Cache-Control', 'public, max-age=31536000, immutable');

// API responses that change infrequently
res.setHeader('Cache-Control', 'public, max-age=300, s-maxage=600');

// Private user data
res.setHeader('Cache-Control', 'private, max-age=60, must-revalidate');

// Never cache
res.setHeader('Cache-Control', 'no-store');

// ETags for conditional requests
app.get('/api/products/:id', async (req, res) => {
  const product = await db.products.findById(req.params.id);
  const etag = generateETag(product);

  if (req.headers['if-none-match'] === etag) {
    return res.status(304).end();
  }

  res.setHeader('ETag', etag);
  res.json(product);
});
```

### Application Cache (Redis)

```typescript
// Cache-aside pattern
async function getUserProfile(userId) {
  const cacheKey = `user:profile:${userId}`;

  // 1. Check cache
  const cached = await redis.get(cacheKey);
  if (cached) return JSON.parse(cached);

  // 2. Cache miss: fetch from DB
  const user = await db.users.findById(userId);

  // 3. Populate cache
  await redis.setex(cacheKey, 300, JSON.stringify(user)); // 5 min TTL

  return user;
}

// Cache invalidation on write
async function updateUserProfile(userId, data) {
  await db.users.update(userId, data);
  await redis.del(`user:profile:${userId}`); // Invalidate
}
```

### Caching Patterns

| Pattern | Description | Use When |
|---------|-------------|----------|
| Cache-Aside | App manages cache reads/writes | General purpose, most common |
| Write-Through | Write to cache and DB simultaneously | Consistency critical |
| Write-Behind | Write to cache, async to DB | High write throughput needed |
| Read-Through | Cache auto-fetches on miss | Simplify application logic |
| Refresh-Ahead | Proactively refresh before expiry | Predictable access patterns |

### Cache Invalidation Strategies

| Strategy | Description | Consistency |
|----------|-------------|-------------|
| TTL-based | Auto-expire after time | Eventual (up to TTL) |
| Event-based | Invalidate on write events | Near real-time |
| Version-based | Cache key includes version | Immediate |
| Tag-based | Group related entries, bulk invalidate | Flexible |

### Multi-Level Caching

```
Request → L1: In-process cache (ms) → L2: Redis (1-5ms) → L3: Database (10-100ms)
                                    → L3: CDN (for static/semi-static)
```

---

## Database Performance

### Connection Pooling

```typescript
// PostgreSQL with pool
import { Pool } from 'pg';

const pool = new Pool({
  host: process.env.DB_HOST,
  port: 5432,
  database: process.env.DB_NAME,
  user: process.env.DB_USER,
  password: process.env.DB_PASSWORD,
  min: 5,                    // Minimum connections
  max: 20,                   // Maximum connections
  idleTimeoutMillis: 30000,  // Close idle connections after 30s
  connectionTimeoutMillis: 5000, // Fail if can't connect in 5s
  statement_timeout: 30000,  // Kill queries running > 30s
});

// Monitor pool health
pool.on('error', (err) => {
  logger.error('Database pool error', err);
});
```

### Query Optimization Checklist

| Check | Action |
|-------|--------|
| Missing indexes | Add indexes on WHERE, JOIN, ORDER BY columns |
| N+1 queries | Use eager loading / JOINs / DataLoader |
| SELECT * | Select only needed columns |
| Large result sets | Paginate, add LIMIT |
| Unoptimized JOINs | Ensure proper indexes on join columns |
| Sequential queries | Parallelize independent queries |
| Expensive aggregations | Use materialized views or pre-compute |
| Full table scans | Add appropriate indexes |

### Read Replicas Pattern

```
Write operations → Primary DB
Read operations  → Read Replica(s)

Implementation:
- Route write queries to primary
- Route read queries to replica(s)
- Handle replication lag (eventual consistency)
- Use primary for reads-after-writes when consistency needed
```

---

## API Response Optimization

### Compression

```typescript
import compression from 'compression';

app.use(compression({
  filter: (req, res) => {
    if (req.headers['x-no-compression']) return false;
    return compression.filter(req, res);
  },
  threshold: 1024,  // Only compress responses > 1KB
  level: 6,         // Compression level (1-9, 6 is balanced)
}));
```

### Response Optimization

```typescript
// Sparse fieldsets - return only requested fields
app.get('/api/users', (req, res) => {
  const fields = req.query.fields?.split(',') || defaultFields;
  const users = await db.users.findMany({ select: fields });
  res.json(users);
});

// Batch endpoints - reduce round trips
app.post('/api/batch', async (req, res) => {
  const { operations } = req.body; // Max 20 operations
  const results = await Promise.allSettled(
    operations.map(op => executeOperation(op))
  );
  res.json({ results });
});
```

---

## Scaling Patterns

### Horizontal Scaling

```
                    Load Balancer
                   /      |      \
              App-1    App-2    App-3
                 \       |       /
                  Shared State
               (Redis / Database)

Requirements:
- Stateless application servers
- Externalized sessions (Redis)
- Shared nothing architecture
- Health checks for load balancer
```

### Load Balancing Algorithms

| Algorithm | Description | Use When |
|-----------|-------------|----------|
| Round Robin | Distribute sequentially | Equal server capacity |
| Weighted Round Robin | Distribute by server weight | Unequal server capacity |
| Least Connections | Route to least busy server | Variable request processing time |
| IP Hash | Same client → same server | Session affinity needed |
| Random | Random server selection | Simple, good distribution at scale |

### Auto-Scaling

```yaml
# Scaling policies
scaling:
  min_instances: 2
  max_instances: 20
  target_metrics:
    cpu_utilization: 70%        # Scale at 70% CPU
    request_count: 1000         # Per instance per minute
    response_time_p95: 500ms    # P95 latency threshold
  scale_up:
    cooldown: 60s               # Wait between scale-ups
    increment: 2                # Add 2 instances at a time
  scale_down:
    cooldown: 300s              # Wait 5 min before scale-down
    decrement: 1                # Remove 1 at a time
```

### Database Scaling

| Pattern | Description | Use When |
|---------|-------------|----------|
| Read replicas | Multiple read copies | Read-heavy workloads (10:1 read/write) |
| Sharding | Partition data across DBs | Single DB can't hold all data |
| Connection pooling | Reuse DB connections | High concurrent connections |
| Vertical scaling | Bigger server | Quick fix, simpler ops |
| Write-ahead log | Async replication | Geographic distribution |

### Sharding Strategies

```
Hash-based:  shard = hash(tenant_id) % num_shards
Range-based: shard_1 = A-M, shard_2 = N-Z
Geo-based:   shard_us, shard_eu, shard_asia
Tenant-based: Large tenants get dedicated shards
```

---

## Rate Limiting Patterns

### Token Bucket Algorithm

```typescript
class TokenBucket {
  private tokens: number;
  private lastRefill: number;

  constructor(
    private capacity: number,     // Max tokens
    private refillRate: number,   // Tokens per second
  ) {
    this.tokens = capacity;
    this.lastRefill = Date.now();
  }

  consume(tokens = 1): boolean {
    this.refill();
    if (this.tokens >= tokens) {
      this.tokens -= tokens;
      return true;
    }
    return false;
  }

  private refill() {
    const now = Date.now();
    const elapsed = (now - this.lastRefill) / 1000;
    this.tokens = Math.min(this.capacity, this.tokens + elapsed * this.refillRate);
    this.lastRefill = now;
  }
}
```

### Sliding Window (Redis)

```typescript
async function slidingWindowRateLimit(key, limit, windowMs) {
  const now = Date.now();
  const windowStart = now - windowMs;

  const pipe = redis.multi();
  pipe.zremrangebyscore(key, 0, windowStart);  // Remove old entries
  pipe.zadd(key, now, `${now}:${crypto.randomUUID()}`);  // Add current
  pipe.zcard(key);  // Count in window
  pipe.pexpire(key, windowMs);  // Set TTL

  const results = await pipe.exec();
  const count = results[2][1];

  return {
    allowed: count <= limit,
    remaining: Math.max(0, limit - count),
    resetAt: new Date(now + windowMs),
  };
}
```

### Rate Limit Headers

```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 42
X-RateLimit-Reset: 1705313100
Retry-After: 30
```

---

## Background Processing

### Job Queue Pattern

```typescript
// Producer
await jobQueue.add('send-email', {
  to: user.email,
  template: 'welcome',
  data: { name: user.name },
}, {
  attempts: 3,
  backoff: { type: 'exponential', delay: 1000 },
  removeOnComplete: 100,  // Keep last 100 completed
  removeOnFail: 500,      // Keep last 500 failed
  priority: 1,            // Lower = higher priority
});

// Consumer
jobQueue.process('send-email', 5, async (job) => {
  await emailService.send(job.data);
  return { sent: true };
});

// Dead letter queue for failed jobs
jobQueue.on('failed', (job, err) => {
  if (job.attemptsMade >= job.opts.attempts) {
    deadLetterQueue.add('failed-email', {
      originalJob: job.data,
      error: err.message,
      failedAt: new Date(),
    });
  }
});
```

### Async Request Pattern

```
POST /api/reports/generate → 202 Accepted
  Response: { "job_id": "job_123", "status_url": "/api/jobs/job_123" }

GET /api/jobs/job_123 → 200 OK
  Response: { "status": "processing", "progress": 65 }

GET /api/jobs/job_123 → 200 OK
  Response: { "status": "completed", "result_url": "/api/reports/rpt_456" }
```
