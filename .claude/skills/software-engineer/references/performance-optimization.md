# Performance Optimization

## Frontend Performance

### Core Web Vitals (Google/web.dev)

| Metric | What It Measures | Good | Needs Improvement | Poor |
|--------|-----------------|------|-------------------|------|
| **LCP** (Largest Contentful Paint) | Loading performance | ≤2.5s | 2.5s-4.0s | >4.0s |
| **INP** (Interaction to Next Paint) | Responsiveness | ≤200ms | 200ms-500ms | >500ms |
| **CLS** (Cumulative Layout Shift) | Visual stability | ≤0.1 | 0.1-0.25 | >0.25 |

### LCP Optimization

```
Problem: Large images, render-blocking resources, slow server
Solutions:
├── Optimize images: WebP/AVIF format, responsive srcset, lazy load below-fold
├── Preload critical assets: <link rel="preload" as="image" href="hero.webp">
├── Remove render-blocking CSS/JS: Inline critical CSS, defer non-critical JS
├── Use CDN: Serve static assets from edge locations
├── SSR/SSG: Pre-render above-fold content on server
└── Font optimization: font-display: swap, preload key fonts
```

### INP Optimization

```
Problem: Long main thread tasks, heavy JavaScript execution
Solutions:
├── Break long tasks: Use requestIdleCallback, setTimeout(fn, 0)
├── Web Workers: Offload heavy computation to background threads
├── Virtual scrolling: Only render visible list items (react-window, vue-virtual-scroller)
├── Debounce/throttle: Limit event handler frequency
├── Reduce bundle size: Tree-shaking, code splitting, dynamic imports
└── Avoid forced synchronous layout: Batch DOM reads, then writes
```

### CLS Optimization

```
Problem: Layout shifts from dynamic content loading
Solutions:
├── Set dimensions: width/height on <img>, <video>, <iframe>
├── Aspect ratio boxes: aspect-ratio CSS property
├── Reserve space: Skeleton screens for async content
├── Font loading: font-display: optional or swap with size-adjust
├── Avoid dynamic injection: Don't insert content above existing content
└── CSS containment: contain: layout for independent components
```

### Bundle Optimization

| Technique | Tool | Impact |
|-----------|------|--------|
| Code Splitting | Dynamic import(), React.lazy() | Load only what's needed |
| Tree Shaking | Webpack/Vite (ES modules) | Remove dead code |
| Compression | Brotli (preferred) or Gzip | 60-80% size reduction |
| Minification | Terser (JS), cssnano (CSS) | 30-50% size reduction |
| Image Optimization | Sharp, Squoosh, next/image | 50-90% size reduction |
| Dependency Analysis | Bundle Analyzer, source-map-explorer | Identify bloated deps |

### Caching Strategy (Browser)

```
# Immutable assets (hashed filenames): cache forever
Cache-Control: public, max-age=31536000, immutable

# HTML pages: always revalidate
Cache-Control: no-cache

# API responses: short cache with revalidation
Cache-Control: private, max-age=60, stale-while-revalidate=300
```

---

## Backend Performance

### Database Query Optimization

```sql
-- ALWAYS: Use EXPLAIN ANALYZE to understand query plans
EXPLAIN ANALYZE SELECT * FROM orders WHERE user_id = 123;

-- Index columns used in WHERE, JOIN, ORDER BY
CREATE INDEX idx_orders_user_id ON orders(user_id);

-- Composite index for multi-column queries (column order matters)
CREATE INDEX idx_orders_user_status ON orders(user_id, status);

-- Covering index to avoid table lookups
CREATE INDEX idx_orders_covering ON orders(user_id, status) INCLUDE (total, created_at);
```

### N+1 Query Problem

```typescript
// BAD: N+1 queries (1 query for users + N queries for orders)
const users = await db.users.findAll();
for (const user of users) {
  user.orders = await db.orders.findByUserId(user.id); // N queries!
}

// GOOD: Eager loading (2 queries total)
const users = await db.users.findAll({
  include: [{ model: Order }],
});

// GOOD: Batch loading with DataLoader
const orderLoader = new DataLoader(async (userIds) => {
  const orders = await db.orders.findAll({ where: { userId: userIds } });
  return userIds.map(id => orders.filter(o => o.userId === id));
});
```

### Caching Layers

```
Request → CDN Cache → Application Cache → Database Cache → Database
                ↓              ↓                  ↓
           Static assets   Redis/Memcached   Query cache
           (minutes-days)  (seconds-minutes)  (seconds)
```

| Pattern | When | Example |
|---------|------|---------|
| Cache-Aside | Read-heavy, tolerance for stale data | User profiles, product catalog |
| Write-Through | Consistency critical | Inventory counts |
| Write-Behind | High write volume, eventual consistency OK | Analytics events |
| Read-Through | Simplify application code | ORM-level caching |

### Connection Pooling

```
Rule of thumb: pool_size = (cpu_cores * 2) + effective_spindle_count

Example: 4-core server with SSD
pool_size = (4 * 2) + 1 = 9 connections

Tools:
- PostgreSQL: pgBouncer (external), pg pool (built-in)
- MySQL: MySQL Router, ProxySQL
- Node.js: knex pool config, Prisma connection pool
- Python: SQLAlchemy pool, asyncpg pool
```

### Async Processing

Move expensive operations off the request path:

```
Synchronous (request path):
  Validate → Save to DB → Return response (<200ms)

Asynchronous (background):
  → Send email (queue)
  → Generate PDF (queue)
  → Process image (queue)
  → Update analytics (queue)
  → Send webhook notifications (queue)

Message Queues: BullMQ (Node.js), Celery (Python), RabbitMQ, AWS SQS
```

### API Response Optimization

| Technique | Implementation |
|-----------|----------------|
| Compression | Enable Brotli/Gzip for responses >1KB |
| Field Selection | Allow `?fields=id,name` to reduce payload |
| Pagination | Never return unbounded collections |
| ETags | Return 304 Not Modified for unchanged data |
| HTTP/2 | Multiplexing, header compression, server push |
| Connection Keep-Alive | Reuse TCP connections |

---

## Monitoring & Profiling

### Key Metrics to Track

| Metric | Target | Tool |
|--------|--------|------|
| Response Time (p50/p95/p99) | p95 <500ms | APM (Datadog, New Relic) |
| Error Rate | <0.1% | Error tracking (Sentry) |
| Throughput (RPS) | Depends on SLA | Load testing (k6, Artillery) |
| CPU/Memory Usage | <70% average | Infrastructure monitoring |
| Database Query Time | p95 <100ms | Slow query log |
| Cache Hit Ratio | >90% | Cache metrics |

### Load Testing

```bash
# k6 load test example
k6 run --vus 100 --duration 5m load-test.js

# Target: System handles expected peak traffic
# Test: 2x expected peak for safety margin
# Monitor: Response times, error rates, resource usage
```

---

## Sources
- web.dev: Core Web Vitals (https://web.dev/articles/vitals)
- Google PageSpeed Insights
- Use The Platform (Addy Osmani)
- High Performance Browser Networking (Ilya Grigorik)
