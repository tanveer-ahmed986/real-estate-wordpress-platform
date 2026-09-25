# Event-Driven Automation Patterns

## Core Architecture

### Event-Driven Pipeline Components
```
Event Sources → Message Broker → Event Processors → Side Effects → State Store
     ↑              ↑                   ↑                              |
  Webhooks     Kafka/RabbitMQ    Workers/Functions                     |
  Schedules    Redis Streams     Workflow Engine                       |
  DB Changes   SQS/SNS          Serverless                           |
  API Calls                                                           ↓
                                                              Notifications/Actions
```

### Event Design Principles

#### Event Schema Standards
```json
{
  "event_id": "uuid-v4",
  "event_type": "order.payment.completed",
  "source": "payment-service",
  "timestamp": "2025-01-15T10:30:00Z",
  "correlation_id": "order-12345",
  "causation_id": "previous-event-uuid",
  "version": "1.0",
  "data": {
    "order_id": "12345",
    "amount": 150.00,
    "currency": "USD"
  },
  "metadata": {
    "user_id": "user-789",
    "tenant_id": "tenant-abc"
  }
}
```

#### Event Naming Convention
```
<domain>.<entity>.<action>

Examples:
  order.created
  order.payment.completed
  order.fulfillment.shipped
  customer.subscription.renewed
  invoice.approval.requested
  invoice.approval.escalated
```

---

## Message Broker Patterns

### Apache Kafka

#### Topic Design
```
# One topic per event type (recommended for high-volume)
orders.created
orders.updated
payments.completed
payments.failed

# Partitioning strategy
- Key by entity ID (order_id) for ordering guarantees
- Key by tenant_id for multi-tenant isolation
- Round-robin for maximum throughput (no ordering needed)
```

#### Consumer Group Pattern
```python
# Multiple consumers in a group for parallel processing
consumer = KafkaConsumer(
    'orders.created',
    group_id='order-processor',
    bootstrap_servers=['kafka:9092'],
    auto_offset_reset='earliest',
    enable_auto_commit=False,  # Manual commit for exactly-once
    value_deserializer=lambda m: json.loads(m.decode('utf-8'))
)

for message in consumer:
    try:
        process_order(message.value)
        consumer.commit()  # Commit only after successful processing
    except TransientError:
        # Don't commit - message will be redelivered
        log.warning(f"Transient error, will retry: {message.key}")
    except PermanentError:
        # Send to dead-letter topic
        produce_to_dlq(message)
        consumer.commit()
```

### RabbitMQ

#### Exchange Patterns
```
# Direct exchange: Route by exact routing key
exchange: business-events (type: topic)
  routing: order.created → queue: order-processor
  routing: order.*.failed → queue: failure-handler
  routing: invoice.# → queue: invoice-processor

# Dead-letter exchange pattern
queue: order-processor
  x-dead-letter-exchange: dlx.business-events
  x-dead-letter-routing-key: order.failed
  x-message-ttl: 300000  # 5 min before DLQ
```

### Redis Streams

#### Lightweight Event Pipeline
```python
# Producer
await redis.xadd('events:orders', {
    'event_type': 'order.created',
    'data': json.dumps(order_data),
    'correlation_id': correlation_id
})

# Consumer group
await redis.xgroup_create('events:orders', 'processors', mkstream=True)

# Consumer with acknowledgment
while True:
    messages = await redis.xreadgroup(
        'processors', 'worker-1',
        {'events:orders': '>'},
        count=10, block=5000
    )
    for stream, entries in messages:
        for msg_id, fields in entries:
            await process_event(fields)
            await redis.xack('events:orders', 'processors', msg_id)
```

---

## Event Processing Patterns

### Event Sourcing
```
Events are the source of truth. Current state derived by replaying events.

OrderCreated → PaymentReceived → ItemsPicked → Shipped → Delivered
     ↓               ↓               ↓           ↓          ↓
  State: new    State: paid    State: picking  State: shipped  State: delivered
```

### Choreography vs Orchestration

#### Choreography (Decentralized)
```
Each service reacts to events independently:
  OrderService publishes "order.created"
  PaymentService listens → charges payment → publishes "payment.completed"
  InventoryService listens → reserves stock → publishes "stock.reserved"
  ShippingService listens → creates shipment → publishes "shipment.created"

Pros: Loose coupling, independent deployment
Cons: Hard to track overall flow, distributed debugging
Best for: Simple flows, few steps, independent services
```

#### Orchestration (Centralized)
```
Central coordinator manages the flow:
  OrderWorkflow:
    1. Call PaymentService.charge()
    2. Call InventoryService.reserve()
    3. Call ShippingService.ship()
    4. Handle failures with compensation

Pros: Clear flow visibility, easier debugging, compensation logic
Cons: Central point of coordination, tighter coupling
Best for: Complex flows, many steps, compensation needed
```

### CQRS (Command Query Responsibility Segregation)
```
Commands (writes) → Event Store → Projections → Read Models (queries)

- Separate write and read models for different optimization
- Write model: normalized, consistent
- Read model: denormalized, fast queries
- Event store bridges both sides
```

---

## Durable Execution + Event-Driven

### Kafka + Temporal Integration
```
Kafka provides event backbone → Temporal manages workflow state

1. Kafka consumer receives "order.created" event
2. Starts Temporal workflow for order processing
3. Temporal handles retries, timeouts, human approval
4. Temporal activities publish events back to Kafka
5. Other services react to downstream events
```

### Pattern: Event-Triggered Durable Workflow
```python
# Kafka consumer starts Temporal workflow
async def consume_events():
    for message in kafka_consumer:
        event = deserialize(message.value)
        if event.type == "order.created":
            await temporal_client.start_workflow(
                OrderProcessingWorkflow.run,
                event.data,
                id=f"order-{event.data.order_id}",
                task_queue="order-processing",
                id_reuse_policy=WorkflowIDReusePolicy.REJECT_DUPLICATE
            )
            kafka_consumer.commit()
```

---

## Scaling Patterns

### Partitioned Processing
```
High-volume events partitioned across workers:
  Topic: orders (12 partitions)
  Consumer Group: order-processors (4 instances)
  Each instance handles 3 partitions

Scaling rule: partitions >= max expected consumers
```

### Backpressure Handling
```
1. Rate limiting at producer level
2. Consumer lag monitoring with alerts
3. Auto-scaling consumers based on lag
4. Circuit breaker on downstream services
5. Overflow to batch processing during peaks
```

### Multi-Region Event Replication
```
Region A (primary) → Kafka MirrorMaker → Region B (replica)
  - Active-passive for DR
  - Active-active for geographic distribution
  - Conflict resolution strategy required for active-active
```
