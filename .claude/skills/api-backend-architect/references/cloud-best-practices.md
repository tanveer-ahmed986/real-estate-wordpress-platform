# Cloud Best Practices

AWS Well-Architected Framework, Azure Architecture, 12-Factor App, and cloud-native patterns.

---

## AWS Well-Architected Framework (6 Pillars)

### 1. Operational Excellence

| Principle | Practice |
|-----------|----------|
| Operations as code | Infrastructure as Code (Terraform, CDK, Pulumi) |
| Frequent small changes | CI/CD with small, reversible deployments |
| Anticipate failure | Runbooks, game days, chaos engineering |
| Learn from failures | Post-incident reviews, blameless culture |

**Key practices**:
- Automate deployments and rollbacks
- Monitor everything (metrics, logs, traces)
- Define operational procedures as runbooks
- Implement feature flags for safe rollouts

### 2. Security

| Principle | Practice |
|-----------|----------|
| Strong identity | Least privilege, MFA, no long-lived credentials |
| Traceability | Audit logs, monitoring, alerting |
| Security at all layers | Network, compute, application, data |
| Automate security | Security scanning in CI/CD, policy as code |

**Key practices**:
- Encrypt data at rest and in transit
- Implement defense in depth
- Automate security best practices
- Prepare for security events (incident response plan)

### 3. Reliability

| Principle | Practice |
|-----------|----------|
| Automatic recovery | Health checks, auto-scaling, self-healing |
| Test recovery procedures | Disaster recovery drills |
| Scale horizontally | Distribute load, avoid single points of failure |
| Manage change in automation | Automated deployments, infrastructure as code |

**Key practices**:
- Multi-AZ / multi-region deployment
- Automated backups with tested restores
- Circuit breakers for downstream dependencies
- Defined RTO/RPO for disaster recovery

### 4. Performance Efficiency

| Principle | Practice |
|-----------|----------|
| Democratize advanced tech | Use managed services (databases, ML, search) |
| Go global in minutes | CDN, edge computing, multi-region |
| Use serverless | Remove operational burden where possible |
| Experiment more often | A/B testing, canary deployments |

**Key practices**:
- Right-size compute resources
- Use caching at every level
- Optimize database queries and indexes
- Use async processing for non-critical paths

### 5. Cost Optimization

| Principle | Practice |
|-----------|----------|
| Adopt a consumption model | Pay only for what you use |
| Measure overall efficiency | Cost per transaction, cost per user |
| Stop spending on undifferentiated heavy lifting | Use managed services |
| Analyze and attribute expenditure | Tagging, cost allocation |

**Key practices**:
- Reserved instances for predictable workloads
- Spot/preemptible instances for fault-tolerant workloads
- Auto-scaling to match demand
- Regular cost reviews and right-sizing

### 6. Sustainability

| Principle | Practice |
|-----------|----------|
| Understand your impact | Measure carbon footprint |
| Establish sustainability goals | Track and improve over time |
| Maximize utilization | Right-size resources, consolidate |
| Adopt new efficient tech | Graviton/ARM instances, efficient runtimes |

---

## Azure Architecture Best Practices

### API Design (Microsoft Guidelines)

| Principle | Guidance |
|-----------|----------|
| Platform independence | API contract decoupled from implementation |
| Service evolution | APIs evolve without breaking existing clients |
| Discoverability | API should be navigable via links (HATEOAS when useful) |

### Azure-Specific Patterns

| Pattern | Use Case |
|---------|----------|
| Queue-Based Load Leveling | Buffer between variable producers and fixed-rate consumers |
| Competing Consumers | Multiple consumers process from same queue for throughput |
| Priority Queue | Process high-priority messages before low-priority |
| Throttling | Protect resources from being overwhelmed |
| Cache-Aside | Load data on demand into cache |
| CQRS | Separate read and write models for complex domains |
| Event Sourcing | Append-only event store for full audit trail |
| Retry | Handle transient failures with automatic retry |
| Circuit Breaker | Prevent cascade failures to downstream services |

---

## 12-Factor App (Modern Interpretation)

### Factor Details with Modern Context

