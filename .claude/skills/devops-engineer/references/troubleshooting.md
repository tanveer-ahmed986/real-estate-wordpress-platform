# DevOps Troubleshooting Guide

Comprehensive troubleshooting guide for common DevOps issues.

---

## CI/CD Pipeline Failures

### Build Fails Intermittently

**Symptoms**:
- Build succeeds sometimes, fails other times
- Same code, different results
- "Works on my machine" syndrome

**Common Causes**:

1. **Dependency Resolution Issues**
   ```bash
   # Problem: Package versions change between builds
   npm install  # Installs latest compatible versions

   # Solution: Use lock files
   npm ci  # Install exact versions from package-lock.json
   ```

2. **Network Timeouts**
   ```bash
   # Problem: Downloading dependencies times out
   # Solution: Increase timeout, use mirrors, cache dependencies

   # GitHub Actions
   - uses: actions/cache@v3
     with:
       path: ~/.npm
       key: ${{ runner.os }}-node-${{ hashFiles('**/package-lock.json') }}
   ```

3. **Race Conditions**
   ```bash
   # Problem: Parallel jobs interfere
   # Solution: Add proper dependencies between jobs

   # GitHub Actions
   jobs:
     test:
       needs: build  # Wait for build to complete
   ```

4. **Environment Differences**
   ```bash
   # Problem: CI environment differs from local
   # Solution: Containerize builds

   docker run -v $(pwd):/app node:20 npm run build
   ```

**Debug Steps**:
```bash
# 1. Check build logs for errors
# 2. Compare successful vs failed builds
# 3. Run build multiple times locally
# 4. Enable verbose logging:
npm install --verbose
terraform plan -parallelism=1  # Disable parallel
```

---

### Tests Pass Locally, Fail in CI

**Symptoms**:
- All tests green on developer machine
- Same tests fail in CI pipeline
- Flaky tests

**Common Causes**:

1. **Hardcoded Paths**
   ```javascript
   // Problem
   const configPath = "/Users/john/project/config.json";

   // Solution
   const configPath = path.join(__dirname, "../config.json");
   ```

2. **Timezone Differences**
   ```javascript
   // Problem
   expect(date).toBe("2024-01-15");  // Depends on timezone

   // Solution
   expect(date).toBe("2024-01-15T00:00:00Z");  // Use ISO 8601
   ```

3. **Missing Environment Variables**
   ```bash
   # Problem: Tests assume env vars exist locally
   # Solution: Set in CI config

   # GitHub Actions
   env:
     DATABASE_URL: postgres://localhost/test
     API_KEY: ${{ secrets.API_KEY }}
   ```

4. **Different Node/Python/etc Versions**
   ```yaml
   # Problem: Different versions between local and CI
   # Solution: Pin versions

   # GitHub Actions
   - uses: actions/setup-node@v4
     with:
       node-version: '20.10.0'  # Exact version
   ```

**Debug Steps**:
```bash
# 1. Run tests in container matching CI environment
docker run -it node:20 bash
npm test

# 2. Check for environmental differences
echo $PATH
echo $NODE_ENV
printenv | sort

# 3. Enable test isolation
jest --runInBand  # Run tests serially, not parallel

# 4. Add retry logic for flaky tests
jest --maxWorkers=1 --testTimeout=10000
```

---

### Deployment Hangs

**Symptoms**:
- Deployment starts but never completes
- No error messages
- Pipeline times out

**Common Causes**:

1. **Resource Constraints**
   ```bash
   # Check Kubernetes node capacity
   kubectl describe nodes | grep -A 5 "Allocated resources"

   # Check pod status
   kubectl get pods
   # If "Pending" → Check events
   kubectl describe pod <pod-name>
   ```

2. **Health Check Failures**
   ```yaml
   # Problem: Readiness probe fails, deployment never completes
   readinessProbe:
     httpGet:
       path: /health
       port: 8080
     initialDelaySeconds: 5
     periodSeconds: 5
     failureThreshold: 3

   # Debug: Check logs
   kubectl logs <pod-name>

   # Temporary fix: Remove probe to see if app actually starts
   ```

3. **Image Pull Issues**
   ```bash
   # Check image pull status
   kubectl describe pod <pod-name> | grep -A 5 "Events"

   # Common issues:
   # - Wrong image name/tag
   # - Missing credentials
   # - Image doesn't exist

   # Solution: Verify image exists
   docker pull registry/app:tag

   # Add image pull secret
   kubectl create secret docker-registry regcred \
     --docker-server=registry.example.com \
     --docker-username=user \
     --docker-password=pass
   ```

