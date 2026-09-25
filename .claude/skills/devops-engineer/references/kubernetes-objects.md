# Kubernetes Objects Reference

## Core Objects

### Pod
**Definition**: Smallest deployable unit - one or more containers

```yaml
apiVersion: v1
kind: Pod
metadata:
  name: nginx-pod
  labels:
    app: nginx
spec:
  containers:
  - name: nginx
    image: nginx:1.25
    ports:
    - containerPort: 80
    resources:
      requests:
        memory: "64Mi"
        cpu: "250m"
      limits:
        memory: "128Mi"
        cpu: "500m"
    livenessProbe:
      httpGet:
        path: /healthz
        port: 80
      initialDelaySeconds: 3
      periodSeconds: 3
    readinessProbe:
      httpGet:
        path: /ready
        port: 80
      initialDelaySeconds: 5
      periodSeconds: 5
```

**Use Cases**:
- Testing/debugging
- One-off jobs
- Sidecar patterns

**Don't Use For**: Long-running applications (use Deployment instead)

### Deployment
**Definition**: Manages replica sets and rolling updates

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: nginx-deployment
  labels:
    app: nginx
spec:
  replicas: 3
  strategy:
    type: RollingUpdate
    rollingUpdate:
      maxSurge: 1
      maxUnavailable: 0
  selector:
    matchLabels:
      app: nginx
  template:
    metadata:
      labels:
        app: nginx
    spec:
      containers:
      - name: nginx
        image: nginx:1.25
        ports:
        - containerPort: 80
```

**Commands**:
```bash
# Create deployment
kubectl create deployment nginx --image=nginx:1.25 --replicas=3

# Update image
kubectl set image deployment/nginx nginx=nginx:1.26

# Scale
kubectl scale deployment/nginx --replicas=5

# Rollout status
kubectl rollout status deployment/nginx

# Rollback
kubectl rollout undo deployment/nginx
```

### Service
**Definition**: Exposes pods via stable network endpoint

**Types**:
1. **ClusterIP** (default): Internal cluster access only
2. **NodePort**: External access via node IP:port
3. **LoadBalancer**: Cloud provider load balancer
4. **ExternalName**: DNS CNAME

```yaml
apiVersion: v1
kind: Service
metadata:
  name: nginx-service
spec:
  type: ClusterIP
  selector:
    app: nginx
  ports:
  - protocol: TCP
    port: 80
    targetPort: 80
```

**LoadBalancer Example**:
```yaml
apiVersion: v1
kind: Service
metadata:
  name: nginx-lb
spec:
  type: LoadBalancer
  selector:
    app: nginx
  ports:
  - protocol: TCP
    port: 80
    targetPort: 80
```

### Ingress
**Definition**: HTTP/HTTPS routing to services

```yaml
apiVersion: networking.k8s.io/v1
kind: Ingress
metadata:
  name: app-ingress
  annotations:
    nginx.ingress.kubernetes.io/rewrite-target: /
spec:
  ingressClassName: nginx
  rules:
  - host: app.example.com
    http:
      paths:
      - path: /
        pathType: Prefix
        backend:
          service:
            name: app-service
            port:
              number: 80
  tls:
  - hosts:
    - app.example.com
    secretName: app-tls
```

**Common Annotations**:
```yaml
# NGINX Ingress
nginx.ingress.kubernetes.io/ssl-redirect: "true"
nginx.ingress.kubernetes.io/force-ssl-redirect: "true"
nginx.ingress.kubernetes.io/rate-limit: "100"

# Cert-Manager (auto SSL)
cert-manager.io/cluster-issuer: "letsencrypt-prod"
```

## Configuration Objects

### ConfigMap
**Definition**: Store non-sensitive configuration

```yaml
apiVersion: v1
kind: ConfigMap
metadata:
  name: app-config
data:
  database_url: "postgres://db:5432"
  log_level: "info"
  config.json: |
    {
      "feature_flags": {
        "new_ui": true
      }
    }
```

**Usage in Pod**:
```yaml
spec:
  containers:
  - name: app
    env:
    - name: DATABASE_URL
      valueFrom:
        configMapKeyRef:
          name: app-config
          key: database_url
    volumeMounts:
    - name: config-volume
      mountPath: /etc/config
  volumes:
  - name: config-volume
    configMap:
      name: app-config
```

### Secret
**Definition**: Store sensitive data (base64 encoded)

```yaml
apiVersion: v1
kind: Secret
metadata:
  name: app-secrets
type: Opaque
data:
  password: cGFzc3dvcmQxMjM=  # base64 encoded
stringData:
  api_key: "plain-text-key"  # auto-encoded
```

**Commands**:
```bash
# Create from literal
kubectl create secret generic db-secret \
  --from-literal=username=admin \
  --from-literal=password=secret123

# Create from file
kubectl create secret generic ssh-key \
  --from-file=ssh-privatekey=~/.ssh/id_rsa

