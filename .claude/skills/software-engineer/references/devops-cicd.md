# DevOps & CI/CD

## CI/CD Pipeline Design

### Standard Pipeline

```
┌────────┐   ┌──────┐   ┌──────┐   ┌───────┐   ┌──────────┐   ┌─────────┐   ┌───────────┐   ┌──────────┐
│ Commit │ → │ Lint │ → │ Test │ → │ Build │ → │ Security │ → │ Deploy  │ → │ Smoke     │ → │ Deploy   │
│        │   │      │   │      │   │       │   │   Scan   │   │ Staging │   │ Test      │   │ Prod     │
└────────┘   └──────┘   └──────┘   └───────┘   └──────────┘   └─────────┘   └───────────┘   └──────────┘
   <1min       <2min      <5min      <3min        <5min          <5min         <3min          <5min
```

### Stage Details

| Stage | What | Tools | Fail Action |
|-------|------|-------|-------------|
| **Lint** | Code style, formatting | ESLint, Prettier, Black, golangci-lint | Block merge |
| **Type Check** | Type safety | tsc, mypy, go vet | Block merge |
| **Unit Test** | Business logic | Jest, pytest, go test | Block merge |
| **Integration Test** | API + DB contracts | Supertest, TestContainers | Block merge |
| **Build** | Compile, bundle | Webpack, Vite, Docker build | Block merge |
| **Security Scan** | Vulnerabilities | Snyk, Trivy, npm audit, SAST | Warn or block |
| **Deploy Staging** | Pre-production test | ArgoCD, Flux, GitHub Actions | Block prod |
| **Smoke Test** | Critical paths pass | Playwright, curl health checks | Block prod |
| **Deploy Prod** | Production release | ArgoCD, Flux, manual approval | Rollback |

---

## Deployment Strategies

### Blue-Green Deployment

```
                 ┌──────────────┐
  Load Balancer →│ Blue (v1)    │ ← Current production
                 └──────────────┘
                 ┌──────────────┐
                 │ Green (v2)   │ ← Deploy and test here
                 └──────────────┘

Step 1: Deploy v2 to Green
Step 2: Test Green environment
Step 3: Switch load balancer to Green
Step 4: Keep Blue as instant rollback
```

**Pros**: Zero downtime, instant rollback
**Cons**: Double infrastructure cost, database migration complexity

### Canary Deployment

```
  Load Balancer ─┬─ 95% → v1 (current)
                 └─  5% → v2 (canary)

Step 1: Route 5% traffic to v2
Step 2: Monitor error rates, latency, metrics
Step 3: Gradually increase: 5% → 25% → 50% → 100%
Step 4: Rollback if metrics degrade
```

**Pros**: Low risk, gradual validation
**Cons**: Complex routing, monitoring required

### Rolling Update

```
Step 1: [v1] [v1] [v1] [v1]  ← All v1
Step 2: [v2] [v1] [v1] [v1]  ← Replace one at a time
Step 3: [v2] [v2] [v1] [v1]
Step 4: [v2] [v2] [v2] [v1]
Step 5: [v2] [v2] [v2] [v2]  ← All v2
```

**Pros**: No extra infrastructure, zero downtime
**Cons**: Mixed versions during rollout, slower rollback

---

## Docker Best Practices

### Multi-Stage Build

```dockerfile
# Stage 1: Build
FROM node:20-alpine AS builder
WORKDIR /app
COPY package*.json ./
RUN npm ci --production=false
COPY . .
RUN npm run build

# Stage 2: Production
FROM node:20-alpine AS production
WORKDIR /app

# Security: non-root user
RUN addgroup -g 1001 appgroup && \
    adduser -u 1001 -G appgroup -s /bin/sh -D appuser

COPY --from=builder /app/dist ./dist
COPY --from=builder /app/node_modules ./node_modules
COPY package*.json ./

# Security: drop privileges
USER appuser

EXPOSE 3000
HEALTHCHECK --interval=30s --timeout=3s --retries=3 \
  CMD wget --no-verbose --tries=1 --spider http://localhost:3000/health || exit 1

CMD ["node", "dist/server.js"]
```

### Dockerfile Checklist

- [ ] Multi-stage build (small final image)
- [ ] Use specific base image tags (not `latest`)
- [ ] Alpine-based images when possible
- [ ] Non-root user
- [ ] `.dockerignore` configured
- [ ] HEALTHCHECK defined
- [ ] No secrets in image layers
- [ ] Layer ordering: least-changed first (better caching)

---

## Infrastructure as Code (IaC)

| Tool | Best For | Language |
|------|----------|----------|
| **Terraform** | Multi-cloud, infrastructure provisioning | HCL |
| **Pulumi** | Infrastructure with real programming languages | TS, Python, Go |
| **AWS CDK** | AWS-specific, TypeScript/Python | TS, Python |
| **Ansible** | Configuration management, server setup | YAML |
| **Docker Compose** | Local dev, simple deployments | YAML |

### Terraform Structure

```
infrastructure/
├── environments/
│   ├── dev/
│   │   ├── main.tf
│   │   ├── variables.tf
│   │   └── terraform.tfvars
│   ├── staging/
│   └── production/
├── modules/
│   ├── networking/     # VPC, subnets, security groups
│   ├── database/       # RDS, ElastiCache
│   ├── compute/        # ECS, EKS, Lambda
│   └── monitoring/     # CloudWatch, alerts
└── shared/
    └── backend.tf      # State storage (S3 + DynamoDB lock)
```

---

## Monitoring & Observability

### Three Pillars

| Pillar | What | Tools |
|--------|------|-------|
| **Metrics** | Numeric measurements over time | Prometheus, Grafana, Datadog |
| **Logs** | Structured event records | ELK Stack, Loki, CloudWatch |
| **Traces** | Request path across services | Jaeger, Zipkin, OpenTelemetry |

### Essential Alerts

| Alert | Condition | Severity |
|-------|-----------|----------|
| High Error Rate | >1% 5xx responses for 5 min | Critical |
| High Latency | p95 >2s for 5 min | Warning |
| CPU Saturation | >85% for 10 min | Warning |
| Memory Pressure | >90% for 5 min | Critical |
| Disk Space | >85% used | Warning |
| Health Check Failure | 3 consecutive failures | Critical |
| Certificate Expiry | <30 days | Warning |

---

## 12-Factor App Compliance

| Factor | Principle | Implementation |
|--------|-----------|----------------|
| I. Codebase | One codebase, many deploys | Git, monorepo or polyrepo |
| II. Dependencies | Explicitly declare and isolate | package.json, requirements.txt, go.mod |
| III. Config | Store config in environment | Env vars, secrets manager |
| IV. Backing Services | Treat as attached resources | Connection strings via env vars |
| V. Build, Release, Run | Strict separation | CI/CD pipeline stages |
| VI. Processes | Stateless processes | No local state, use Redis/DB |
| VII. Port Binding | Export services via port | Self-contained HTTP server |
| VIII. Concurrency | Scale via process model | Horizontal pod autoscaling |
| IX. Disposability | Fast startup, graceful shutdown | SIGTERM handlers, health checks |
| X. Dev/Prod Parity | Keep environments similar | Docker, IaC, same backing services |
| XI. Logs | Treat as event streams | stdout → log aggregator |
| XII. Admin Processes | Run as one-off processes | Kubernetes Jobs, scripts |

---

## Sources
- 12factor.net: The Twelve-Factor App
- Docker Documentation: https://docs.docker.com/
- Kubernetes Documentation: https://kubernetes.io/docs/
- AWS Well-Architected Framework
- Google SRE Book: https://sre.google/books/
