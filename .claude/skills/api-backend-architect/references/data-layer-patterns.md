# Data Layer Patterns

Database design, ORM patterns, migrations, and message queue integration.

---

## Database Selection Guide

| Database | Best For | Avoid When |
|----------|----------|------------|
| **PostgreSQL** | Relational data, ACID, complex queries, JSON support | Simple key-value needs |
| **MySQL** | Web apps, read-heavy workloads, mature ecosystem | Complex queries with CTEs |
| **MongoDB** | Flexible schemas, document storage, rapid prototyping | Strong relationships, transactions critical |
| **Redis** | Caching, sessions, rate limiting, pub/sub, queues | Primary data storage, complex queries |
| **Elasticsearch** | Full-text search, log analytics, time-series | Primary database, ACID transactions |
| **DynamoDB** | Key-value at scale, serverless, predictable perf | Complex queries, joins needed |
| **SQLite** | Embedded apps, desktop software, development | Multi-user concurrent writes |
| **ClickHouse** | Analytics, OLAP, time-series aggregations | OLTP, frequent updates |

---

## Schema Design Principles

### Relational Database

```sql
-- Use UUIDs for public-facing IDs
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- Base table pattern with audit fields
CREATE TABLE users (
  id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  display_name VARCHAR(100) NOT NULL,
  role VARCHAR(20) NOT NULL DEFAULT 'user'
    CHECK (role IN ('admin', 'manager', 'user', 'viewer')),
  is_active BOOLEAN NOT NULL DEFAULT true,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  deleted_at TIMESTAMPTZ  -- Soft delete
);

-- Indexes for common queries
CREATE INDEX idx_users_email ON users (email);
CREATE INDEX idx_users_role ON users (role) WHERE is_active = true;
CREATE INDEX idx_users_created ON users (created_at DESC);

-- Updated_at trigger
CREATE OR REPLACE FUNCTION update_updated_at()
RETURNS TRIGGER AS $$
BEGIN
  NEW.updated_at = NOW();
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER users_updated_at
  BEFORE UPDATE ON users
  FOR EACH ROW EXECUTE FUNCTION update_updated_at();

-- Foreign keys with appropriate actions
CREATE TABLE orders (
  id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  user_id UUID NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
  status VARCHAR(20) NOT NULL DEFAULT 'pending'
    CHECK (status IN ('pending', 'confirmed', 'shipped', 'delivered', 'cancelled')),
  total_amount DECIMAL(12, 2) NOT NULL CHECK (total_amount >= 0),
  currency VARCHAR(3) NOT NULL DEFAULT 'USD',
  metadata JSONB DEFAULT '{}',
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_orders_user_id ON orders (user_id);
CREATE INDEX idx_orders_status ON orders (status);
CREATE INDEX idx_orders_created ON orders (created_at DESC);
CREATE INDEX idx_orders_metadata ON orders USING GIN (metadata);
```

### Naming Conventions

| Element | Convention | Example |
|---------|-----------|---------|
| Tables | Plural, snake_case | `order_items`, `user_profiles` |
| Columns | snake_case | `created_at`, `display_name` |
| Primary keys | `id` | `id UUID` |
| Foreign keys | `{table_singular}_id` | `user_id`, `order_id` |
| Indexes | `idx_{table}_{columns}` | `idx_orders_user_id` |
| Constraints | `chk_{table}_{column}` | `chk_orders_status` |
| Triggers | `{table}_{action}` | `orders_updated_at` |

---

## ORM & Query Builder Patterns

### Repository Pattern

```typescript
// Repository interface
interface UserRepository {
  findById(id: string): Promise<User | null>;
  findByEmail(email: string): Promise<User | null>;
  findMany(filters: UserFilters, pagination: Pagination): Promise<PaginatedResult<User>>;
  create(data: CreateUserInput): Promise<User>;
  update(id: string, data: UpdateUserInput): Promise<User>;
  softDelete(id: string): Promise<void>;
}

// Implementation (Prisma example)
class PrismaUserRepository implements UserRepository {
  constructor(private db: PrismaClient) {}

  async findById(id: string): Promise<User | null> {
    return this.db.user.findUnique({
      where: { id, deletedAt: null },
    });
  }

  async findMany(
    filters: UserFilters,
    pagination: Pagination
  ): Promise<PaginatedResult<User>> {
    const where = {
      deletedAt: null,
      ...(filters.role && { role: filters.role }),
      ...(filters.isActive !== undefined && { isActive: filters.isActive }),
      ...(filters.search && {
        OR: [
          { email: { contains: filters.search, mode: 'insensitive' } },
          { displayName: { contains: filters.search, mode: 'insensitive' } },
        ],
      }),
    };

    const [items, total] = await Promise.all([
      this.db.user.findMany({
        where,
        take: pagination.limit,
        skip: pagination.offset,
        orderBy: { [pagination.sortBy]: pagination.sortDir },
      }),
      this.db.user.count({ where }),
    ]);

    return { items, total, ...pagination };
  }

  async softDelete(id: string): Promise<void> {
    await this.db.user.update({
      where: { id },
      data: { deletedAt: new Date() },
    });
  }
}
```