# Create TLS secret
kubectl create secret tls app-tls \
  --cert=path/to/cert.crt \
  --key=path/to/key.key
```

**Usage**:
```yaml
spec:
  containers:
  - name: app
    env:
    - name: DB_PASSWORD
      valueFrom:
        secretKeyRef:
          name: app-secrets
          key: password
```

## Storage Objects

### PersistentVolume (PV)
**Definition**: Cluster storage resource

```yaml
apiVersion: v1
kind: PersistentVolume
metadata:
  name: pv-data
spec:
  capacity:
    storage: 10Gi
  accessModes:
  - ReadWriteOnce
  persistentVolumeReclaimPolicy: Retain
  storageClassName: fast
  hostPath:
    path: /mnt/data
```

### PersistentVolumeClaim (PVC)
**Definition**: Request for storage

```yaml
apiVersion: v1
kind: PersistentVolumeClaim
metadata:
  name: pvc-data
spec:
  accessModes:
  - ReadWriteOnce
  resources:
    requests:
      storage: 5Gi
  storageClassName: fast
```

**Usage in Pod**:
```yaml
spec:
  containers:
  - name: app
    volumeMounts:
    - name: data
      mountPath: /data
  volumes:
  - name: data
    persistentVolumeClaim:
      claimName: pvc-data
```

## Workload Objects

### StatefulSet
**Definition**: Manages stateful applications (databases, queues)

```yaml
apiVersion: apps/v1
kind: StatefulSet
metadata:
  name: postgresql
spec:
  serviceName: postgres
  replicas: 3
  selector:
    matchLabels:
      app: postgres
  template:
    metadata:
      labels:
        app: postgres
    spec:
      containers:
      - name: postgres
        image: postgres:15
        ports:
        - containerPort: 5432
        volumeMounts:
        - name: data
          mountPath: /var/lib/postgresql/data
  volumeClaimTemplates:
  - metadata:
      name: data
    spec:
      accessModes: ["ReadWriteOnce"]
      resources:
        requests:
          storage: 10Gi
```

**Characteristics**:
- Stable network identity (pod-0, pod-1, pod-2)
- Ordered deployment and scaling
- Persistent storage per pod

### DaemonSet
**Definition**: Runs one pod per node

```yaml
apiVersion: apps/v1
kind: DaemonSet
metadata:
  name: node-exporter
spec:
  selector:
    matchLabels:
      app: node-exporter
  template:
    metadata:
      labels:
        app: node-exporter
    spec:
      containers:
      - name: node-exporter
        image: prom/node-exporter:latest
        ports:
        - containerPort: 9100
```

**Use Cases**:
- Log collectors (Fluentd, Filebeat)
- Monitoring agents (node-exporter, Datadog)
- Network plugins (Calico, Cilium)

### Job
**Definition**: Run-to-completion tasks

```yaml
apiVersion: batch/v1
kind: Job
metadata:
  name: data-migration
spec:
  completions: 1
  parallelism: 1
  backoffLimit: 3
  template:
    spec:
      containers:
      - name: migrate
        image: app:latest
        command: ["python", "migrate.py"]
      restartPolicy: OnFailure
```

### CronJob
**Definition**: Scheduled jobs

```yaml
apiVersion: batch/v1
kind: CronJob
metadata:
  name: backup
spec:
  schedule: "0 2 * * *"  # 2 AM daily
  jobTemplate:
    spec:
      template:
        spec:
          containers:
          - name: backup
            image: backup-tool:latest
            command: ["backup.sh"]
          restartPolicy: OnFailure
```

## Autoscaling Objects

### HorizontalPodAutoscaler (HPA)
**Definition**: Auto-scale pods based on metrics

```yaml
apiVersion: autoscaling/v2
kind: HorizontalPodAutoscaler
metadata:
  name: app-hpa
spec:
  scaleTargetRef:
    apiVersion: apps/v1
    kind: Deployment
    name: app
  minReplicas: 2
  maxReplicas: 10
  metrics:
  - type: Resource
    resource:
      name: cpu
      target:
        type: Utilization
        averageUtilization: 70
  - type: Resource
    resource:
      name: memory
      target:
        type: Utilization
        averageUtilization: 80
```

**Command**:
```bash
kubectl autoscale deployment app --cpu-percent=70 --min=2 --max=10
```

## Policy Objects

### NetworkPolicy
**Definition**: Control pod-to-pod traffic

```yaml
apiVersion: networking.k8s.io/v1
kind: NetworkPolicy
metadata:
  name: api-network-policy
spec:
  podSelector:
    matchLabels:
      app: api
  policyTypes:
  - Ingress
  - Egress
  ingress:
  - from:
    - podSelector:
        matchLabels:
          app: frontend
    ports:
    - protocol: TCP
      port: 8080
  egress:
  - to:
    - podSelector:
        matchLabels:
          app: database
    ports:
    - protocol: TCP
      port: 5432
