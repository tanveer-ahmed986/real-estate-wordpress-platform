# DevOps Anti-Patterns

## What NOT to Do

Anti-patterns are common practices that appear beneficial but create long-term problems. Avoid these to maintain healthy DevOps practices.

---

## Organizational Anti-Patterns

### 1. Creating a Separate "DevOps Team"

**What it looks like**:
```
Development Team → Ticket → "DevOps Team" → Deployment
```

**Why it's bad**:
- Creates a new silo instead of breaking silos down
- Reintroduces ticket queue delays
- DevOps becomes a bottleneck
- Developers don't own their deployments
- Knowledge remains centralized

**Instead**:
```
Feature Teams (Dev + Ops skills embedded)
    ↓
Self-service infrastructure platform
    ↓
Deploy independently
```

**Correct approach**:
- Embed DevOps practices in all teams
- Create a Platform Team that builds self-service tools
- Enable developers to deploy independently
- "You build it, you run it" culture

---

### 2. Treating DevOps as Just Tooling

**What it looks like**:
- "We do DevOps - we have Jenkins!"
- Buying tools without changing culture
- Expecting tools to solve people problems

**Why it's bad**:
- DevOps is culture first, tools second
- Automation without collaboration fails
- Tools don't eliminate silos

**Instead**:
1. Foster collaboration culture
2. Break down silos
3. Choose tools that support your culture
4. Measure outcomes, not tool usage

**Indicators you're doing it wrong**:
- Developers still throw code "over the wall"
- Operations still manually deploys
- Teams blame each other for failures
- No shared responsibility for uptime

---

### 3. Hero Culture

**What it looks like**:
- One person knows how everything works
- "Only Sarah can deploy to production"
- Knowledge isn't documented
- Bus factor = 1

**Why it's bad**:
- Single point of failure
- Burnout risk
- Team can't scale
- No vacation for heroes

**Instead**:
- Document everything in runbooks
- Pair programming and shadowing
- Rotate on-call responsibilities
- Automate tribal knowledge into code
- Cross-train team members

---

## Technical Anti-Patterns

### 4. Manual Deployments

**What it looks like**:
```bash
# Production deployment "process"
ssh production-server
git pull
npm install
pm2 restart app
# Hope nothing broke
```

**Why it's bad**:
- Human error prone
- Not repeatable
- No audit trail
- Can't rollback easily
- Doesn't scale

**Instead**:
- Fully automated CI/CD pipeline
- Declarative infrastructure
- Immutable deployments
- Automated rollback
- Deployment tracking

**Migration path**:
```
1. Script your current manual process
2. Move script to CI/CD pipeline
3. Add automated testing
4. Implement deployment strategies (blue-green, canary)
5. Enable automated rollback
```

---

### 5. Snowflake Servers

**What it looks like**:
- Each server is manually configured
- "Production server has special tweaks"
- Configuration drift between environments
- Can't recreate from scratch

**Why it's bad**:
- Can't reproduce issues locally
- Disaster recovery is manual
- Scaling requires manual work
- "Works on my machine" syndrome

**Instead**:
- **Immutable infrastructure**: Never modify servers, replace them
- **Infrastructure as Code**: All config in version control
- **Configuration management**: Ansible, Terraform, Puppet
- **Containerization**: Consistent environments everywhere

**Test**:
```
Can you delete production and recreate it from code?
├─ Yes → Good (immutable infrastructure)
└─ No → You have snowflakes
```

---

### 6. Alert Fatigue

**What it looks like**:
- 1000+ alerts per day
- Most alerts ignored
- Real issues buried in noise
- PagerDuty notifications muted

**Why it's bad**:
- Critical alerts missed
- On-call burnout
- False sense of monitoring
- Alert overload = no alerts

**Statistics** (2026):
- 73% of enterprises struggle with alert fatigue
- Average: 10,000 alerts/week, 95% ignored

**Instead**:
- **Alert on symptoms, not causes**
  - ❌ "CPU usage > 80%" (cause)
  - ✅ "User requests failing" (symptom)

- **SLO-based alerting**
  ```
  Alert when: Error Budget < 10%
  (Not: Alert on every error)
  ```

- **Intelligent grouping** (AIOps)
  - 1 root cause → 100 symptoms
  - Show 1 alert, not 100

- **Runbooks for every alert**
  - If no action → delete alert
  - Every alert must be actionable

---

### 7. No Rollback Plan

**What it looks like**:
- Deploy to production
- Something breaks
- Panic: "How do we undo this?"
- Manual recovery attempts
- Extended outage

**Why it's bad**:
- Deployments become risky
- Fear of deploying
- Long recovery times
- Customer impact

