# Workflow Orchestration Platform Patterns

## Temporal (Durable Execution)

### Core Concepts
- **Workflows**: Long-running, fault-tolerant functions that maintain state
- **Activities**: Individual units of work (API calls, DB operations, file processing)
- **Workers**: Processes that execute workflows and activities
- **Task Queues**: Named queues connecting workflow/activity definitions to workers
- **Signals**: External events sent to running workflows
- **Queries**: Read-only operations on workflow state

### Production Patterns

#### Workflow-as-Code (Python)
```python
@workflow.defn
class OrderProcessingWorkflow:
    @workflow.run
    async def run(self, order: Order) -> OrderResult:
        # Step 1: Validate order (activity with retry policy)
        validated = await workflow.execute_activity(
            validate_order, order,
            start_to_close_timeout=timedelta(seconds=30),
            retry_policy=RetryPolicy(maximum_attempts=3)
        )

        # Step 2: Process payment (with compensation on failure)
        payment = await workflow.execute_activity(
            charge_payment, validated,
            start_to_close_timeout=timedelta(minutes=2),
        )

        # Step 3: Wait for human approval if amount > threshold
        if order.amount > 10000:
            approved = await workflow.wait_condition(
                lambda: self.approval_status is not None,
                timeout=timedelta(hours=24)
            )
            if not approved:
                await workflow.execute_activity(refund_payment, payment)
                return OrderResult(status="rejected")

        # Step 4: Fulfill order
        return await workflow.execute_activity(fulfill_order, payment)

    @workflow.signal
    async def approve(self, approved: bool):
        self.approval_status = approved
```

#### Retry Policy Best Practices
```python
# Transient failures (network, timeout)
transient_retry = RetryPolicy(
    initial_interval=timedelta(seconds=1),
    backoff_coefficient=2.0,
    maximum_interval=timedelta(minutes=5),
    maximum_attempts=5,
    non_retryable_error_types=["ValueError", "BusinessError"]
)

# Critical operations (payment, external API)
critical_retry = RetryPolicy(
    initial_interval=timedelta(seconds=5),
    backoff_coefficient=2.0,
    maximum_interval=timedelta(minutes=30),
    maximum_attempts=10,
)
```

#### Worker Auto-Tuning (2025 GA)
```python
# Temporal auto-tunes concurrent tasks based on CPU/memory
worker = Worker(
    client=client,
    task_queue="order-processing",
    workflows=[OrderProcessingWorkflow],
    activities=[validate_order, charge_payment, fulfill_order],
    # Auto-tuning adjusts these dynamically
    max_concurrent_activities=100,
    max_concurrent_workflow_tasks=100,
)
```

### When to Use Temporal
- Long-running workflows (hours to months)
- Financial/compliance-grade reliability
- Complex compensation logic (saga pattern)
- Workflows with human approval gates
- Mission-critical business processes

---

## Camunda (BPMN-Based)

### Core Concepts
- **Process Definitions**: BPMN 2.0 diagrams defining workflow logic
- **Zeebe**: Cloud-native workflow engine (horizontally scalable)
- **Job Workers**: Services that execute individual tasks
- **Message Correlation**: Matching events to running process instances
- **DMN**: Decision Model and Notation for business rules

### Production Patterns

#### Zeebe Job Worker (Java)
```java
@ZeebeWorker(type = "process-payment")
public void processPayment(@ZeebeVariable String orderId,
                           @ZeebeVariable double amount,
                           JobClient client, ActivatedJob job) {
    try {
        PaymentResult result = paymentService.charge(orderId, amount);
        client.newCompleteCommand(job.getKey())
            .variables(Map.of("paymentId", result.getId()))
            .send().join();
    } catch (PaymentException e) {
        client.newThrowErrorCommand(job.getKey())
            .errorCode("PAYMENT_FAILED")
            .errorMessage(e.getMessage())
            .send().join();
    }
}
```

#### Message Correlation (Event-Driven)
```java
// Publish event that correlates to running process
zeebeClient.newPublishMessageCommand()
    .messageName("payment-received")
    .correlationKey(orderId)
    .variables(Map.of("amount", 150.00))
    .timeToLive(Duration.ofHours(1))
    .send().join();
```

### When to Use Camunda
- Business stakeholders need visual process diagrams
- BPMN 2.0 compliance required
- Complex decision tables (DMN)
- Audit trail requirements
- Microservices orchestration with Kafka integration

---

## n8n (Low-Code Automation)