4. **Stuck in Rolling Update**
   ```bash
   # Check rollout status
   kubectl rollout status deployment/app

   # If stuck, check why old pods aren't terminating
   kubectl get pods -o wide
   kubectl describe pod <old-pod>

   # Force delete stuck pod (last resort)
   kubectl delete pod <pod-name> --force --grace-period=0
   ```

**Debug Steps**:
```bash
# 1. Increase pipeline timeout temporarily
# 2. Check resource availability
kubectl top nodes
kubectl describe nodes

# 3. Check pod events
kubectl get events --sort-by='.lastTimestamp' | head -20

# 4. Manually test deployment
kubectl apply -f deployment.yaml --dry-run=client
kubectl apply -f deployment.yaml --validate=true
```

---

### Secrets Not Found

**Symptoms**:
- Pipeline fails with "secret not found"
- Environment variables empty
- Authentication errors

**Common Causes**:

1. **Wrong Secret Name**
   ```yaml
   # Problem: Typo in secret name
   env:
     - name: API_KEY
       valueFrom:
         secretKeyRef:
           name: app-secret  # Check this matches actual secret name
           key: api-key

   # Verify secret exists
   kubectl get secret app-secret
   ```

2. **Wrong Namespace**
   ```bash
   # Problem: Secret in different namespace
   kubectl get secret app-secret -n production  # Specify namespace

   # Solution: Create secret in correct namespace
   kubectl create secret generic app-secret \
     --from-literal=api-key=value \
     -n production
   ```

3. **CI/CD Platform Secrets**
   ```yaml
   # GitHub Actions - secrets must be added in repo settings
   env:
     API_KEY: ${{ secrets.API_KEY }}  # Must exist in repo secrets

   # Verify secrets exist: Settings → Secrets and variables → Actions
   ```

4. **Secret Rotation**
   ```bash
   # Problem: Secret expired or rotated
   # Solution: Update secret
   kubectl create secret generic app-secret \
     --from-literal=api-key=new-value \
     --dry-run=client -o yaml | kubectl apply -f -

   # Restart pods to pick up new secret
   kubectl rollout restart deployment/app
   ```

**Debug Steps**:
```bash
# 1. List all secrets
kubectl get secrets
kubectl get secrets -A  # All namespaces

# 2. Decode secret to verify value
kubectl get secret app-secret -o jsonpath='{.data.api-key}' | base64 -d

# 3. Check pod has access
kubectl exec <pod-name> -- env | grep API_KEY

# 4. Verify secret is mounted
kubectl exec <pod-name> -- ls /var/run/secrets/
```

---

## Kubernetes Issues

### Pod in CrashLoopBackOff

**Symptoms**:
```bash
kubectl get pods
NAME                   READY   STATUS             RESTARTS   AGE
app-5d4b6c7f9d-abc123  0/1     CrashLoopBackOff   5          3m
```

**Troubleshooting Steps**:

1. **Check Logs**
   ```bash
   # Current logs
   kubectl logs app-5d4b6c7f9d-abc123

   # Previous instance (after crash)
   kubectl logs app-5d4b6c7f9d-abc123 --previous

   # Follow logs in real-time
   kubectl logs app-5d4b6c7f9d-abc123 -f
   ```

2. **Check Events**
   ```bash
   kubectl describe pod app-5d4b6c7f9d-abc123
   # Look at Events section at bottom
   ```

3. **Common Causes**:

   **a) Application Error**
   ```bash
   # Logs show: "Error: Cannot connect to database"
   # Solution: Check database connectivity
   kubectl exec app-5d4b6c7f9d-abc123 -- nc -zv postgres 5432

   # Verify environment variables
   kubectl exec app-5d4b6c7f9d-abc123 -- env | grep DB_
   ```

   **b) Missing ConfigMap/Secret**
   ```bash
   # Events show: "configmap "app-config" not found"
   # Solution: Create missing ConfigMap
   kubectl create configmap app-config --from-file=config.json
   ```

   **c) Wrong Command/Args**
   ```yaml
   # Problem: Wrong startup command
   spec:
     containers:
     - name: app
       image: app:latest
       command: ["/bin/sh"]
       args: ["-c", "node server.js"]  # Check command is correct

   # Test command locally
   docker run app:latest /bin/sh -c "node server.js"
   ```

   **d) Resource Limits**
   ```bash
   # Events show: "Pod was terminated in response to OOM kill"
   # Solution: Increase memory limits

   resources:
     limits:
       memory: "512Mi"  # Increase from 256Mi
   ```