```

### PodDisruptionBudget
**Definition**: Ensure availability during disruptions

```yaml
apiVersion: policy/v1
kind: PodDisruptionBudget
metadata:
  name: app-pdb
spec:
  minAvailable: 2
  selector:
    matchLabels:
      app: api
```

## Resource Management

### ResourceQuota
**Definition**: Limit resource consumption per namespace

```yaml
apiVersion: v1
kind: ResourceQuota
metadata:
  name: compute-quota
  namespace: dev
spec:
  hard:
    requests.cpu: "10"
    requests.memory: "20Gi"
    limits.cpu: "20"
    limits.memory: "40Gi"
    pods: "50"
```

### LimitRange
**Definition**: Default/max resources for pods

```yaml
apiVersion: v1
kind: LimitRange
metadata:
  name: limit-range
  namespace: dev
spec:
  limits:
  - max:
      cpu: "2"
      memory: "4Gi"
    min:
      cpu: "100m"
      memory: "128Mi"
    default:
      cpu: "500m"
      memory: "512Mi"
    defaultRequest:
      cpu: "250m"
      memory: "256Mi"
    type: Container
```

## Best Practices

### Labels and Selectors
```yaml
metadata:
  labels:
    app: myapp              # Application name
    tier: backend           # Application tier
    environment: production # Environment
    version: v1.2.3         # Version
    managed-by: helm        # Management tool
```

### Resource Requests and Limits
```yaml
resources:
  requests:  # Guaranteed resources
    memory: "256Mi"
    cpu: "250m"
  limits:    # Maximum resources
    memory: "512Mi"
    cpu: "500m"
```

**Guidelines**:
- Always set requests (for scheduling)
- Set limits to prevent noisy neighbors
- requests ≤ limits
- Monitor actual usage and adjust

### Health Checks
```yaml
livenessProbe:   # Is container alive?
  httpGet:
    path: /healthz
    port: 8080
  initialDelaySeconds: 30
  periodSeconds: 10
  failureThreshold: 3

readinessProbe:  # Can container serve traffic?
  httpGet:
    path: /ready
    port: 8080
  initialDelaySeconds: 5
  periodSeconds: 5

startupProbe:    # Has container started? (for slow starts)
  httpGet:
    path: /healthz
    port: 8080
  failureThreshold: 30
  periodSeconds: 10
```

### Security Contexts
```yaml
securityContext:
  runAsNonRoot: true
  runAsUser: 1000
  fsGroup: 2000
  capabilities:
    drop:
    - ALL
  readOnlyRootFilesystem: true
```

## kubectl Quick Reference

```bash
# Get resources
kubectl get pods/deployments/services
kubectl get all
kubectl get pods -o wide
kubectl get pods --show-labels

# Describe
kubectl describe pod <pod-name>

# Logs
kubectl logs <pod-name>
kubectl logs <pod-name> -f  # follow
kubectl logs <pod-name> -c <container-name>  # multi-container
kubectl logs <pod-name> --previous  # previous instance

# Execute
kubectl exec -it <pod-name> -- /bin/bash
kubectl exec <pod-name> -- env

# Port forward
kubectl port-forward pod/<pod-name> 8080:80
kubectl port-forward service/<service-name> 8080:80

# Apply/Create
kubectl apply -f manifest.yaml
kubectl apply -f directory/
kubectl apply -k kustomize/

# Delete
kubectl delete pod <pod-name>
kubectl delete -f manifest.yaml
kubectl delete deployment,service <name>

# Scale
kubectl scale deployment/<name> --replicas=5
kubectl autoscale deployment/<name> --min=2 --max=10 --cpu-percent=70

# Rollout
kubectl rollout status deployment/<name>
kubectl rollout history deployment/<name>
kubectl rollout undo deployment/<name>
kubectl rollout undo deployment/<name> --to-revision=2

# Debug
kubectl top nodes
kubectl top pods
kubectl get events --sort-by='.lastTimestamp'
kubectl debug <pod-name> -it --image=busybox
```

## Common Patterns

### Sidecar Pattern
```yaml
spec:
  containers:
  - name: app
    image: app:latest
  - name: log-forwarder  # Sidecar
    image: fluentd:latest
```

### Init Containers
```yaml
spec:
  initContainers:
  - name: wait-for-db
    image: busybox
    command: ['sh', '-c', 'until nc -z db 5432; do sleep 1; done']
  containers:
  - name: app
    image: app:latest
```

### Multi-Container Communication
```yaml
spec:
  containers:
  - name: nginx
    image: nginx
    volumeMounts:
    - name: shared-data
      mountPath: /usr/share/nginx/html
  - name: content-puller
    image: alpine/git
    command: ['sh', '-c', 'git clone repo /data']
    volumeMounts:
    - name: shared-data
      mountPath: /data
  volumes:
  - name: shared-data
    emptyDir: {}
```
