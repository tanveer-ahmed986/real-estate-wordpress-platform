# Deployment Patterns

## Blue-Green Deployment

**Overview**: Maintain two identical production environments (Blue and Green) and switch traffic between them.

```
Production traffic → Blue (v1.0)
Deploy → Green (v2.0)
Test Green
Switch traffic → Green
Keep Blue for rollback
```

### Implementation

**Kubernetes Example**:
```yaml
# Service points to Blue deployment
apiVersion: v1
kind: Service
metadata:
  name: my-app
spec:
  selector:
    app: my-app
    version: blue  # Switch to 'green' for cutover
  ports:
  - port: 80
    targetPort: 8080
---
# Blue Deployment
apiVersion: apps/v1
kind: Deployment
metadata:
  name: my-app-blue
spec:
  replicas: 3
  selector:
    matchLabels:
      app: my-app
      version: blue
  template:
    metadata:
      labels:
        app: my-app
        version: blue
    spec:
      containers:
      - name: app
        image: my-app:v1.0
---
# Green Deployment
apiVersion: apps/v1
kind: Deployment
metadata:
  name: my-app-green
spec:
  replicas: 3
  selector:
    matchLabels:
      app: my-app
      version: green
  template:
    metadata:
      labels:
        app: my-app
        version: green
    spec:
      containers:
      - name: app
        image: my-app:v2.0
```

**Cutover Process**:
```bash
# 1. Deploy green
kubectl apply -f green-deployment.yaml

# 2. Wait for green to be ready
kubectl wait --for=condition=available deployment/my-app-green

# 3. Test green (port-forward or internal testing)
kubectl port-forward deployment/my-app-green 8080:8080

# 4. Switch traffic to green
kubectl patch service my-app -p '{"spec":{"selector":{"version":"green"}}}'

# 5. Monitor for issues
# If issues detected:
kubectl patch service my-app -p '{"spec":{"selector":{"version":"blue"}}}'
```

### Pros & Cons

| Pros | Cons |
|------|------|
| ✅ Zero downtime | ❌ Requires 2x infrastructure |
| ✅ Instant rollback | ❌ Database migrations challenging |
| ✅ Test in production | ❌ Stateful apps need careful handling |
| ✅ Simple implementation | ❌ Cost of running two environments |

### Best For
- Web applications and APIs
- Services with read-heavy databases
- Organizations with sufficient infrastructure budget
- When instant rollback is critical

---

## Canary Deployment

**Overview**: Gradually roll out new version to subset of users, monitoring metrics before full rollout.

```
Deploy v2 to 5% of users
Monitor metrics (errors, latency, saturation)
Gradually increase: 10% → 25% → 50% → 100%
If metrics good → continue
If metrics bad → rollback immediately
```

### Implementation

**Kubernetes with Istio**:
```yaml
# Virtual Service for traffic splitting
apiVersion: networking.istio.io/v1beta1
kind: VirtualService
metadata:
  name: my-app
spec:
  hosts:
  - my-app
  http:
  - match:
    - headers:
        user-type:
          exact: beta-tester
    route:
    - destination:
        host: my-app
        subset: v2
  - route:
    - destination:
        host: my-app
        subset: v1
      weight: 95
    - destination:
        host: my-app
        subset: v2
      weight: 5  # Start with 5% canary
---
# Destination Rule
apiVersion: networking.istio.io/v1beta1
kind: DestinationRule
metadata:
  name: my-app
spec:
  host: my-app
  subsets:
  - name: v1
    labels:
      version: v1
  - name: v2
    labels:
      version: v2
```

**Progressive Rollout Script**:
```bash
#!/bin/bash
# Canary rollout script

CANARY_PERCENTAGES=(5 10 25 50 100)
WAIT_TIME=300  # 5 minutes between stages

for PERCENT in "${CANARY_PERCENTAGES[@]}"; do
  echo "Rolling out to ${PERCENT}%..."

  # Update traffic split
  kubectl patch virtualservice my-app --type merge -p "{
    \"spec\": {
      \"http\": [{
        \"route\": [
          {\"destination\": {\"host\": \"my-app\", \"subset\": \"v1\"}, \"weight\": $((100-PERCENT))},
          {\"destination\": {\"host\": \"my-app\", \"subset\": \"v2\"}, \"weight\": ${PERCENT}}
        ]
      }]
    }
  }"

  # Wait and monitor
  echo "Monitoring for ${WAIT_TIME} seconds..."
  sleep $WAIT_TIME

  # Check metrics (example with Prometheus)
  ERROR_RATE=$(curl -s 'http://prometheus:9090/api/v1/query?query=rate(http_requests_total{status=~"5.."}[5m])' | jq -r '.data.result[0].value[1]')

  if (( $(echo "$ERROR_RATE > 0.05" | bc -l) )); then
    echo "Error rate too high! Rolling back..."
    kubectl patch virtualservice my-app --type merge -p "{
      \"spec\": {
        \"http\": [{
          \"route\": [{\"destination\": {\"host\": \"my-app\", \"subset\": \"v1\"}, \"weight\": 100}]
        }]
      }
    }"
    exit 1
  fi

  echo "Metrics healthy. Proceeding..."
done

echo "Canary rollout complete!"
```

