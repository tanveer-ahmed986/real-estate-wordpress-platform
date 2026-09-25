# CRM Automation & Task Routing Patterns

## CRM Automation Architecture

### Event-Driven CRM Pipeline
```
CRM Events → Event Bus → Automation Engine → Actions
                              ↓
                        Rules Engine
                              ↓
                   ┌──────────┼──────────┐
                   ↓          ↓          ↓
              Lead Routing  Nurturing  Notifications
```

### Common CRM Automation Triggers

| Trigger | Event | Automated Action |
|---------|-------|-----------------|
| New lead created | `lead.created` | Score, route, notify sales |
| Deal stage changed | `deal.stage.updated` | Update tasks, notify stakeholders |
| Contact inactive | `contact.activity.stale` | Trigger re-engagement campaign |
| Meeting booked | `meeting.scheduled` | Prep materials, notify team |
| Contract signed | `contract.signed` | Trigger onboarding workflow |
| Support ticket opened | `ticket.created` | Route by priority, assign agent |
| Renewal approaching | `subscription.renewal.due` | Notify CSM, create renewal task |

---

## Lead Routing Patterns

### Round-Robin Assignment
```python
class RoundRobinRouter:
    def __init__(self, team_members):
        self.queue = deque(team_members)

    def assign(self, lead):
        while True:
            assignee = self.queue[0]
            self.queue.rotate(-1)

            if is_available(assignee) and not is_at_capacity(assignee):
                return assign_lead(lead, assignee)

        # All at capacity - queue for next available
        return queue_lead(lead)
```

### Weighted Assignment (Performance-Based)
```python
def weighted_assign(lead, team):
    """Assign based on rep performance and current load."""
    scores = []
    for rep in team:
        weight = (
            rep.conversion_rate * 0.4 +
            rep.avg_response_time_score * 0.3 +
            (1 - rep.current_load / rep.max_capacity) * 0.3
        )
        scores.append((rep, weight))

    scores.sort(key=lambda x: x[1], reverse=True)
    return assign_lead(lead, scores[0][0])
```

### Territory-Based Routing
```python
TERRITORY_MAP = {
    "US-West": {"states": ["CA", "WA", "OR", "NV"], "team": "west-team"},
    "US-East": {"states": ["NY", "NJ", "MA", "CT"], "team": "east-team"},
    "EMEA": {"countries": ["GB", "DE", "FR"], "team": "emea-team"},
    "APAC": {"countries": ["JP", "AU", "SG"], "team": "apac-team"},
}

def route_by_territory(lead):
    for territory, config in TERRITORY_MAP.items():
        if lead.country in config.get("countries", []) or \
           lead.state in config.get("states", []):
            team = get_team(config["team"])
            return round_robin_assign(lead, team)

    return assign_to_default_team(lead)
```

### Lead Scoring & Qualification
```python
SCORING_RULES = {
    "demographic": {
        "company_size_500+": 20,
        "title_c_level": 25,
        "title_vp_director": 15,
        "industry_match": 15,
    },
    "behavioral": {
        "visited_pricing_page": 10,
        "downloaded_whitepaper": 5,
        "attended_webinar": 8,
        "requested_demo": 30,
        "opened_email_3x": 5,
    },
    "engagement_decay": {
        "days_since_last_activity": lambda days: max(0, 20 - days),
    }
}

def score_lead(lead, activities):
    score = 0
    for rule_category, rules in SCORING_RULES.items():
        for rule, points in rules.items():
            if callable(points):
                score += points(get_metric(lead, rule))
            elif matches_rule(lead, activities, rule):
                score += points

    lead.score = score
    lead.qualification = (
        "hot" if score >= 80 else
        "warm" if score >= 50 else
        "cold" if score >= 20 else
        "unqualified"
    )
    return lead
```

---

## Task Routing & Assignment

### Priority Queue Pattern
```python
class TaskRouter:
    def __init__(self):
        self.queues = {
            "critical": PriorityQueue(),  # P0: Immediate
            "high": PriorityQueue(),      # P1: < 1 hour
            "medium": PriorityQueue(),    # P2: < 4 hours
            "low": PriorityQueue(),       # P3: < 24 hours
        }

    def enqueue(self, task):
        priority = self.classify_priority(task)
        self.queues[priority].put((task.due_at, task))
        self.notify_available_agents(priority)

    def assign_next(self, agent):
        """Assign highest priority available task to agent."""
        for priority in ["critical", "high", "medium", "low"]:
            if not self.queues[priority].empty():
                _, task = self.queues[priority].get()
                if self.agent_qualified(agent, task):
                    return assign(task, agent)
                else:
                    self.queues[priority].put((task.due_at, task))
        return None
```