### Unit of Work Pattern

```typescript
// Transaction management
async function createOrderWithItems(orderData, items) {
  return db.$transaction(async (tx) => {
    // 1. Create order
    const order = await tx.order.create({
      data: { ...orderData, status: 'pending' },
    });

    // 2. Create order items
    await tx.orderItem.createMany({
      data: items.map(item => ({
        orderId: order.id,
        ...item,
      })),
    });

    // 3. Update inventory
    for (const item of items) {
      const result = await tx.product.update({
        where: { id: item.productId },
        data: { stock: { decrement: item.quantity } },
      });
      if (result.stock < 0) {
        throw new Error(`Insufficient stock for product ${item.productId}`);
      }
    }

    return order;
  });
}
```

---

## Migration Strategy

### Migration Best Practices

| Practice | Guidance |
|----------|----------|
| Version control | All migrations in version control |
| Idempotent | Migrations can be safely re-run |
| Reversible | Include rollback (down) migration |
| Small | One change per migration |
| Non-breaking | Support rolling deployments |
| Tested | Test on staging with production-like data |

### Zero-Downtime Migration Pattern

```
# Adding a column (safe)
1. Add nullable column (no default)
2. Deploy app that writes to new column
3. Backfill existing rows
4. Add NOT NULL constraint

# Renaming a column (safe)
1. Add new column
2. Deploy app that writes to both columns
3. Backfill new column from old
4. Deploy app that reads from new column only
5. Drop old column

# Adding an index (safe)
CREATE INDEX CONCURRENTLY idx_name ON table (column);
-- CONCURRENTLY prevents locking the table

# NEVER in production
- DROP COLUMN without verifying no code references it
- ALTER COLUMN TYPE on large tables without planning
- Adding NOT NULL without default on existing table
```

---

## Message Queue Integration

### When to Use Message Queues

| Use Case | Pattern |
|----------|---------|
| Async processing | Fire-and-forget (email, notifications) |
| Decoupling services | Producer doesn't know about consumers |
| Load leveling | Buffer spikes in traffic |
| Retry logic | Automatic retry with dead letter queue |
| Fan-out | One event, multiple consumers |
| Ordered processing | FIFO queues, partitioned streams |

### Producer Pattern

```typescript
// Reliable message publishing
async function publishEvent(event) {
  // 1. Store event in outbox table (same transaction as business logic)
  await db.$transaction(async (tx) => {
    await tx.order.update({ where: { id: event.orderId }, data: { status: 'confirmed' } });
    await tx.outbox.create({
      data: {
        eventType: event.type,
        payload: JSON.stringify(event),
        status: 'pending',
      },
    });
  });

  // 2. Outbox processor publishes to queue (separate process)
  // This ensures at-least-once delivery even if queue is down
}
```

### Consumer Pattern

```typescript
// Idempotent consumer
async function handleOrderPlaced(message) {
  const eventId = message.id;

  // Check if already processed (idempotency)
  const processed = await db.processedEvents.findUnique({
    where: { eventId },
  });
  if (processed) {
    message.ack(); // Already handled
    return;
  }

  try {
    await db.$transaction(async (tx) => {
      // Process the event
      await processOrder(tx, message.data);

      // Mark as processed
      await tx.processedEvents.create({
        data: { eventId, processedAt: new Date() },
      });
    });

    message.ack();
  } catch (error) {
    // Nack with requeue for transient errors
    if (isTransient(error)) {
      message.nack(true); // requeue
    } else {
      message.nack(false); // send to dead letter queue
      logger.error('Non-transient error', { eventId, error });
    }
  }
}
```

### Transactional Outbox Pattern

```
┌──────────────────────────────────────┐
│           Single Transaction         │
│  1. Update business table            │
│  2. Insert event into outbox table   │
└──────────────────────────────────────┘
                │
    Outbox Processor (polling/CDC)
                │
                ▼
         Message Broker
         (Kafka, RabbitMQ)
                │
                ▼
           Consumers
```

---

## Data Access Anti-Patterns

| Anti-Pattern | Problem | Fix |
|-------------|---------|-----|
| N+1 queries | Fetching related data in loop | Eager loading, DataLoader, JOINs |
| SELECT * | Fetching unnecessary columns | Select specific fields |
| No indexes | Slow queries, full table scans | Add indexes for query patterns |
| Fat transactions | Long locks, contention | Keep transactions short |
| No connection pooling | Connection exhaustion | Use pool with proper limits |
| Mixing business logic in queries | Hard to test, tightly coupled | Repository pattern |
| No soft deletes | Data loss, broken references | Add deleted_at column |
| Missing audit trail | No history of changes | Audit log table or event sourcing |
