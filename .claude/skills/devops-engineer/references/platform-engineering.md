# Platform Engineering & Internal Developer Platforms (2026)

## What is Platform Engineering?

**Platform Engineering** is the discipline of building and maintaining Internal Developer Platforms (IDPs) that enable self-service capabilities and reduce cognitive load on developers.

**Key Principle**: "You build it, you run it" - but with paved roads, not roadblocks.

### Platform Engineering vs DevOps

| Aspect | DevOps | Platform Engineering |
|--------|--------|---------------------|
| **Focus** | Culture and practices | Product and tooling |
| **Who** | Everyone | Dedicated platform team |
| **What** | Break down silos | Build internal products |
| **How** | Automation, collaboration | Self-service, abstraction |
| **Outcome** | Faster delivery | Better developer experience |

**They complement each other**: Platform Engineering is a tangible implementation of DevOps principles.

## Why Platform Engineering in 2026?

### Statistics
- **80%** of software organizations have platform teams by 2026 (Gartner)
- **76%** of DevOps teams integrated AI into CI/CD by late 2025
- **73%** of enterprises use AIOps to combat alert fatigue
- Platform teams reduce lead time by **40-60%**

### Common Developer Pain Points (Pre-Platform)
- ❌ "I need to open a ticket to get a database"
- ❌ "Which version of Node should I use?"
- ❌ "How do I deploy to production?"
- ❌ "Where are the logs for my service?"
- ❌ "What's the status of my deployment?"
- ❌ "I broke staging and don't know how to fix it"

### Post-Platform Experience
- ✅ Click button → Database provisioned in 5 minutes
- ✅ Predefined tech stacks with best practices baked in
- ✅ `git push` → Automatic deployment with rollback
- ✅ Unified dashboard shows all logs, metrics, traces
- ✅ Real-time deployment status in Slack
- ✅ Automatic rollback on failures

## Core Components of an IDP

### 1. Developer Portal (Backstage)

**Backstage** (by Spotify) is the leading open-source developer portal.

```yaml
# app-config.yaml
app:
  title: Acme Developer Portal
  baseUrl: http://localhost:3000

organization:
  name: Acme Corp

backend:
  baseUrl: http://localhost:7007
  database:
    client: pg
    connection:
      host: ${POSTGRES_HOST}
      user: ${POSTGRES_USER}
      password: ${POSTGRES_PASSWORD}

catalog:
  rules:
    - allow: [Component, System, API, Resource, Location]

  locations:
    - type: url
      target: https://github.com/org/repo/blob/main/catalog-info.yaml
```

**Key Features**:
- **Service Catalog**: Single source of truth for all services
- **Software Templates**: Scaffold new projects with best practices
- **TechDocs**: Documentation alongside code
- **Plugins**: Kubernetes, CI/CD, monitoring integration
- **Search**: Find any service, API, or owner

**Setup**:
```bash
npx @backstage/create-app@latest
cd my-backstage-app
yarn install
yarn dev
```

### 2. Software Templates (Golden Paths)

**Golden Path**: The "blessed" way to build and deploy services