**Instead**:
- **Automated rollback** in CI/CD
  ```yaml
  deploy:
    script: deploy.sh
    on_failure:
      - rollback.sh  # Automatic
  ```

- **Blue-green deployments**: Instant switch back
- **Database migrations**: Always backward compatible
- **Tested rollback procedure**: Practice in staging
- **Feature flags**: Disable features without deploying

**Test your rollback**:
```
Intentionally break production (in staging)
↓
Trigger rollback
↓
Verify recovery time < 5 minutes
```

---

### 8. Testing Only in Production

**What it looks like**:
- "Let's see if it works in production"
- No staging environment
- Discover bugs after deployment
- Users report issues before monitoring

**Why it's bad**:
- Poor user experience
- Reputation damage
- Firefighting mode
- High stress

**Instead**:
- **Test pyramid**:
  ```
  Production
      ↑
  Staging (production-like)
      ↑
  Integration tests
      ↑
  Unit tests (fast, comprehensive)
  ```

- **Shift-left testing**: Test early and often
- **Automated testing in CI/CD**
- **Staging environment**: Mirror production
- **Synthetic monitoring**: Test production continuously

---

### 9. Ignoring Security Until the End

**What it looks like**:
- Build feature → Deploy → Security review (oops)
- "We'll add security later"
- Security as a gate, not a practice

**Why it's bad**:
- Costly to fix vulnerabilities late
- Security vulnerabilities in production
- Compliance failures
- Reputational damage

**Instead**:
- **DevSecOps**: Security from day 1
  ```
  Code → SAST scan → Build → Container scan →
  Deploy → Runtime security monitoring
  ```

- **Shift-left security**:
  | Stage | Security Practice |
  |-------|-------------------|
  | Code | Secret scanning, static analysis |
  | Build | Dependency scanning, SBOM |
  | Container | Image vulnerability scanning |
  | Deploy | Policy enforcement (OPA) |
  | Runtime | Threat detection (Falco) |

- **Automate security**: Don't rely on manual reviews
- **Security as code**: Policies in version control

---

### 10. Cargo Cult DevOps

**What it looks like**:
- "Google uses microservices, so we should too"
- "Everyone does Kubernetes, we need it"
- Copying practices without understanding why

**Why it's bad**:
- Overengineering
- Complexity without benefits
- Tools don't match problem
- Wasted resources

**Instead**:
- **Understand your context**:
  - Team size
  - Application complexity
  - Scale requirements
  - Expertise level

- **Start simple**:
  ```
  Small app + small team:
    → Heroku or single server
  NOT → Kubernetes cluster
  ```

- **Scale when needed**:
  - Don't pre-optimize
  - Add complexity when you feel the pain
  - "You Aren't Gonna Need It" (YAGNI)

**Decision Framework**:
```
Do we have the problem this tool solves?
├─ No → Don't adopt it
└─ Yes → Do we have the expertise?
    ├─ No → Managed service or simpler alternative
    └─ Yes → Does the benefit outweigh complexity?
        ├─ No → Simpler solution
        └─ Yes → Adopt with caution
```

---

## Process Anti-Patterns

### 11. Vanity Metrics

