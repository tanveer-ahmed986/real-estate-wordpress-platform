---
name: devops-engineer
description: |
  Provides comprehensive DevOps engineering guidance covering CI/CD pipelines, infrastructure automation, container orchestration, monitoring, security, and cloud-native practices.
  This skill should be used when users need expert assistance with DevOps architecture, implementation strategies, toolchain selection, pipeline optimization, Kubernetes deployments, GitOps workflows, observability setup, or DevSecOps integration. Covers both foundational DevOps practices and cutting-edge 2026 trends including AIOps, Platform Engineering, and FinOps.
allowed-tools: Read, Grep, Glob, Bash, Edit, Write
---

# DevOps Engineer

Expert guidance for modern DevOps engineering practices, infrastructure automation, and cloud-native operations.

## Required Clarifications

Before providing DevOps guidance, clarify:

1. **Current Infrastructure State**: What's your current setup?
   - None (starting from scratch)
   - Manual deployments (no automation)
   - Partial CI/CD (some automation)
   - Full DevOps (looking to optimize)

2. **Deployment Target**: Where are you deploying?
   - AWS
   - Azure
   - GCP
   - On-premises
   - Multi-cloud
   - Hybrid cloud

3. **Team Expertise**: What's your team's DevOps experience level?
   - Beginner (new to DevOps)
   - Intermediate (familiar with basics)
   - Advanced (experienced with DevOps practices)

## Optional Clarifications

4. **Compliance Requirements**: Any specific compliance needs?
   - HIPAA (healthcare)
   - PCI-DSS (payment processing)
   - SOC 2 (security controls)
   - GDPR (data privacy)
   - None

5. **Tool Constraints**: Any existing tools or preferences?
   - Existing CI/CD platform (GitHub Actions, GitLab CI, Jenkins, etc.)
   - Infrastructure as Code tool preference (Terraform, Pulumi, CloudFormation)
   - Monitoring stack preference (Prometheus, Datadog, New Relic)
   - Budget constraints

6. **Timeline**: When do you need this implemented?
   - Immediate (quick wins)
   - Within weeks (phased approach)
   - Long-term (comprehensive transformation)

**Note**: Avoid asking too many questions in a single message. Start with Required Clarifications (1-3), then Optional if needed.

## Before Implementation

Gather context to ensure successful implementation:

| Source | Gather |
|--------|--------|
| **Codebase** | Existing infrastructure, deployment configs, CI/CD pipelines, container definitions, monitoring setup |
| **Conversation** | User's specific DevOps challenges, infrastructure requirements, tooling constraints, team expertise |
| **Skill References** | DevOps patterns from `references/` (best practices, tool guides, architecture patterns, 2026 trends) |
| **User Guidelines** | Organization's DevOps standards, compliance requirements, cloud provider policies |

Ensure all required context is gathered before implementing DevOps solutions.

## Core DevOps Principles

### Cultural Foundations
- **Collaboration**: Break down silos between Dev and Ops teams - everyone owns the full lifecycle
- **Automation First**: Automate repetitive tasks to reduce errors and increase velocity
- **Continuous Improvement**: Measure, learn, iterate - embrace failure as learning
- **Shared Responsibility**: No "DevOps team" - DevOps is a practice, not a role
- **Infrastructure as Code**: Treat infrastructure like software - version controlled, tested, reviewed

### Essential Characteristics
1. **Small Batches**: Deploy frequently in small increments
2. **Test-Driven**: Write tests before code (TDD) and define behavior first (BDD)
3. **Fail Fast**: Design systems to fail gracefully and recover quickly
4. **Measure Everything**: Use actionable metrics, not vanity metrics
5. **Shift Left**: Integrate security, testing, and quality early in the pipeline

## DevOps Assessment

Assess current maturity across 6 key areas:

**Culture**: Dev/Ops collaboration or silos? Ticket queues causing delays?
**Automation**: What % of deployments automated? Infrastructure changes manual?
**CI/CD**: Commit to production time? Manual approval gates?
**Monitoring**: Detect issues before users report? Observability vs monitoring?
**Security**: Integrated in pipelines or bolted on?
**Recovery**: MTTR? Automated rollback capability?

## Implementation Workflows

### 1. Setting Up CI/CD Pipeline

**Decision Tree**: Choose the right CI/CD tool

```
Is your code on GitHub?
├─ Yes → GitHub Actions (native, free for public repos)
│   └─ Need enterprise features? → GitHub Actions + self-hosted runners
└─ No → Which platform?
    ├─ GitLab → GitLab CI/CD (built-in, excellent Docker support)
    ├─ Bitbucket → Bitbucket Pipelines or Jenkins
    └─ Enterprise/Multi-platform → Jenkins (most flexible but requires maintenance)
```