### Core Concepts
- **Workflows**: Visual node-based automation flows
- **Nodes**: Pre-built connectors (1,100+ integrations)
- **Triggers**: Webhook, schedule, event-based workflow starts
- **Credentials**: Secure storage for API keys and OAuth tokens
- **Executions**: Logged runs with input/output data

### Production Patterns

#### Webhook-Triggered CRM Automation
```json
{
  "nodes": [
    {
      "type": "n8n-nodes-base.webhook",
      "parameters": {
        "path": "new-lead",
        "httpMethod": "POST"
      }
    },
    {
      "type": "n8n-nodes-base.if",
      "parameters": {
        "conditions": {
          "number": [{"value1": "={{$json.score}}", "operation": "largerEqual", "value2": 80}]
        }
      }
    },
    {
      "type": "n8n-nodes-base.salesforce",
      "parameters": {
        "operation": "create",
        "resource": "lead",
        "fields": {"company": "={{$json.company}}", "email": "={{$json.email}}"}
      }
    },
    {
      "type": "n8n-nodes-base.slack",
      "parameters": {
        "channel": "#sales-alerts",
        "text": "New qualified lead: {{$json.company}}"
      }
    }
  ]
}
```

#### n8n + Airflow Integration
```python
# Custom Airflow operator to trigger n8n workflows
class N8nOperator(BaseOperator):
    def __init__(self, workflow_id, n8n_url, **kwargs):
        super().__init__(**kwargs)
        self.workflow_id = workflow_id
        self.n8n_url = n8n_url

    def execute(self, context):
        response = requests.post(
            f"{self.n8n_url}/api/v1/workflows/{self.workflow_id}/execute",
            headers={"X-N8N-API-KEY": Variable.get("n8n_api_key")},
            json={"data": context["params"]}
        )
        return response.json()
```

### When to Use n8n
- Rapid automation prototyping
- Non-developer teams building automations
- SaaS-to-SaaS integrations (CRM, email, chat)
- Webhook-based event processing
- Simple approval workflows

---

## Apache Airflow (DAG-Based)

### Core Concepts (Airflow 3.0 - April 2025)
- **DAGs**: Directed acyclic graphs defining task dependencies
- **Tasks/Operators**: Individual units of work
- **Sensors**: Wait for external conditions
- **XCom**: Cross-communication between tasks
- **Pools**: Limit concurrent task execution
- **Datasets**: Data-aware scheduling (trigger on data changes)

### Production Patterns

#### Business Process DAG
```python
from airflow.decorators import dag, task
from datetime import datetime, timedelta

@dag(
    schedule="@daily",
    start_date=datetime(2025, 1, 1),
    catchup=False,
    default_args={
        "retries": 3,
        "retry_delay": timedelta(minutes=5),
        "retry_exponential_backoff": True,
    },
    tags=["business-process", "invoicing"],
)
def invoice_processing():
    @task
    def extract_invoices():
        """Pull new invoices from ERP system."""
        return erp_client.get_pending_invoices()

    @task
    def validate_invoices(invoices):
        """Validate invoice data against business rules."""
        valid, invalid = [], []
        for inv in invoices:
            if inv.amount > 0 and inv.vendor_id:
                valid.append(inv)
            else:
                invalid.append(inv)
        if invalid:
            notify_exceptions(invalid)
        return valid

    @task
    def route_for_approval(invoices):
        """Route to appropriate approver based on amount."""
        for inv in invoices:
            if inv.amount > 50000:
                assign_to(inv, "cfo")
            elif inv.amount > 10000:
                assign_to(inv, "finance_manager")
            else:
                auto_approve(inv)

    invoices = extract_invoices()
    validated = validate_invoices(invoices)
    route_for_approval(validated)

invoice_processing()
```

### When to Use Airflow
- Batch processing and ETL workflows
- Data pipeline orchestration
- Scheduled business processes
- Complex task dependency management
- Large-scale operations (Uber: 450K daily runs)

---

## Platform Comparison Matrix

| Feature | Temporal | Camunda | n8n | Airflow |
|---------|----------|---------|-----|---------|
| **Execution Model** | Durable code | BPMN engine | Node-based | DAG scheduler |
| **State Management** | Event-sourced | Process state | Execution logs | XCom + metadata DB |
| **Failure Handling** | Auto-resume | Error events | Retry nodes | Task retries |
| **Scaling** | Horizontal | Zeebe clusters | Queue mode | Celery/K8s executor |
| **Human Tasks** | Signals/queries | User tasks | Forms/webhooks | Sensors + external |
| **Learning Curve** | Steep | Moderate | Low | Moderate |
| **Best For** | Mission-critical | BPMN compliance | Quick integrations | Data pipelines |