| # | Factor | Classic | Modern (Cloud-Native) |
|---|--------|---------|----------------------|
| 1 | **Codebase** | One repo per app | Monorepo or multi-repo with clear boundaries |
| 2 | **Dependencies** | requirements.txt / package.json | Container images with pinned versions |
| 3 | **Config** | Environment variables | Secrets manager + env vars, config maps |
| 4 | **Backing Services** | Treat as attached resources | Service mesh, connection strings as config |
| 5 | **Build, Release, Run** | Separate stages | CI/CD pipelines with immutable artifacts |
| 6 | **Processes** | Stateless processes | Containers, serverless functions |
| 7 | **Port Binding** | Self-contained HTTP server | Container port mapping, service discovery |
| 8 | **Concurrency** | Scale via process model | Horizontal pod autoscaling, serverless scaling |
| 9 | **Disposability** | Fast startup, graceful shutdown | Container health probes, preStop hooks |
| 10 | **Dev/Prod Parity** | Minimize gaps | Docker Compose for local, same images in prod |
| 11 | **Logs** | Treat as event streams | Structured JSON logs → aggregator (ELK, Datadog) |
| 12 | **Admin Processes** | One-off processes | Kubernetes Jobs, database migration containers |

### Beyond 12-Factor

| Additional Factor | Guidance |
|-------------------|----------|
| **API First** | Design API contract before implementation |
| **Telemetry** | Built-in observability (metrics, traces, logs) |
| **Security** | Security as code, shift-left security testing |
| **Authentication** | Externalized identity, token-based auth |

---

## Containerization Patterns

### Dockerfile Best Practices

```dockerfile
# Multi-stage build
FROM node:20-alpine AS builder
WORKDIR /app
COPY package*.json ./
RUN npm ci --only=production && npm cache clean --force
COPY . .
RUN npm run build

# Production image
FROM node:20-alpine AS runtime
WORKDIR /app

# Non-root user
RUN addgroup -S appgroup && adduser -S appuser -G appgroup

# Copy only what's needed
COPY --from=builder /app/dist ./dist
COPY --from=builder /app/node_modules ./node_modules
COPY --from=builder /app/package.json ./

USER appuser
EXPOSE 3000

HEALTHCHECK --interval=30s --timeout=3s --start-period=10s --retries=3 \
  CMD wget --no-verbose --tries=1 --spider http://localhost:3000/health/live || exit 1

CMD ["node", "dist/server.js"]
```

### Container Security
- Use minimal base images (Alpine, distroless)
- Run as non-root user
- Pin image versions (no `latest` tag in production)
- Scan images for vulnerabilities (Trivy, Snyk)
- Don't store secrets in images
- Use multi-stage builds to minimize image size

---

## Deployment Strategies

| Strategy | Risk | Downtime | Rollback Speed |
|----------|------|----------|----------------|
| **Rolling Update** | Low | Zero | Fast |
| **Blue-Green** | Very Low | Zero | Instant (switch back) |
| **Canary** | Lowest | Zero | Fast (route away) |
| **Recreate** | Medium | Brief | Slow (redeploy) |

### Rolling Update
```
v1 v1 v1 v1  →  v1 v1 v1 v2  →  v1 v1 v2 v2  →  v1 v2 v2 v2  →  v2 v2 v2 v2
```

### Blue-Green
```
Blue (v1) ← Traffic       Green (v2) idle
Blue (v1) idle            Green (v2) ← Traffic (after verification)
```

### Canary
```
v1 v1 v1 v1 ← 95% traffic
v2           ← 5% traffic (monitor metrics)

If healthy: gradually increase to 100%
If unhealthy: route back to 100% v1
```

---

## Infrastructure Patterns

### Service Discovery

| Pattern | When to Use |
|---------|-------------|
| DNS-based | Simple setups, cloud-managed services |
| Client-side (Consul, Eureka) | Microservices needing health-aware routing |
| Server-side (Load Balancer) | Traditional architectures |
| Service Mesh (Istio, Linkerd) | Complex microservices with mTLS, observability |

### Configuration Management

| Source | Use For | Example |
|--------|---------|---------|
| Environment variables | Runtime config, feature flags | `PORT=3000`, `LOG_LEVEL=info` |
| Secrets manager | Sensitive credentials | Database passwords, API keys |
| Config maps | Non-sensitive app config | Feature flags, thresholds |
| Config files | Complex structured config | YAML/JSON config files |

### Secrets Rotation Strategy

```
1. Generate new secret
2. Deploy new secret to secrets manager
3. Update application to accept both old and new
4. Deploy application
5. Verify all instances using new secret
6. Revoke old secret
```