### Metrics to Monitor

| Metric | Threshold | Action |
|--------|-----------|--------|
| **Error Rate** | >5% increase | Rollback immediately |
| **Latency (p99)** | >20% increase | Rollback immediately |
| **Latency (p50)** | >10% increase | Pause and investigate |
| **Success Rate** | <99% | Rollback immediately |
| **CPU/Memory** | >80% utilization | Pause and scale |

### Pros & Cons

| Pros | Cons |
|------|------|
| ✅ Lower risk | ❌ Complex to implement |
| ✅ Real user feedback | ❌ Requires service mesh or ALB |
| ✅ Gradual validation | ❌ Longer deployment time |
| ✅ A/B testing capable | ❌ Difficult to debug issues |

### Best For
- High-traffic applications
- Changes with uncertain impact
- Organizations with mature observability
- When user experience is critical

---

## Rolling Deployment

**Overview**: Gradually replace instances of old version with new version, one at a time.

```
v1: [Pod1, Pod2, Pod3, Pod4, Pod5]
     ↓ Replace Pod1
v2: [Pod1], v1: [Pod2, Pod3, Pod4, Pod5]
     ↓ Replace Pod2
v2: [Pod1, Pod2], v1: [Pod3, Pod4, Pod5]
     ...continue until all replaced
```

### Implementation

**Kubernetes Native**:
```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: my-app
spec:
  replicas: 5
  strategy:
    type: RollingUpdate
    rollingUpdate:
      maxSurge: 1        # Max new pods above desired count
      maxUnavailable: 1  # Max pods unavailable during update
  selector:
    matchLabels:
      app: my-app
  template:
    metadata:
      labels:
        app: my-app
    spec:
      containers:
      - name: app
        image: my-app:v2.0
        readinessProbe:
          httpGet:
            path: /health
            port: 8080
          initialDelaySeconds: 5
          periodSeconds: 5
```

**Update and Monitor**:
```bash
# Start rolling update
kubectl set image deployment/my-app app=my-app:v2.0

# Watch rollout status
kubectl rollout status deployment/my-app

# Pause rollout if issues detected
kubectl rollout pause deployment/my-app

# Resume rollout
kubectl rollout resume deployment/my-app

# Rollback if needed
kubectl rollout undo deployment/my-app
```

### Configuration Parameters

| Parameter | Conservative | Balanced | Aggressive |
|-----------|--------------|----------|------------|
| **maxSurge** | 1 (20%) | 1-2 (25%) | 2-3 (50%) |
| **maxUnavailable** | 0 | 1 (20%) | 2 (40%) |
| **Deployment Time** | Slowest | Medium | Fastest |
| **Risk Level** | Lowest | Medium | Highest |

### Pros & Cons

| Pros | Cons |
|------|------|
| ✅ Simple to implement | ❌ Slower than blue-green |
| ✅ No extra infrastructure | ❌ Both versions run simultaneously |
| ✅ Kubernetes native | ❌ Rollback takes time |
| ✅ Gradual resource usage | ❌ Debugging mixed versions hard |

### Best For
- Default choice for most deployments
- Resource-constrained environments
- Applications tolerant of mixed versions
- Teams new to advanced deployment patterns

---

## Feature Flags (Feature Toggles)

**Overview**: Deploy code with features disabled, enable selectively for testing and rollout.

```
Deploy code with features OFF
     ↓
Enable for internal team (dogfooding)
     ↓
Enable for 5% of users (beta test)
     ↓
Monitor and iterate
     ↓
Roll out to 100% of users
     ↓
Remove flag after stabilization (tech debt cleanup)
```

### Implementation

**Application Code Example (Node.js)**:
```javascript
// Feature flag service
const FeatureFlags = {
  async isEnabled(featureName, userId) {
    // Check remote feature flag service (LaunchDarkly, Unleash, etc.)
    const flags = await fetchFeatureFlags(userId);
    return flags[featureName] || false;
  }
};

// Usage in application
app.get('/api/data', async (req, res) => {
  const useNewAlgorithm = await FeatureFlags.isEnabled(
    'new-recommendation-algorithm',
    req.user.id
  );

  if (useNewAlgorithm) {
    return res.json(await getRecommendationsV2(req.user));
  } else {
    return res.json(await getRecommendationsV1(req.user));
  }
});
```

**ConfigMap-based Feature Flags (Simple)**:
```yaml
apiVersion: v1
kind: ConfigMap
metadata:
  name: feature-flags
data:
  new-ui: "false"
  experimental-api: "true"
  beta-feature: "staged"  # Can be: true, false, staged, percentage
  rollout-percentage: "25"
```