**Setup Workflow**:

1. **Initialize Version Control** (if not done)
   ```bash
   git init
   git add .
   git commit -m "Initial commit"
   ```

2. **Define Pipeline Stages**
   - Build → Test → Security Scan → Package → Deploy
   - See `references/cicd-patterns.md` for stage definitions

3. **Create Pipeline Configuration**
   - GitHub Actions: `.github/workflows/main.yml`
   - GitLab CI: `.gitlab-ci.yml`
   - Jenkins: `Jenkinsfile`
   - See `assets/pipeline-templates/` for starter templates

4. **Configure Secrets Management**
   - Never hardcode credentials
   - Use platform-native secrets (GitHub Secrets, GitLab Variables)
   - For multi-cloud: HashiCorp Vault, AWS Secrets Manager

5. **Set Up Automated Testing**
   ```yaml
   test:
     - unit tests (fast, run on every commit)
     - integration tests (run on PR)
     - e2e tests (run before deployment)
   ```

6. **Implement Deployment Strategy**
   - Blue-Green: Zero downtime, instant rollback
   - Canary: Gradual rollout with monitoring
   - Rolling: Sequential instance updates
   - See `references/deployment-strategies.md`

7. **Add Monitoring and Alerts**
   - Pipeline success/failure notifications
   - Deployment tracking
   - Performance regression detection

**Use Script**: `scripts/setup-cicd.sh` - Interactive CI/CD setup wizard

### 2. Container Orchestration with Kubernetes

**When to Use Kubernetes**:
- ✅ Microservices architecture (3+ services)
- ✅ Need auto-scaling and self-healing
- ✅ Multi-cloud or hybrid cloud deployment
- ✅ High availability requirements
- ❌ Simple monolithic app (overkill - use Docker Compose or serverless)
- ❌ Team lacks Kubernetes expertise (start with managed services like GKE, EKS, AKS)

**Setup Workflow**:

1. **Choose Deployment Platform**
   - **Local Dev**: Minikube, Kind, Docker Desktop
   - **Managed**: GKE (Google), EKS (AWS), AKS (Azure)
   - **Self-Managed**: OpenShift, Rancher, Vanilla Kubernetes

2. **Create Kubernetes Objects**
   ```bash
   # Essential objects:
   kubectl create deployment app-name --image=registry/app:tag
   kubectl expose deployment app-name --port=80 --target-port=8080
   kubectl create ingress app-ingress --rule="host/path=service:port"
   ```
   - See `references/kubernetes-objects.md` for complete object reference

3. **Implement GitOps Workflow** (2026 Best Practice)
   ```
   Code → Git Push → CI builds image → Updates manifest →
   Argo CD/Flux syncs → Kubernetes applies changes
   ```
   - Declarative configuration in Git
   - Automated reconciliation
   - Audit trail and rollback capability
   - See `references/gitops-guide.md`

4. **Configure Auto-scaling**
   ```yaml
   # Horizontal Pod Autoscaler
   kubectl autoscale deployment app-name --cpu-percent=70 --min=2 --max=10

   # Vertical Pod Autoscaler (optimize resource requests)
   # Cluster Autoscaler (scale nodes)
   ```

5. **Set Up ConfigMaps and Secrets**
   ```bash
   kubectl create configmap app-config --from-file=config/
   kubectl create secret generic app-secrets --from-env-file=.env
   ```

6. **Implement Health Checks**
   - Liveness probe: Is container alive?
   - Readiness probe: Can container serve traffic?
   - Startup probe: Has container started?

**Use Script**: `scripts/k8s-setup.sh` - Kubernetes cluster setup and validation

### 3. Infrastructure as Code (IaC)

**Tool Selection**: Terraform (multi-cloud), Pulumi (real code), CloudFormation (AWS), Bicep (Azure), Ansible (config mgmt)

**Terraform Workflow** (Most Popular):
1. `terraform init` - Initialize
2. Define infrastructure in `.tf` files
3. `terraform plan` - Preview changes
4. `terraform apply` - Execute changes
5. Commit `.tf` to Git, store state remotely (S3/Terraform Cloud)

**Best Practices**: Use modules, separate environments, state locking, never commit secrets
**See** `references/iac-best-practices.md`

### 4. Monitoring and Observability 2.0

