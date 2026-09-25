# Notification & Alerting Systems

## Notification Architecture

### Multi-Channel Dispatch
```
Event → Notification Engine → Channel Router → Delivery
              ↓                     ↓
        User Preferences     ┌──────┼──────────┐
        Throttle/Digest      ↓      ↓          ↓
        Templates           Email  Slack    Push/SMS
                              ↓      ↓          ↓
                          Tracking  Webhook   Provider
```

---

## Channel Selection Matrix

| Urgency | Channel | Use Case |
|---------|---------|----------|
| Critical (immediate) | SMS + Push + Slack | System down, security breach, SLA breach |
| High (< 1 hour) | Slack + Email | Approval needed, task assigned, escalation |
| Medium (< 4 hours) | Email + In-app | Status update, task completed, report ready |
| Low (next batch) | Email digest | Weekly summary, trend report, FYI |

---

## Notification Engine

### Core Implementation
```python
class NotificationEngine:
    def __init__(self, channels, preferences_store, template_engine):
        self.channels = channels
        self.preferences = preferences_store
        self.templates = template_engine
        self.throttler = NotificationThrottler()

    async def send(self, event_type, recipient, data, urgency="medium"):
        # Check user preferences
        prefs = await self.preferences.get(recipient, event_type)
        if prefs.get("muted"):
            return

        # Check throttle
        if not self.throttler.allow(recipient, event_type):
            self.throttler.queue_for_digest(recipient, event_type, data)
            return

        # Render template
        content = self.templates.render(event_type, data)

        # Route to channels based on urgency and preferences
        channels = self.resolve_channels(urgency, prefs)

        results = []
        for channel in channels:
            result = await self.channels[channel].deliver(recipient, content)
            results.append(result)

        await self.log_notification(event_type, recipient, channels, results)
```

### Throttling & Digest
```python
class NotificationThrottler:
    RULES = {
        "per_user_per_minute": 5,
        "per_user_per_hour": 30,
        "digest_after": 10,  # Switch to digest after N notifications
    }

    def allow(self, recipient, event_type):
        minute_count = self.get_count(recipient, window="1m")
        hour_count = self.get_count(recipient, window="1h")

        if minute_count >= self.RULES["per_user_per_minute"]:
            return False
        if hour_count >= self.RULES["per_user_per_hour"]:
            return False

        self.increment(recipient)
        return True

    async def send_digest(self, recipient):
        """Batch queued notifications into a digest."""
        queued = await self.get_queued(recipient)
        if not queued:
            return

        grouped = group_by(queued, key="event_type")
        digest_content = render_digest_template(grouped)

        await email_channel.deliver(recipient, digest_content)
        await self.clear_queued(recipient)
```

---

## Template System

### Template Structure
```python
NOTIFICATION_TEMPLATES = {
    "approval.requested": {
        "subject": "Approval needed: {{request.title}}",
        "email": {
            "body": """
                <h2>{{request.title}}</h2>
                <p>{{submitter.name}} submitted a request for your approval.</p>
                <table>
                    <tr><td>Amount:</td><td>{{request.amount | currency}}</td></tr>
                    <tr><td>Category:</td><td>{{request.category}}</td></tr>
                    <tr><td>Due:</td><td>{{request.due_date | relative_time}}</td></tr>
                </table>
                <a href="{{approve_url}}">Approve</a> | <a href="{{reject_url}}">Reject</a>
            """,
        },
        "slack": {
            "blocks": [
                {"type": "header", "text": "Approval Request: {{request.title}}"},
                {"type": "section", "text": "From: {{submitter.name}} | Amount: {{request.amount | currency}}"},
                {"type": "actions", "elements": [
                    {"type": "button", "text": "Approve", "action_id": "approve_{{request.id}}"},
                    {"type": "button", "text": "Reject", "action_id": "reject_{{request.id}}"},
                ]}
            ]
        },
        "sms": "Approval needed: {{request.title}} ({{request.amount | currency}}). Reply YES/NO."
    },
    "task.assigned": {
        "subject": "Task assigned: {{task.title}}",
        "email": {
            "body": "You've been assigned: {{task.title}}. Due: {{task.due_date | relative_time}}."
        },
        "slack": {
            "text": ":clipboard: Task assigned to you: *{{task.title}}* (Due: {{task.due_date | relative_time}})"
        }
    },
    "escalation.triggered": {
        "subject": "[ESCALATION] {{item.title}} - SLA at risk",
        "email": {
            "body": """
                <h2>Escalation Notice</h2>
                <p><strong>{{item.title}}</strong> has been escalated to you.</p>
                <p>Original assignee: {{original_assignee.name}}</p>
                <p>SLA remaining: {{sla.remaining | duration}}</p>
                <p>Reason: {{escalation.reason}}</p>
            """
        }
    }
}
```

---

## Escalation Notification Chains

### Multi-Level Escalation
```python
ESCALATION_NOTIFICATIONS = [
    {
        "level": 1,
        "delay": timedelta(hours=0),
        "recipients": ["assignee"],
        "channels": ["slack", "email"],
        "template": "task.assigned"
    },
    {
        "level": 2,
        "delay": timedelta(hours=4),
        "recipients": ["assignee"],
        "channels": ["slack", "email"],
        "template": "task.reminder"
    },
    {
        "level": 3,
        "delay": timedelta(hours=8),
        "recipients": ["assignee", "assignee.manager"],
        "channels": ["slack", "email"],
        "template": "escalation.triggered"
    },
    {
        "level": 4,
        "delay": timedelta(hours=24),
        "recipients": ["department_head"],
        "channels": ["slack", "email", "sms"],
        "template": "escalation.critical"
    },
]
```

---

## Delivery Tracking

### Notification Status Tracking
```python
@dataclass
class NotificationRecord:
    id: str
    event_type: str
    recipient: str
    channel: str
    status: str  # "queued", "sent", "delivered", "read", "failed", "bounced"
    sent_at: Optional[datetime]
    delivered_at: Optional[datetime]
    read_at: Optional[datetime]
    error: Optional[str]

class DeliveryTracker:
    async def track(self, notification_id, status, **metadata):
        await self.store.update(notification_id, {
            "status": status,
            f"{status}_at": utcnow(),
            **metadata
        })

    async def get_delivery_report(self, time_range):
        """Aggregate delivery metrics."""
        records = await self.store.query(time_range=time_range)
        return {
            "total": len(records),
            "delivered": count(records, status="delivered"),
            "read": count(records, status="read"),
            "failed": count(records, status="failed"),
            "delivery_rate": count(records, status="delivered") / len(records),
            "read_rate": count(records, status="read") / len(records),
            "avg_delivery_time": avg(r.delivered_at - r.sent_at for r in records if r.delivered_at),
        }
```

---

## User Preference Management

### Preference Schema
```python
DEFAULT_PREFERENCES = {
    "channels": {
        "email": True,
        "slack": True,
        "sms": False,  # Opt-in only
        "push": True,
    },
    "quiet_hours": {
        "enabled": False,
        "start": "22:00",
        "end": "07:00",
        "timezone": "UTC",
        "override_for": ["critical"],  # Critical bypasses quiet hours
    },
    "digest": {
        "enabled": True,
        "frequency": "daily",  # "hourly", "daily", "weekly"
        "time": "09:00",
    },
    "subscriptions": {
        "approval.requested": {"channels": ["slack", "email"], "muted": False},
        "task.assigned": {"channels": ["slack"], "muted": False},
        "report.ready": {"channels": ["email"], "muted": False},
        "system.maintenance": {"channels": ["email"], "muted": True},
    }
}
```
