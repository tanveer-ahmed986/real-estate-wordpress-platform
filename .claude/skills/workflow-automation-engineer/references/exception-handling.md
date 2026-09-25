# Exception Handling & Resilience Patterns

## Exception Taxonomy

### Error Classification
```
Business Exceptions (expected, handled by business logic)
├── Validation errors (bad input, missing fields)
├── Policy violations (exceeded limit, unauthorized)
├── Business rule failures (insufficient funds, out of stock)
└── Timeout/deadline exceeded (SLA breach, approval stalled)

Technical Exceptions (infrastructure/system failures)
├── Transient (retry will fix)
│   ├── Network timeout
│   ├── Service temporarily unavailable (503)
│   ├── Rate limiting (429)
│   └── Database connection pool exhausted
├── Permanent (retry won't fix)
│   ├── Invalid configuration
│   ├── Schema mismatch
│   ├── Authentication failure (401)
│   └── Resource not found (404)
└── Catastrophic (system-level)
    ├── Out of memory
    ├── Disk full
    └── Data corruption
```

---

## Retry Strategies

### Exponential Backoff with Jitter
```python
import random

def retry_with_backoff(func, max_retries=5, base_delay=1.0, max_delay=60.0):
    for attempt in range(max_retries):
        try:
            return func()
        except TransientError as e:
            if attempt == max_retries - 1:
                raise MaxRetriesExceeded(e)

            # Exponential backoff with full jitter
            delay = min(base_delay * (2 ** attempt), max_delay)
            jitter = random.uniform(0, delay)

            log.warning(f"Attempt {attempt + 1} failed, retrying in {jitter:.1f}s: {e}")
            time.sleep(jitter)
        except PermanentError:
            raise  # Don't retry permanent errors
```

### Circuit Breaker
```python
class CircuitBreaker:
    CLOSED = "closed"      # Normal operation
    OPEN = "open"          # Failing, reject requests
    HALF_OPEN = "half_open"  # Testing recovery

    def __init__(self, failure_threshold=5, recovery_timeout=30, success_threshold=3):
        self.state = self.CLOSED
        self.failure_count = 0
        self.success_count = 0
        self.failure_threshold = failure_threshold
        self.recovery_timeout = recovery_timeout
        self.success_threshold = success_threshold
        self.last_failure_time = None

    def call(self, func, *args, **kwargs):
        if self.state == self.OPEN:
            if time.time() - self.last_failure_time > self.recovery_timeout:
                self.state = self.HALF_OPEN
            else:
                raise CircuitOpenError("Circuit breaker is open")

        try:
            result = func(*args, **kwargs)
            self._on_success()
            return result
        except Exception as e:
            self._on_failure()
            raise

    def _on_success(self):
        if self.state == self.HALF_OPEN:
            self.success_count += 1
            if self.success_count >= self.success_threshold:
                self.state = self.CLOSED
                self.failure_count = 0
                self.success_count = 0

    def _on_failure(self):
        self.failure_count += 1
        self.last_failure_time = time.time()
        if self.failure_count >= self.failure_threshold:
            self.state = self.OPEN
```

---

## Saga Pattern (Distributed Transactions)

### Orchestration-Based Saga
```python
class OrderSaga:
    """Coordinate multi-step order process with compensation."""

    STEPS = [
        {"action": "reserve_inventory", "compensate": "release_inventory"},
        {"action": "charge_payment", "compensate": "refund_payment"},
        {"action": "create_shipment", "compensate": "cancel_shipment"},
        {"action": "send_confirmation", "compensate": "send_cancellation"},
    ]

    async def execute(self, order):
        completed_steps = []

        for step in self.STEPS:
            try:
                result = await self.run_step(step["action"], order)
                completed_steps.append(step)
                order.update_from(result)
            except Exception as e:
                log.error(f"Step {step['action']} failed: {e}")
                await self.compensate(completed_steps, order)
                raise SagaFailedError(step["action"], e)

        return order

    async def compensate(self, completed_steps, order):
        """Reverse completed steps in reverse order."""
        for step in reversed(completed_steps):
            try:
                await self.run_step(step["compensate"], order)
            except Exception as e:
                # Compensation failure: log and alert for manual intervention
                log.critical(f"Compensation {step['compensate']} failed: {e}")
                await alert_operations(order, step, e)
```