**What it looks like**:
- "We deploy 100 times per day!" (but mostly config changes)
- "We have 10,000 tests!" (but they don't catch bugs)
- "Our velocity is 200 story points!" (but no customer value)

**Why it's bad**:
- Measures activity, not outcomes
- Gameable metrics
- Doesn't correlate with success
- Creates wrong incentives

**Instead**:
- **DORA metrics** (outcome-focused):
  - Deployment frequency → Are we delivering value?
  - Lead time → How fast can we respond?
  - MTTR → How quickly do we recover?
  - Change failure rate → How reliable are we?

- **Business metrics**:
  - Customer satisfaction
  - Revenue impact
  - Feature adoption
  - User engagement

---

### 12. Long-Lived Feature Branches

**What it looks like**:
- Feature branch lives for weeks/months
- Massive merge conflicts
- "Merge hell" at the end
- Integration issues discovered late

**Why it's bad**:
- Delayed feedback
- Merge conflicts
- Integration issues
- Delayed value delivery

**Instead**:
- **Trunk-based development**:
  ```
  main (always deployable)
    ↑
  Short-lived feature branches (<2 days)
    ↑
  Feature flags for incomplete features
  ```

- **Continuous integration**: Merge daily
- **Small batches**: Break features into small PRs
- **Feature flags**: Hide incomplete work

---

### 13. No Postmortems (or Blameful Postmortems)

**What it looks like**:
- Incident happens → Fix → Move on
- "Who broke production?" (blame culture)
- Same issues repeat
- No learning from failures

**Why it's bad**:
- Missed learning opportunities
- Same mistakes repeated
- Fear of taking risks
- Blame culture

**Instead**:
- **Blameless postmortems**:
  ```
  What happened? (timeline)
  Why did it happen? (root cause)
  How do we prevent it? (action items)
  NOT: Who's fault was it?
  ```

- **Follow up on action items**: Track and complete
- **Share learnings**: Publish postmortems internally
- **Celebrate failures**: Learning opportunities

**Template**:
```markdown
# Incident: [Brief description]
Date: [Date]
Duration: [X hours]
Impact: [User impact]

## Timeline
- 14:00: Deployment started
- 14:05: Error rate spiked to 25%
- 14:10: Alert triggered
- 14:15: Rollback initiated
- 14:20: Service restored

## Root Cause
[What went wrong - technical details]

## Contributing Factors
- Insufficient testing
- No canary deployment
- Alert delay

## Action Items
- [ ] Add integration test for X
- [ ] Implement canary deployment
- [ ] Reduce alert latency to <1 min
- [ ] Update runbook

## Lessons Learned
[What we learned]
```

---

## Infrastructure Anti-Patterns

### 14. Shared Environments

**What it looks like**:
- One staging environment for all teams
- Teams step on each other
- "Who broke staging?"
- Can't deploy without coordination

**Why it's bad**:
- Deployment bottleneck
- Environment pollution
- Hard to reproduce issues
- Coordination overhead

**Instead**:
- **Ephemeral environments**: Create and destroy per PR
- **Namespace isolation**: Kubernetes namespaces per team/feature
- **Infrastructure as Code**: Easy to spin up environments

```yaml
# Per-PR environments
name: Deploy Preview Environment
on: pull_request
jobs:
  deploy-preview:
    runs-on: ubuntu-latest
    steps:
      - name: Deploy to preview
        run: |
          kubectl create namespace pr-${{ github.event.pull_request.number }}
          kubectl apply -f manifests/ -n pr-${{ github.event.pull_request.number }}
      - name: Comment PR
        run: |
          echo "Preview: https://pr-${{ github.event.pull_request.number }}.example.com"
```

---

### 15. Hardcoded Configuration

**What it looks like**:
```javascript
// Hardcoded in code
const DB_HOST = "prod-db.example.com";
const API_KEY = "sk_live_abc123...";
```

**Why it's bad**:
- Secrets in version control
- Can't change without redeploying
- Different values per environment require code changes
- Security risk

**Instead**:
- **Environment variables**:
  ```javascript
  const DB_HOST = process.env.DB_HOST;
  const API_KEY = process.env.API_KEY;
  ```

- **ConfigMaps** (Kubernetes):
  ```yaml
  apiVersion: v1
  kind: ConfigMap
  metadata:
    name: app-config
  data:
    DB_HOST: "prod-db.example.com"
  ```

- **Secrets management**:
  - HashiCorp Vault
  - AWS Secrets Manager
  - Kubernetes Secrets (encrypted)

---

### 16. No Resource Limits

**What it looks like**:
```yaml
# No resource limits
spec:
  containers:
  - name: app
    image: my-app
    # No requests or limits defined
```

**Why it's bad**:
- One app can consume all node resources
- "Noisy neighbor" problem
- Node crashes from OOM
- Unpredictable performance

**Instead**:
```yaml
spec:
  containers:
  - name: app
    image: my-app
    resources:
      requests:  # Guaranteed resources
        memory: "256Mi"
        cpu: "250m"
      limits:    # Maximum resources
        memory: "512Mi"
        cpu: "500m"
```

**Best practices**:
- Always set requests (for scheduling)
- Set limits to prevent runaway processes
- Monitor actual usage and adjust
- Use Vertical Pod Autoscaler to recommend values

---

## Summary: How to Avoid Anti-Patterns

| Anti-Pattern Category | Key Prevention Strategy |
|----------------------|-------------------------|
| **Organizational** | Embed DevOps in all teams, not separate team |
| **Technical** | Automate everything, immutable infrastructure |
| **Process** | Measure outcomes (DORA), not activity |
| **Infrastructure** | Infrastructure as Code, ephemeral environments |
| **Security** | Shift-left, automate scanning in CI/CD |
| **Cultural** | Blameless postmortems, shared responsibility |

**Golden Rule**: If it hurts, do it more often (and automate it).
- Deployments hurt? → Deploy more frequently with automation
- Merges hurt? → Integrate continuously
- Testing hurts? → Automate tests in CI/CD
