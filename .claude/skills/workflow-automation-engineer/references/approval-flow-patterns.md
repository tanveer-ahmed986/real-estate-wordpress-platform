# Approval Flow Patterns

## Approval Flow Architecture

### Core Components
```
Request → Routing Engine → Approval Queue → Decision → Post-Action
              ↓                  ↓              ↓
         Rules Engine      Notifications    Audit Log
         (who approves)    (email/slack)    (compliance)
              ↓                  ↓
         Escalation         Reminders
         (timeout)          (SLA tracking)
```

### Approval Types

| Type | Description | Use Case |
|------|-------------|----------|
| **Sequential** | A → B → C in order | Expense reports, contract review |
| **Parallel** | A + B + C simultaneously | Cross-department sign-off |
| **Threshold** | N of M must approve | Board votes, committee decisions |
| **Hierarchical** | Manager → Director → VP | Budget approvals by amount |
| **Conditional** | Route based on criteria | Amount-based, department-based |
| **Delegated** | Approver assigns substitute | Vacation coverage, workload distribution |

---

## Routing Patterns

### Amount-Based Hierarchical Routing
```python
def get_approvers(request):
    """Route to appropriate approver(s) based on amount and type."""
    approvers = []

    if request.amount <= 1000:
        approvers = [request.submitter.manager]  # Auto-approve or manager
    elif request.amount <= 10000:
        approvers = [request.submitter.manager, get_department_head(request.department)]
    elif request.amount <= 50000:
        approvers = [get_department_head(request.department), get_finance_director()]
    else:
        approvers = [get_finance_director(), get_cfo()]

    # Add compliance review for regulated categories
    if request.category in REGULATED_CATEGORIES:
        approvers.append(get_compliance_officer())

    return approvers
```

### Skill-Based Routing
```python
def route_to_specialist(request):
    """Route based on required expertise."""
    specialists = find_specialists(
        skills=request.required_skills,
        availability="available",
        workload="below_threshold"
    )

    if not specialists:
        # Escalate to team lead if no specialist available
        return escalate_to_lead(request)

    # Round-robin among qualified specialists
    return select_round_robin(specialists)
```

### Geographic/Timezone Routing
```python
def route_by_timezone(request):
    """Route to approver in active business hours."""
    all_approvers = get_eligible_approvers(request)

    active_approvers = [
        a for a in all_approvers
        if is_business_hours(a.timezone)
    ]

    if active_approvers:
        return select_least_loaded(active_approvers)

    # Queue for next available timezone
    next_active = get_next_active_approver(all_approvers)
    return queue_for_approver(request, next_active, notify=True)
```

---

## Escalation Patterns

### Time-Based Escalation Chain
```python
ESCALATION_CHAIN = [
    {"timeout_hours": 4, "action": "reminder", "target": "current_approver"},
    {"timeout_hours": 8, "action": "reminder", "target": "current_approver", "cc": "submitter"},
    {"timeout_hours": 24, "action": "escalate", "target": "approver.manager"},
    {"timeout_hours": 48, "action": "escalate", "target": "department_head"},
    {"timeout_hours": 72, "action": "auto_action", "target": "auto_approve_or_reject"},
]

async def monitor_approval(approval_id):
    """Monitor and escalate stalled approvals."""
    for step in ESCALATION_CHAIN:
        await sleep(hours=step["timeout_hours"])

        if is_resolved(approval_id):
            return

        if step["action"] == "reminder":
            send_reminder(approval_id, step["target"])
        elif step["action"] == "escalate":
            reassign_approval(approval_id, step["target"])
            notify_escalation(approval_id, step["target"])
        elif step["action"] == "auto_action":
            apply_default_action(approval_id)
```