```yaml
# template.yaml
apiVersion: scaffolder.backstage.io/v1beta3
kind: Template
metadata:
  name: node-api-template
  title: Node.js REST API
  description: Create a new Node.js REST API with best practices
spec:
  owner: platform-team
  type: service

  parameters:
    - title: Service Details
      required:
        - name
        - description
      properties:
        name:
          title: Name
          type: string
          pattern: '^[a-z0-9-]+$'
        description:
          title: Description
          type: string
        owner:
          title: Owner Team
          type: string
          ui:field: OwnerPicker

    - title: Configuration
      properties:
        database:
          title: Database
          type: string
          enum: ['postgresql', 'mysql', 'mongodb', 'none']
        cache:
          title: Cache
          type: boolean
          default: false

  steps:
    - id: fetch
      name: Fetch Template
      action: fetch:template
      input:
        url: ./skeleton
        values:
          name: ${{ parameters.name }}
          description: ${{ parameters.description }}
          owner: ${{ parameters.owner }}

    - id: publish
      name: Publish to GitHub
      action: publish:github
      input:
        repoUrl: github.com?repo=${{ parameters.name }}&owner=org
        defaultBranch: main

    - id: register
      name: Register Component
      action: catalog:register
      input:
        repoContentsUrl: ${{ steps.publish.output.repoContentsUrl }}
        catalogInfoPath: '/catalog-info.yaml'

    - id: create-ci
      name: Setup CI/CD
      action: github:actions:dispatch
      input:
        repoUrl: github.com?repo=${{ parameters.name }}&owner=org
        workflowId: setup.yml

    - id: provision-db
      name: Provision Database
      action: http:backstage:request
      if: ${{ parameters.database !== 'none' }}
      input:
        method: POST
        path: /api/databases
        body:
          type: ${{ parameters.database }}
          name: ${{ parameters.name }}-db

  output:
    links:
      - title: Repository
        url: ${{ steps.publish.output.remoteUrl }}
      - title: Pipeline
        url: https://github.com/org/${{ parameters.name }}/actions
```

**What Golden Paths Provide**:
- Pre-configured linting, testing, security scanning
- CI/CD pipelines already set up
- Observability integrated (logging, metrics, tracing)
- Security best practices baked in
- Documentation templates
- Deployment configs (Kubernetes manifests)

### 3. Self-Service Infrastructure

**Infrastructure Automation Layer**:
```python
# Platform API
@app.post("/databases")
async def create_database(db_request: DatabaseRequest):
    """
    Self-service database provisioning
    - Validates request against policy
    - Creates database in appropriate environment
    - Sets up backups, monitoring
    - Returns connection details as secret
    """
    # Validate
    if not validate_policy(db_request):
        raise PolicyViolation("Database too large for dev environment")

    # Provision (via Terraform/Pulumi)
    db = terraform_runner.apply(
        module="database",
        vars={
            "type": db_request.type,
            "size": db_request.size,
            "environment": db_request.environment
        }
    )

    # Setup monitoring
    monitoring.add_dashboard(
        service=db_request.name,
        metrics=["connections", "cpu", "memory", "disk"]
    )

    # Create secret
    k8s.create_secret(
        name=f"{db_request.name}-db",
        data={
            "host": db.endpoint,
            "username": db.username,
            "password": db.password
        }
    )

    # Notify
    slack.send(f"Database {db_request.name} ready! Secret: {db_request.name}-db")

    return {"status": "created", "endpoint": db.endpoint}
```

**Self-Service Capabilities**:
- Database provisioning (PostgreSQL, MySQL, MongoDB, Redis)
- Kubernetes namespace creation
- CI/CD pipeline setup
- SSL certificate generation
- Feature flag creation
- Preview environment spin-up
- Cost budgets and alerts

### 4. Unified Observability

**Single Pane of Glass** for all services:

```yaml
# Integrated into developer portal
observability:
  metrics:
    provider: prometheus
    dashboards:
      - service-overview
      - error-rates
      - latency-percentiles

  logging:
    provider: loki
    retention: 30d
    search: enabled

  tracing:
    provider: tempo
    sampling: 0.1  # 10% of requests

  alerts:
    provider: alertmanager
    routes:
      - team: backend
        slack_channel: "#backend-alerts"
      - team: frontend
        slack_channel: "#frontend-alerts"
```

**Developer Experience**:
- Click service in catalog → See live metrics
- Search logs across all services
- Trace request across microservices
- See deployment history and changelogs
- View cost breakdown by service

### 5. Policy as Code (Guardrails)

**OPA (Open Policy Agent)** for automated governance:

```rego
# policy.rego
package kubernetes.admission

deny[msg] {
    input.request.kind.kind == "Pod"
    not input.request.object.spec.securityContext.runAsNonRoot
    msg := "Containers must run as non-root user"
}

deny[msg] {
    input.request.kind.kind == "Deployment"
    not input.request.object.spec.template.spec.containers[_].resources.limits
    msg := "All containers must have resource limits"
}

deny[msg] {
    input.request.kind.kind == "Ingress"
    not input.request.object.metadata.annotations["cert-manager.io/cluster-issuer"]
    msg := "Ingress must use cert-manager for TLS"
}

warn[msg] {
    input.request.kind.kind == "Deployment"
    input.request.object.spec.replicas < 2
    msg := "Consider using at least 2 replicas for high availability"
}
```

**Policy Categories**:
- Security (no root containers, secret management)
- Resource limits (prevent resource exhaustion)
- Compliance (PCI-DSS, SOC 2, GDPR)
- Cost controls (max instance sizes, reserved instances)
- Best practices (health checks, labels, naming)

## Implementation Roadmap

### Phase 1: Foundation (Weeks 1-4)
**Goal**: Basic developer portal and service catalog

1. **Deploy Backstage**
   ```bash
   npx @backstage/create-app@latest
   # Configure auth (GitHub/GitLab)
   # Connect to service repositories
   ```

2. **Create Service Catalog**
   ```yaml
   # catalog-info.yaml in each repo
   apiVersion: backstage.io/v1alpha1
   kind: Component
   metadata:
     name: user-service
     description: User management API
     tags:
       - nodejs
       - api
   spec:
     type: service
     lifecycle: production
     owner: backend-team
     system: user-management
   ```

3. **Integrate Existing Tools**
   - CI/CD status (GitHub Actions, GitLab CI)
   - Kubernetes visibility (kubectl plugin)
   - Documentation (TechDocs)

### Phase 2: Golden Paths (Weeks 5-8)
**Goal**: Self-service project creation

1. **Create 2-3 Templates**
   - Node.js API
   - React frontend
   - Python data service

2. **Automate Setup**
   - Repository creation
   - CI/CD pipeline configuration
   - K8s namespace and resources
   - Observability integration

3. **Test with 1-2 Teams**
   - Gather feedback
   - Iterate on templates

### Phase 3: Self-Service Infra (Weeks 9-16)
**Goal**: Automated infrastructure provisioning

1. **Build Platform API**
   - Database provisioning
   - Cache provisioning
   - Namespace management

2. **Integrate with IaC**
   - Terraform modules
   - GitOps for infrastructure
   - State management

3. **Add Cost Controls**
   - Budget alerts
   - Resource tagging
   - Show-back reports

### Phase 4: Advanced Features (Weeks 17-24)
**Goal**: Full platform maturity

1. **Policy Enforcement**
   - OPA integration
   - Automated compliance checks
   - Security scanning in portal

2. **AI Integration** (2026)
   - AI-assisted troubleshooting
   - Predictive scaling recommendations
   - Automated incident response

3. **FinOps Dashboard**
   - Real-time cost visibility
   - Optimization recommendations
   - Chargeback by team/service

## Platform Engineering Best Practices

### Start Small
```
Don't build everything at once
↓
Identify #1 developer pain point
↓
Build MVP to solve it
↓
Measure adoption and satisfaction
↓
Iterate
```

### Make it Optional (At First)
- Don't force adoption
- Make platform so good that devs want to use it
- Show value through early adopters
- Gradual migration, not big bang

### Platform as a Product
- Developers are your customers
- Gather feedback continuously
- Measure developer satisfaction (DevEx)
- Have product managers, not just engineers

### Treat Platform Team Right
```
Platform Team Size:
  Small company (< 100 eng): 2-3 people
  Medium company (100-500 eng): 5-10 people
  Large company (> 500 eng): 15-30 people

Ratio: ~1 platform engineer per 25-50 developers
```

### Metrics to Track