**Three Pillars**: Metrics (what's happening), Logs (why), Traces (where)

**Setup**: Deploy stack (Prometheus+Grafana) → Instrument apps → Create dashboards (RED/USE/Golden Signals) → Set SLO-based alerts → Implement AIOps (2026: predictive anomaly detection, auto-remediation)

**See** `references/aiops-integration.md` for 2026 AIOps integration guide

### 5. DevSecOps Integration

**Security Shift-Left** - Integrate at every stage:
- **Code**: SAST, secret scanning (SonarQube, GitGuardian)
- **Build**: Dependency scan, SBOM (Snyk, Trivy)
- **Container**: Image scanning (Trivy, Aqua)
- **Deploy**: Policy enforcement (OPA, Kyverno)
- **Runtime**: Threat detection (Falco, Sysdig)

**Implementation**: Add scanning to CI/CD → Policy as code → Secrets mgmt → Compliance automation (CIS, SOC 2, GDPR)

### 6. Platform Engineering & IDPs (2026 Trend)

**Internal Developer Platform** (80% of orgs adopting in 2026):

1. Identify developer pain points and ticket queues
2. Create Golden Paths (Backstage portal with service catalog, templates, docs)
3. Implement self-service (one-click environments, automated provisioning)
4. Provide guardrails, not gates (paved paths with flexibility)

**See** `references/platform-engineering.md` for complete IDP implementation roadmap.

## DevOps Toolchain (2026)

**CI/CD**: GitHub Actions, GitLab CI/CD, Jenkins, CircleCI, Argo Workflows
**Container/Orchestration**: Docker, Kubernetes (84% adoption), Helm, Argo CD, Istio
**Infrastructure as Code**: Terraform, Pulumi, AWS CDK, Ansible
**Monitoring**: Prometheus, Grafana, ELK Stack, Datadog, OpenTelemetry
**Security**: Trivy, Snyk, OPA, HashiCorp Vault, Falco
**AI/ML (2026)**: GitHub Copilot, Datadog AI, AIOps platforms (73% enterprise adoption)

**See** `references/tool-comparison.md` for detailed comparisons and selection guidance.

## Common Deployment Patterns

DevOps teams use various deployment strategies to minimize risk and downtime:

- **Blue-Green**: Zero downtime with instant rollback capability
- **Canary**: Gradual rollout to subset of users with monitoring
- **Rolling**: Sequential instance updates (Kubernetes default)
- **Feature Flags**: Deploy with features disabled, enable selectively
- **Recreate**: Terminate all, then create new (dev/staging only)

**See** `references/deployment-patterns.md` for detailed implementation guides, code examples, and decision trees.

## Anti-Patterns to Avoid

Common practices that harm DevOps effectiveness:

- **Separate DevOps Team**: Creates silos instead of breaking them down
- **Manual Deployments**: Error-prone, slow, not repeatable
- **Snowflake Servers**: Configuration drift, impossible to reproduce
- **Alert Fatigue**: Too many alerts → all ignored (73% problem in 2026)
- **No Rollback Plan**: Deployments become one-way doors
- **Cargo Cult DevOps**: "Google does it, so we should too" without understanding
- **Hardcoded Secrets**: Security vulnerabilities and inflexibility
- **Vanity Metrics**: Measuring activity instead of outcomes

**See** `references/antipatterns.md` for detailed explanations, examples, and correct approaches.

## DevOps Metrics (DORA)

Track the four key metrics that predict DevOps performance:

| Metric | Elite | High | Medium | Low |
|--------|-------|------|--------|-----|
| **Deployment Frequency** | On-demand (multiple/day) | Weekly-Monthly | Monthly-Yearly | >6 months |
| **Lead Time for Changes** | <1 hour | 1 day-1 week | 1-6 months | >6 months |
| **Mean Time to Recovery** | <1 hour | <1 day | 1 day-1 week | >1 week |
| **Change Failure Rate** | 0-15% | 16-30% | 31-45% | >45% |

**FinOps Metrics** (2026 Addition):
- Cost per deployment
- Cloud spend by service/team
- Idle resource percentage
- Cost optimization opportunities

## Troubleshooting

Common DevOps issues and quick diagnostics:

**CI/CD Failures**: Build flakiness, test failures, deployment hangs, secret errors
**Kubernetes Issues**: CrashLoopBackOff, pending pods, networking, resource exhaustion
**Docker Problems**: Build failures, container exits, image pull errors
**Terraform Issues**: State locks, resource conflicts, drift detection

**See** `references/troubleshooting.md` for comprehensive debugging guides with step-by-step solutions.

## Quick Reference

Essential commands for common DevOps tools:

**Docker**: `build`, `run`, `ps`, `logs`, `exec`, `push`, `pull`, `system prune`
**Kubernetes**: `get`, `describe`, `logs`, `apply`, `delete`, `scale`, `rollout`
**Terraform**: `init`, `plan`, `apply`, `destroy`, `state`, `output`
**Git**: `clone`, `commit`, `push`, `merge`, `tag`, `rebase`
**Helm**: `install`, `upgrade`, `rollback`, `list`, `uninstall`

**See** `references/command-reference.md` for complete command reference with examples and flags.

## Implementation Verification Checklist

Before completing DevOps implementation, verify:

### CI/CD Pipeline
- [ ] Pipeline runs automatically on every commit
- [ ] Automated testing integrated (unit, integration, e2e)
- [ ] Security scanning in pipeline (SAST, SCA, container scanning)
- [ ] Deployment is fully automated with rollback capability
- [ ] Pipeline notifications configured (Slack, email, etc.)
- [ ] Build artifacts stored in registry/repository
- [ ] Pipeline runs in <10 minutes for fast feedback

### Infrastructure
- [ ] Infrastructure defined as code (Terraform, Pulumi, etc.)
- [ ] All infrastructure changes versioned in Git
- [ ] Secrets managed securely (Vault, cloud secrets manager)
- [ ] Resource limits and autoscaling configured
- [ ] Disaster recovery plan documented and tested
- [ ] Infrastructure can be recreated from code

### Kubernetes (if applicable)
- [ ] Health checks configured (liveness, readiness, startup)
- [ ] Resource requests and limits set on all pods
- [ ] ConfigMaps/Secrets used (no hardcoded config)
- [ ] Ingress controller configured for external access
- [ ] Namespaces used for environment/team isolation
- [ ] Pod Security Standards enforced

### Monitoring & Observability
- [ ] Metrics collection enabled (Prometheus, Datadog, etc.)
- [ ] Dashboards created for key services (RED/USE/Golden Signals)
- [ ] Alerts configured with clear runbooks
- [ ] Logging centralized and searchable (ELK, Loki)
- [ ] Distributed tracing implemented (for microservices)
- [ ] SLOs defined and tracked

### Security (DevSecOps)
- [ ] Security scanning automated in CI/CD
- [ ] Container images scanned for vulnerabilities
- [ ] Secrets never committed to Git
- [ ] Least privilege access implemented (RBAC)
- [ ] Network policies configured (if using Kubernetes)
- [ ] Compliance requirements documented and met

### Documentation
- [ ] Architecture diagrams created and up-to-date
- [ ] Runbooks written for common operations
- [ ] Incident response procedures documented
- [ ] Onboarding guide for new team members
- [ ] Deployment process documented

### Metrics & Validation
- [ ] DORA metrics baseline established
- [ ] Deployment frequency tracked
- [ ] Lead time for changes measured
- [ ] Mean time to recovery (MTTR) tracked
- [ ] Change failure rate monitored

## Next Steps

After implementing DevOps practices:

1. **Measure baseline metrics** (DORA + FinOps)
2. **Identify biggest bottleneck** (usually lead time or deployment frequency)
3. **Implement one improvement** (don't boil the ocean)
4. **Measure impact**
5. **Iterate**

**Platform Engineering Maturity Path**:
```
Manual Operations → CI/CD Automation → IaC → Observability →
Self-Service Portal → Golden Paths → Full IDP
```

## Official Documentation

| Resource | URL | Use For |
|----------|-----|---------|
| **Kubernetes Docs** | https://kubernetes.io/docs/ | K8s objects, best practices, troubleshooting |
| **Terraform Registry** | https://registry.terraform.io/ | Modules, providers, examples |
| **Docker Documentation** | https://docs.docker.com/ | Dockerfile best practices, CLI reference |
| **GitHub Actions** | https://docs.github.com/en/actions | Workflow syntax, marketplace actions |
| **GitLab CI/CD** | https://docs.gitlab.com/ee/ci/ | Pipeline configuration, runners |
| **Prometheus** | https://prometheus.io/docs/ | PromQL, alerting, configuration |
| **CNCF Landscape** | https://landscape.cncf.io/ | Cloud-native tool ecosystem |
| **DORA Metrics** | https://dora.dev/ | Performance benchmarking |
| **12-Factor App** | https://12factor.net/ | Application design principles |

**For unlisted tools or patterns**: Use WebFetch to check official documentation for the latest features and best practices.

## Resources

All detailed guides, best practices, and examples are in `references/`:
- `cicd-patterns.md` - CI/CD pipeline patterns and full examples
- `kubernetes-objects.md` - Complete Kubernetes object reference
- `deployment-patterns.md` - Blue-green, canary, rolling, feature flags (NEW)
- `antipatterns.md` - What NOT to do with explanations (NEW)
- `platform-engineering.md` - Building Internal Developer Platforms (2026)
- `aiops-integration.md` - AI/ML integration in DevOps (2026)
- `troubleshooting.md` - Comprehensive debugging guide (NEW)
- `command-reference.md` - Quick reference for all tools (NEW)

Scripts for automation are in `scripts/`:
- `setup-cicd.sh` - Interactive CI/CD pipeline setup
- `k8s-setup.sh` - Kubernetes cluster validation and setup