**Application reads from ConfigMap**:
```javascript
const featureFlags = JSON.parse(
  process.env.FEATURE_FLAGS || '{}'
);

function isFeatureEnabled(feature, userId) {
  const flag = featureFlags[feature];

  if (flag === 'true') return true;
  if (flag === 'false') return false;

  if (flag === 'staged') {
    // Enable for subset based on user ID hash
    const percentage = parseInt(featureFlags['rollout-percentage'] || '0');
    const hash = hashUserId(userId);
    return (hash % 100) < percentage;
  }

  return false;
}
```

### Feature Flag Management Tools

| Tool | Best For | Pricing |
|------|----------|---------|
| **LaunchDarkly** | Enterprise, complex targeting | $$$ |
| **Unleash** | Open-source, self-hosted | Free/$ |
| **Flagsmith** | Open-source, easy to use | Free/$ |
| **ConfigCat** | Small teams, simple flags | $/$$ |
| **Environment Variables** | Very simple flags | Free |

### Best Practices

1. **Naming Convention**
   ```
   {feature}-{purpose}
   Examples:
   - new-checkout-flow
   - experimental-search-algorithm
   - beta-dark-mode
   ```

2. **Flag Lifecycle**
   ```
   Create → Test → Rollout → Stabilize → Remove

   ⚠️ Don't forget to remove flags after full rollout!
   Technical debt: Old flags make code hard to maintain
   ```

3. **Types of Flags**
   - **Release flags**: Control new feature rollout (temporary)
   - **Ops flags**: Circuit breakers, performance tuning (long-lived)
   - **Experiment flags**: A/B testing (temporary)
   - **Permission flags**: Access control (long-lived)

### Pros & Cons

| Pros | Cons |
|------|------|
| ✅ Decouple deploy from release | ❌ Code complexity increases |
| ✅ Easy rollback (no deploy) | ❌ Technical debt if not cleaned |
| ✅ A/B testing enabled | ❌ Testing all combinations hard |
| ✅ Gradual rollout | ❌ Flag management overhead |

### Best For
- Separating deployment from release
- Large features needing gradual rollout
- A/B testing and experimentation
- Quick rollback without redeployment

---

## Recreate Deployment

**Overview**: Terminate all old versions, then create new versions. Downtime occurs.

```
v1: [Pod1, Pod2, Pod3]
     ↓ Terminate all
     (DOWNTIME)
     ↓ Create new
v2: [Pod1, Pod2, Pod3]
```

### When to Use

- ✅ Development/staging environments
- ✅ Services that can tolerate downtime
- ✅ Major breaking changes (e.g., database schema incompatible)
- ✅ Stateful applications with complex migration
- ❌ Production services requiring high availability

### Implementation

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: my-app
spec:
  replicas: 3
  strategy:
    type: Recreate  # All pods terminated before new ones created
  selector:
    matchLabels:
      app: my-app
  template:
    metadata:
      labels:
        app: my-app
    spec:
      containers:
      - name: app
        image: my-app:v2.0
```

---

## Comparison Matrix

| Pattern | Downtime | Infrastructure Cost | Complexity | Rollback Speed | Best Use Case |
|---------|----------|---------------------|------------|----------------|---------------|
| **Blue-Green** | None | 2x (High) | Low | Instant | Critical services, instant rollback needed |
| **Canary** | None | 1x + overhead | High | Fast | High-traffic apps, risk mitigation |
| **Rolling** | None | 1x | Low | Medium | Default choice, most apps |
| **Feature Flags** | None | 1x | Medium | Instant | Gradual rollout, A/B testing |
| **Recreate** | Yes | 1x | Very Low | Medium | Dev/staging, breaking changes |

---

## Decision Tree

```
Can you tolerate downtime?
├─ Yes → Recreate (simplest)
└─ No → Continue

Do you have budget for 2x infrastructure?
├─ Yes → Do you need instant rollback?
│   ├─ Yes → Blue-Green
│   └─ No → Continue
└─ No → Continue

Is this a high-risk change?
├─ Yes → Do you have service mesh (Istio/Linkerd)?
│   ├─ Yes → Canary
│   └─ No → Feature Flags + Rolling
└─ No → Rolling (default)
```

---

## Hybrid Approaches

### Canary + Feature Flags
```
1. Deploy new version with feature disabled
2. Enable feature for canary subset (5%)
3. Monitor metrics
4. Gradually increase percentage
5. Remove flag after full rollout
```

**Benefits**: Safest approach, can rollback via flag or deployment

### Blue-Green + Feature Flags
```
1. Deploy green with new feature behind flag
2. Test green environment thoroughly
3. Switch traffic to green
4. Enable feature gradually via flags
```

**Benefits**: Instant infrastructure rollback + gradual feature rollout