### Temporal Saga Implementation
```python
@workflow.defn
class OrderSagaWorkflow:
    @workflow.run
    async def run(self, order: Order) -> OrderResult:
        compensations = []

        try:
            # Step 1: Reserve inventory
            reservation = await workflow.execute_activity(
                reserve_inventory, order,
                start_to_close_timeout=timedelta(seconds=30)
            )
            compensations.append(("release_inventory", reservation))

            # Step 2: Charge payment
            payment = await workflow.execute_activity(
                charge_payment, order,
                start_to_close_timeout=timedelta(minutes=2)
            )
            compensations.append(("refund_payment", payment))

            # Step 3: Ship
            shipment = await workflow.execute_activity(
                create_shipment, order,
                start_to_close_timeout=timedelta(minutes=5)
            )

            return OrderResult(status="completed")

        except Exception as e:
            # Compensate in reverse order
            for comp_name, comp_data in reversed(compensations):
                await workflow.execute_activity(
                    comp_name, comp_data,
                    start_to_close_timeout=timedelta(minutes=2),
                    retry_policy=RetryPolicy(maximum_attempts=10)  # Must succeed
                )
            return OrderResult(status="failed", error=str(e))
```

---

## Dead-Letter Queue (DLQ) Pattern

### DLQ Architecture
```
Main Queue → Consumer → Process
                ↓ (failure after max retries)
            Dead-Letter Queue
                ↓
          ┌─────┼──────┐
          ↓     ↓      ↓
       Alert  Store  Dashboard
          ↓
    Manual Review → Replay / Discard
```

### DLQ Implementation
```python
class DeadLetterHandler:
    def __init__(self, dlq_store, alert_service):
        self.store = dlq_store
        self.alerter = alert_service

    async def send_to_dlq(self, message, error, attempts):
        """Park a failed message in the dead-letter queue."""
        dlq_entry = {
            "id": uuid4(),
            "original_message": message,
            "error": str(error),
            "error_type": type(error).__name__,
            "attempts": attempts,
            "failed_at": utcnow(),
            "status": "pending_review",
            "queue": message.source_queue,
        }

        await self.store.insert(dlq_entry)
        await self.alerter.notify(
            channel="ops",
            message=f"DLQ entry: {message.source_queue} - {error}",
            severity="warning" if attempts < 10 else "critical"
        )

    async def replay(self, dlq_id, modifications=None):
        """Replay a DLQ message back to the original queue."""
        entry = await self.store.get(dlq_id)
        message = entry["original_message"]

        if modifications:
            message.update(modifications)

        await publish_to_queue(entry["queue"], message)
        await self.store.update(dlq_id, status="replayed")

    async def bulk_replay(self, filter_criteria):
        """Replay multiple DLQ entries matching criteria."""
        entries = await self.store.query(filter_criteria)
        results = {"success": 0, "failed": 0}

        for entry in entries:
            try:
                await self.replay(entry["id"])
                results["success"] += 1
            except Exception:
                results["failed"] += 1

        return results
```

---

## Idempotency Patterns

### Idempotency Key Store
```python
class IdempotencyGuard:
    def __init__(self, store, ttl=timedelta(hours=24)):
        self.store = store
        self.ttl = ttl

    async def execute_once(self, key, func, *args, **kwargs):
        """Execute function only if key hasn't been processed."""
        existing = await self.store.get(key)
        if existing:
            return existing["result"]  # Return cached result

        # Mark as in-progress (prevents parallel duplicates)
        await self.store.set(key, {"status": "processing"}, ttl=self.ttl)

        try:
            result = await func(*args, **kwargs)
            await self.store.set(key, {"status": "completed", "result": result}, ttl=self.ttl)
            return result
        except Exception as e:
            await self.store.delete(key)  # Allow retry on failure
            raise
```

### Check-Before-Act Pattern
```python
async def process_payment(order_id, amount):
    """Idempotent payment processing."""
    # Check if already processed
    existing_payment = await db.payments.find_one({"order_id": order_id})
    if existing_payment:
        return existing_payment  # Already done

    # Process payment with idempotency key
    result = await payment_gateway.charge(
        amount=amount,
        idempotency_key=f"payment-{order_id}",
    )

    # Store result
    await db.payments.insert_one({
        "order_id": order_id,
        "payment_id": result.id,
        "amount": amount,
        "status": "completed",
    })

    return result
```

---

## Timeout & Deadline Management

### Hierarchical Timeouts
```python
TIMEOUT_CONFIG = {
    "workflow": {
        "total": timedelta(days=7),       # Max workflow duration
        "step": timedelta(hours=24),       # Max time per step
    },
    "activity": {
        "schedule_to_start": timedelta(minutes=5),  # Queue wait
        "start_to_close": timedelta(minutes=30),     # Execution time
        "heartbeat": timedelta(minutes=1),            # Liveness check
    },
    "external": {
        "api_call": timedelta(seconds=30),
        "db_query": timedelta(seconds=10),
        "human_approval": timedelta(hours=48),
    }
}
```