### Skill-Based Routing
```python
def route_by_skills(task, agents):
    """Match task requirements to agent capabilities."""
    qualified = []
    for agent in agents:
        skill_match = len(set(task.required_skills) & set(agent.skills))
        if skill_match >= len(task.required_skills):
            qualified.append((agent, skill_match, agent.current_load))

    if not qualified:
        return escalate_to_supervisor(task, reason="no_qualified_agents")

    # Sort by: most skills matched, then lowest load
    qualified.sort(key=lambda x: (-x[1], x[2]))
    return assign(task, qualified[0][0])
```

### Workload Balancing
```python
class WorkloadBalancer:
    MAX_CONCURRENT = 10
    MAX_WEIGHT = 100

    TASK_WEIGHTS = {
        "complex_investigation": 30,
        "standard_review": 15,
        "simple_update": 5,
    }

    def can_accept(self, agent, task):
        current_weight = sum(
            self.TASK_WEIGHTS.get(t.type, 10) for t in agent.active_tasks
        )
        task_weight = self.TASK_WEIGHTS.get(task.type, 10)
        return (
            len(agent.active_tasks) < self.MAX_CONCURRENT and
            current_weight + task_weight <= self.MAX_WEIGHT
        )
```

---

## Contact Lifecycle Automation

### Lifecycle Stage Transitions
```
Lead → MQL → SQL → Opportunity → Customer → Advocate
  ↓      ↓      ↓        ↓           ↓          ↓
Score  Nurture  Demo    Proposal   Onboard    Referral
       Drip    Follow   Negotiate  Support    Program
```

### Automated Stage Transitions
```python
STAGE_TRANSITIONS = {
    "lead_to_mql": {
        "condition": lambda lead: lead.score >= 50,
        "actions": [
            "update_stage('mql')",
            "notify_marketing('mql_reached', lead)",
            "start_nurture_sequence(lead)",
        ]
    },
    "mql_to_sql": {
        "condition": lambda lead: lead.score >= 80 and lead.has_demo_request,
        "actions": [
            "update_stage('sql')",
            "assign_sales_rep(lead)",
            "create_follow_up_task(lead, due='1_hour')",
            "notify_sales('sql_qualified', lead)",
        ]
    },
    "customer_churn_risk": {
        "condition": lambda customer: (
            customer.usage_trend == "declining" and
            customer.nps_score < 7 and
            customer.days_to_renewal <= 90
        ),
        "actions": [
            "flag_churn_risk(customer)",
            "notify_csm('churn_risk', customer)",
            "create_retention_task(customer, priority='high')",
            "schedule_executive_review(customer)",
        ]
    }
}
```

---

## Integration Patterns

### Webhook-Based CRM Sync
```python
@app.post("/webhooks/crm")
async def handle_crm_webhook(request: Request):
    payload = await request.json()
    event_type = payload["event"]

    handlers = {
        "contact.created": handle_new_contact,
        "deal.stage_changed": handle_deal_update,
        "task.completed": handle_task_completion,
        "note.added": handle_note_added,
    }

    handler = handlers.get(event_type)
    if handler:
        # Process async to respond quickly to webhook
        await task_queue.enqueue(handler, payload["data"])
        return {"status": "accepted"}

    return {"status": "ignored", "reason": "unknown_event"}
```

### Bi-Directional Sync Pattern
```python
class CRMSync:
    """Keep CRM and internal systems in sync."""

    async def sync_to_crm(self, entity, changes):
        """Push internal changes to CRM."""
        idempotency_key = f"{entity.id}-{hash(frozenset(changes.items()))}"
        if await self.already_processed(idempotency_key):
            return

        await crm_client.update(entity.crm_id, changes)
        await self.mark_processed(idempotency_key)

    async def sync_from_crm(self, webhook_data):
        """Pull CRM changes to internal system."""
        # Avoid sync loops: check if change originated from us
        if webhook_data.get("source") == "api" and \
           webhook_data.get("api_user") == OUR_API_USER:
            return  # Skip our own changes

        await internal_db.update(webhook_data["entity_id"], webhook_data["changes"])
```