### Delegation Pattern
```python
class ApprovalDelegation:
    """Handle approver delegation (vacation, workload)."""

    def delegate(self, approver_id, delegate_id, start, end, scope="all"):
        """Set up delegation for a time period."""
        delegation = Delegation(
            approver=approver_id,
            delegate=delegate_id,
            start_date=start,
            end_date=end,
            scope=scope,  # "all", "department", "amount_under_5000"
        )
        save_delegation(delegation)
        notify_delegate(delegation)

    def resolve_approver(self, original_approver_id):
        """Find actual approver considering delegations."""
        delegation = get_active_delegation(original_approver_id)
        if delegation and delegation.is_active():
            return delegation.delegate_id
        return original_approver_id
```

---

## SLA Tracking

### SLA Configuration
```python
SLA_DEFINITIONS = {
    "urgent": {
        "response_time": timedelta(hours=2),
        "resolution_time": timedelta(hours=4),
        "escalation_at": [
            timedelta(hours=1),   # 50% warning
            timedelta(hours=1.5), # 75% critical
        ],
    },
    "standard": {
        "response_time": timedelta(hours=8),
        "resolution_time": timedelta(hours=24),
        "escalation_at": [
            timedelta(hours=4),
            timedelta(hours=6),
        ],
    },
    "low": {
        "response_time": timedelta(hours=24),
        "resolution_time": timedelta(hours=72),
        "escalation_at": [
            timedelta(hours=12),
            timedelta(hours=24),
        ],
    },
}
```

### SLA Monitoring
```python
class SLAMonitor:
    def check_sla(self, approval):
        sla = SLA_DEFINITIONS[approval.priority]
        elapsed = now() - approval.created_at
        remaining = sla["resolution_time"] - elapsed

        if remaining <= timedelta(0):
            self.breach(approval)
        elif remaining <= sla["escalation_at"][-1]:
            self.warn_critical(approval, remaining)
        elif remaining <= sla["escalation_at"][0]:
            self.warn(approval, remaining)

        return {
            "status": "on_track" if remaining > timedelta(0) else "breached",
            "remaining": remaining,
            "percentage": (elapsed / sla["resolution_time"]) * 100
        }
```

---

## Audit Trail

### Approval Event Log
```python
@dataclass
class ApprovalEvent:
    event_id: str
    approval_id: str
    timestamp: datetime
    actor: str
    action: str  # "submitted", "approved", "rejected", "escalated", "delegated"
    comment: Optional[str]
    metadata: dict  # IP, device, location for compliance

def log_approval_event(approval_id, actor, action, comment=None, **metadata):
    """Immutable audit log entry for every approval action."""
    event = ApprovalEvent(
        event_id=uuid4(),
        approval_id=approval_id,
        timestamp=utcnow(),
        actor=actor,
        action=action,
        comment=comment,
        metadata={
            "ip_address": get_client_ip(),
            "user_agent": get_user_agent(),
            **metadata
        }
    )
    # Append-only storage (never update/delete)
    audit_store.append(event)
    return event
```

---

## Implementation with Temporal

### Approval Workflow
```python
@workflow.defn
class ApprovalWorkflow:
    def __init__(self):
        self.decision = None
        self.comment = None

    @workflow.run
    async def run(self, request: ApprovalRequest) -> ApprovalResult:
        # Determine approvers
        approvers = await workflow.execute_activity(
            determine_approvers, request,
            start_to_close_timeout=timedelta(seconds=30)
        )

        for approver in approvers:
            # Notify approver
            await workflow.execute_activity(
                send_approval_notification, approver, request,
                start_to_close_timeout=timedelta(seconds=30)
            )

            # Wait for decision with escalation
            try:
                approved = await workflow.wait_condition(
                    lambda: self.decision is not None,
                    timeout=timedelta(hours=24)
                )
            except asyncio.TimeoutError:
                # Escalate on timeout
                await workflow.execute_activity(
                    escalate_approval, request, approver,
                    start_to_close_timeout=timedelta(seconds=30)
                )
                continue

            if not self.decision:
                return ApprovalResult(status="rejected", comment=self.comment)

            self.decision = None  # Reset for next approver

        return ApprovalResult(status="approved")

    @workflow.signal
    async def submit_decision(self, approved: bool, comment: str = ""):
        self.decision = approved
        self.comment = comment
```