4. **Debug with Ephemeral Container**
   ```bash
   # Create debug container in failing pod
   kubectl debug app-5d4b6c7f9d-abc123 -it --image=busybox

   # Test networking
   wget -O- http://postgres:5432
   nslookup postgres
   ```

---

### Pod Pending Forever

**Symptoms**:
```bash
kubectl get pods
NAME                   READY   STATUS    RESTARTS   AGE
app-5d4b6c7f9d-abc123  0/1     Pending   0          10m
```

**Troubleshooting Steps**:

1. **Check Events**
   ```bash
   kubectl describe pod app-5d4b6c7f9d-abc123

   # Common messages:
   # "0/3 nodes are available: insufficient cpu"
   # "0/3 nodes are available: pod has unbound immediate PersistentVolumeClaims"
   ```

2. **Insufficient Resources**
   ```bash
   # Check node capacity
   kubectl describe nodes | grep -A 5 "Allocated resources"

   # Check pod requests
   kubectl get pod app-5d4b6c7f9d-abc123 -o jsonpath='{.spec.containers[*].resources}'

   # Solutions:
   # - Add more nodes
   # - Reduce resource requests
   # - Delete unused pods
   ```

3. **Unbound PersistentVolumeClaim**
   ```bash
   # Check PVC status
   kubectl get pvc

   # If "Pending":
   kubectl describe pvc app-pvc

   # Solutions:
   # - Create matching PersistentVolume
   # - Use StorageClass with dynamic provisioning
   # - Check storage quota
   ```

4. **Node Selector / Affinity**
   ```bash
   # Check if pod requires specific node
   kubectl get pod app-5d4b6c7f9d-abc123 -o yaml | grep -A 5 nodeSelector

   # Check if any nodes match
   kubectl get nodes --show-labels

   # Solution: Remove nodeSelector or label nodes correctly
   ```

---

### Service Not Accessible

**Symptoms**:
- Can't reach service
- Connection refused
- Timeout

**Troubleshooting Steps**:

1. **Check Service Exists**
   ```bash
   kubectl get svc app-service
   kubectl describe svc app-service
   ```

2. **Verify Selector Matches**
   ```bash
   # Get service selector
   kubectl get svc app-service -o jsonpath='{.spec.selector}'
   # Output: {"app":"myapp"}

   # Check if any pods match
   kubectl get pods -l app=myapp

   # If no pods match → Fix labels
   ```

3. **Check Endpoints**
   ```bash
   # Endpoints should list pod IPs
   kubectl get endpoints app-service

   # If empty → selector mismatch or no pods ready
   ```

4. **Test from Inside Cluster**
   ```bash
   # Create test pod
   kubectl run test --image=busybox -it --rm -- /bin/sh

   # Inside pod, test service
   wget -O- http://app-service:80
   nslookup app-service

   # Try pod IP directly
   wget -O- http://10.1.2.3:8080
   ```

5. **Check NetworkPolicy**
   ```bash
   # List network policies
   kubectl get networkpolicy

   # Check if policy blocks traffic
   kubectl describe networkpolicy

   # Temporarily delete to test
   kubectl delete networkpolicy app-policy
   ```

6. **Check Ingress**
   ```bash
   # If using Ingress
   kubectl get ingress
   kubectl describe ingress app-ingress

   # Check ingress controller
   kubectl get pods -n ingress-nginx
   kubectl logs -n ingress-nginx <ingress-controller-pod>
   ```

---

### High Memory or CPU Usage

**Symptoms**:
```bash
kubectl top pods
NAME                   CPU      MEMORY
app-5d4b6c7f9d-abc123  950m     1800Mi  # High usage
```

**Troubleshooting Steps**:

1. **Identify Resource Hogs**
   ```bash
   # Sort by CPU
   kubectl top pods --sort-by=cpu

   # Sort by memory
   kubectl top pods --sort-by=memory

   # Check container-level usage
   kubectl top pod app-5d4b6c7f9d-abc123 --containers
   ```

2. **Check for Memory Leaks**
   ```bash
   # Get memory usage over time
   kubectl top pod app-5d4b6c7f9d-abc123 --containers
   # Wait 5 minutes
   kubectl top pod app-5d4b6c7f9d-abc123 --containers
   # If memory continuously increases → memory leak

   # Solution: Fix application code or restart periodically
   ```

3. **Profile Application**
   ```bash
   # Node.js heap snapshot
   kubectl exec app-pod -- node --inspect server.js

   # Java heap dump
   kubectl exec app-pod -- jmap -dump:format=b,file=heap.bin <PID>
   kubectl cp app-pod:/heap.bin ./heap.bin

   # Python memory profiler
   kubectl exec app-pod -- python -m memory_profiler app.py
   ```