| Category | Metric | Target |
|----------|--------|--------|
| **Velocity** | Time to first deployment | < 1 hour |
| **Velocity** | PR merge to production | < 24 hours |
| **Satisfaction** | Developer NPS | > 40 |
| **Adoption** | % services using golden paths | > 70% |
| **Reliability** | Platform uptime | > 99.9% |
| **Self-Service** | % requests automated | > 80% |
| **Cost** | Cloud cost per developer | Trending down |

## Common Anti-Patterns

| Anti-Pattern | Why Bad | Instead |
|--------------|---------|---------|
| **Build Everything** | Never ship, overwhelm team | Start with biggest pain point |
| **Enforce Adoption** | Developer resistance | Make it so good they want it |
| **Platform Team Silo** | Becomes ticket queue | Embed in product teams |
| **One Size Fits All** | Doesn't fit anyone | Golden paths + escape hatches |
| **No Product Mindset** | Build features no one uses | Treat devs as customers |

## Tools Ecosystem (2026)

### Developer Portals
- **Backstage** (Spotify) - Most popular, extensible
- **Port** - Commercial, SaaS option
- **Cortex** - Focus on service catalog and scorecards
- **OpsLevel** - Service maturity tracking

### Infrastructure Automation
- **Terraform** - Multi-cloud IaC
- **Crossplane** - Kubernetes-native infrastructure
- **Pulumi** - Infrastructure as real code
- **AWS Proton** - AWS-only platform service

### Policy Enforcement
- **OPA** (Open Policy Agent) - General purpose
- **Kyverno** - Kubernetes-native
- **Checkov** - IaC security scanning
- **Sentinel** - HashiCorp's policy language

### Cost Management (FinOps)
- **Kubecost** - Kubernetes cost visibility
- **Cloud Custodian** - Multi-cloud cost optimization
- **Infracost** - IaC cost estimation
- **OpenCost** - Open-source cost monitoring

## Example: Complete IDP Stack

```yaml
Developer Portal:
  ├─ Backstage (UI)
  ├─ Service Catalog (metadata)
  └─ Software Templates (scaffolding)

Infrastructure Platform:
  ├─ Kubernetes (compute)
  ├─ Crossplane (infra provisioning)
  ├─ Argo CD (GitOps deployment)
  └─ External Secrets (secret management)

Observability:
  ├─ Prometheus (metrics)
  ├─ Loki (logs)
  ├─ Tempo (traces)
  └─ Grafana (dashboards)

Security & Policy:
  ├─ OPA/Kyverno (policy enforcement)
  ├─ Trivy (vulnerability scanning)
  └─ Vault (secrets management)

FinOps:
  ├─ Kubecost (K8s cost visibility)
  ├─ Infracost (IaC cost estimation)
  └─ Custom dashboards (cost attribution)

AI/ML (2026):
  ├─ AIOps platform (anomaly detection)
  ├─ GitHub Copilot (development assistance)
  └─ Datadog AI (predictive monitoring)
```

## Success Story Template

**Before Platform**:
- 3-5 days to provision new service
- 50+ Jira tickets per week to Ops
- 30% of developer time on toil
- Lead time: 2 weeks

**After Platform**:
- 1 hour to provision new service (self-service)
- 5 tickets per week (90% reduction)
- 5% of developer time on toil
- Lead time: 1 day

**ROI**:
- Developer productivity: +40%
- Infrastructure costs: -25% (better utilization)
- Deployment frequency: 10x increase
- MTTR: 50% reduction

## Resources

### Learn More
- [Backstage Documentation](https://backstage.io/docs)
- [Platform Engineering on Reddit](https://reddit.com/r/platformengineering)
- [Team Topologies Book](https://teamtopologies.com) - Platform Team structure
- [Gartner Platform Engineering Report](https://www.gartner.com/en/documents/platform-engineering)

### Community
- PlatformCon (annual conference)
- CNCF Platform Working Group
- Platform Engineering Slack communities