4. **Adjust Resource Limits**
   ```yaml
   resources:
     requests:
       memory: "512Mi"
       cpu: "500m"
     limits:
       memory: "1Gi"  # Increase if legitimate usage
       cpu: "1000m"
   ```

5. **Scale Horizontally**
   ```bash
   # Instead of vertical scaling, add more replicas
   kubectl scale deployment app --replicas=5

   # Enable HPA
   kubectl autoscale deployment app --cpu-percent=70 --min=2 --max=10
   ```

---

## Docker Issues

### Image Build Fails

**Common Causes**:

1. **COPY/ADD File Not Found**
   ```dockerfile
   # Problem: File path is relative to build context
   COPY config.json /app/  # File must be in build context

   # Solution: Check build context
   docker build -t app .  # '.' is build context

   # Or specify file location
   COPY ./configs/config.json /app/
   ```

2. **Base Image Not Found**
   ```dockerfile
   # Problem: Typo in image name or tag
   FROM node:20.10.0  # Check tag exists

   # Solution: Verify on Docker Hub or use digest
   FROM node@sha256:abc123...
   ```

3. **Build Cache Issues**
   ```bash
   # Problem: Outdated cached layers
   # Solution: Build without cache
   docker build --no-cache -t app .

   # Or invalidate specific layer by changing order
   ```

---

### Container Exits Immediately

**Symptoms**:
```bash
docker run app
# Container starts and immediately exits
```

**Troubleshooting**:

1. **Check Logs**
   ```bash
   docker logs <container-id>
   docker logs <container-id> 2>&1 | less  # Include stderr
   ```

2. **Run Interactively**
   ```bash
   # Override entrypoint to debug
   docker run -it --entrypoint /bin/sh app

   # Try running command manually
   node server.js
   ```

3. **Check Process**
   ```dockerfile
   # Problem: Main process exits
   CMD ["npm", "start"]  # Must be foreground process

   # NOT:
   CMD ["npm", "start", "&"]  # Background process causes exit
   ```

---

## Terraform Issues

### State Lock Error

**Symptoms**:
```
Error: Error acquiring the state lock
```

**Solutions**:

1. **Wait for Other Process**
   ```bash
   # Another terraform process is running
   # Wait for it to complete or:

   # Force unlock (DANGER - only if sure no other process)
   terraform force-unlock <lock-id>
   ```

2. **Stale Lock**
   ```bash
   # Process crashed without releasing lock
   # Check DynamoDB (AWS) or backend for lock entry
   # Manually delete lock entry

   # Or force unlock
   terraform force-unlock -force <lock-id>
   ```

---

### Resource Already Exists

**Symptoms**:
```
Error: resource already exists
```

**Solutions**:

1. **Import Existing Resource**
   ```bash
   # Import into Terraform state
   terraform import aws_instance.app i-1234567890abcdef0
   ```

2. **Remove from State**
   ```bash
   # If you want to delete and recreate
   terraform state rm aws_instance.app
   terraform apply
   ```

---

## General Debugging Strategies

### 1. Isolate the Problem

```
Full system broken
    ↓
Identify failing component
    ↓
Reproduce issue in isolation
    ↓
Fix component
    ↓
Verify in full system
```

### 2. Check the Basics

- Is it running? (`ps`, `kubectl get pods`)
- Can it be reached? (`ping`, `curl`, `nc`)
- Are logs showing errors? (`logs`, `journalctl`)
- Are resources available? (`df`, `free`, `kubectl top`)

### 3. Increase Logging

```bash
# Enable debug logging
export LOG_LEVEL=debug
export TF_LOG=TRACE
kubectl logs <pod> -f --tail=1000
```

### 4. Compare Working vs Broken

- What changed between working and broken state?
- Compare configs, environment variables, versions
- Use `diff` to find changes

### 5. Use Process of Elimination

- Remove components one by one
- Disable features
- Test with minimal configuration
- Add complexity back gradually

---

## Quick Diagnostic Commands

```bash
# Is service running?
kubectl get pods -l app=myapp
docker ps | grep myapp
systemctl status myapp

# Can I reach it?
curl -v http://service:8080/health
nc -zv service 8080
ping service

# What's wrong?
kubectl logs <pod>
kubectl describe pod <pod>
kubectl get events --sort-by='.lastTimestamp'
docker logs <container>

# Resources OK?
kubectl top nodes
kubectl top pods
df -h
free -h

# Network OK?
kubectl exec <pod> -- nslookup service
kubectl exec <pod> -- wget -O- http://service:8080
traceroute service
```
