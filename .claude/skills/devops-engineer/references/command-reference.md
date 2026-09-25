# DevOps Command Reference

Quick reference for essential DevOps tools and commands.

---

## Docker

### Build and Run

```bash
# Build image from Dockerfile
docker build -t registry/app:tag .
docker build -t registry/app:tag -f Dockerfile.prod .  # Specify Dockerfile

# Run container
docker run -d -p 8080:80 --name app registry/app:tag
docker run -it --rm registry/app:tag /bin/bash  # Interactive, remove on exit

# Run with environment variables
docker run -e DB_HOST=postgres -e DB_PORT=5432 registry/app:tag

# Run with volume mount
docker run -v $(pwd)/data:/app/data registry/app:tag
```

### Image Management

```bash
# List images
docker images
docker images --filter "dangling=true"  # Untagged images

# Remove images
docker rmi registry/app:tag
docker image prune  # Remove unused images

# Tag image
docker tag registry/app:tag registry/app:latest
docker tag registry/app:tag registry/app:v1.2.3

# Push to registry
docker push registry/app:tag

# Pull from registry
docker pull registry/app:tag
```

### Container Management

```bash
# List containers
docker ps  # Running containers
docker ps -a  # All containers

# Stop and remove
docker stop app
docker rm app
docker rm -f app  # Force remove running container

# View logs
docker logs app
docker logs -f app  # Follow logs
docker logs --tail 100 app  # Last 100 lines

# Execute command in container
docker exec app ls /app
docker exec -it app /bin/bash  # Interactive shell

# Inspect container
docker inspect app
docker inspect --format='{{.NetworkSettings.IPAddress}}' app

# Container stats
docker stats  # All containers
docker stats app  # Specific container
```

### Cleanup

```bash
# Remove stopped containers
docker container prune

# Remove unused images
docker image prune
docker image prune -a  # All unused images

# Remove unused volumes
docker volume prune

# Remove unused networks
docker network prune

# Clean everything
docker system prune  # Containers, networks, images
docker system prune -a  # Include unused images
docker system prune -a --volumes  # Include volumes (DANGER)

# Show disk usage
docker system df
```

### Docker Compose

```bash
# Start services
docker-compose up
docker-compose up -d  # Detached mode
docker-compose up --build  # Rebuild images

# Stop services
docker-compose down
docker-compose down -v  # Remove volumes

# View logs
docker-compose logs
docker-compose logs -f  # Follow logs
docker-compose logs app  # Specific service

# Scale services
docker-compose up -d --scale app=3

# Execute command
docker-compose exec app /bin/bash
```

---

## Kubernetes (kubectl)

### Cluster Info

```bash
# Cluster information
kubectl cluster-info
kubectl version

# Node information
kubectl get nodes
kubectl get nodes -o wide
kubectl describe node <node-name>
kubectl top nodes  # Resource usage (requires metrics-server)

# Contexts and namespaces
kubectl config get-contexts
kubectl config use-context <context-name>
kubectl config set-context --current --namespace=<namespace>
```

### Get Resources

```bash
# Pods
kubectl get pods
kubectl get pods -A  # All namespaces
kubectl get pods -o wide  # More details
kubectl get pods --show-labels
kubectl get pods -l app=nginx  # Filter by label
kubectl get pods --field-selector status.phase=Running

# Deployments
kubectl get deployments
kubectl get deploy  # Short form
kubectl get deploy -o yaml  # YAML output

# Services
kubectl get services
kubectl get svc  # Short form

# All resources
kubectl get all
kubectl get all -A  # All namespaces
```

### Describe and Inspect

```bash
# Describe resources (detailed info)
kubectl describe pod <pod-name>
kubectl describe deployment <deployment-name>
kubectl describe service <service-name>
kubectl describe node <node-name>

# Get YAML/JSON
kubectl get pod <pod-name> -o yaml
kubectl get deployment <deployment-name> -o json

# Extract specific fields
kubectl get pods -o custom-columns=NAME:.metadata.name,STATUS:.status.phase
kubectl get pods -o jsonpath='{.items[*].metadata.name}'
```

### Logs and Debugging

```bash
# View logs
kubectl logs <pod-name>
kubectl logs <pod-name> -f  # Follow logs
kubectl logs <pod-name> -c <container-name>  # Specific container
kubectl logs <pod-name> --previous  # Previous instance (after crash)
kubectl logs <pod-name> --tail=100  # Last 100 lines
kubectl logs <pod-name> --since=1h  # Last hour

# Execute commands
kubectl exec <pod-name> -- ls /app
kubectl exec -it <pod-name> -- /bin/bash  # Interactive shell
kubectl exec -it <pod-name> -c <container-name> -- /bin/sh

# Port forwarding
kubectl port-forward pod/<pod-name> 8080:80
kubectl port-forward service/<service-name> 8080:80
kubectl port-forward deployment/<deployment-name> 8080:80

# Debug with ephemeral container
kubectl debug <pod-name> -it --image=busybox
kubectl debug node/<node-name> -it --image=ubuntu
```

### Create and Apply

```bash
# Create resources
kubectl create deployment nginx --image=nginx:1.25
kubectl create service clusterip nginx --tcp=80:80
kubectl create configmap app-config --from-file=config/
kubectl create secret generic app-secret --from-literal=password=secret123

# Apply manifests
kubectl apply -f manifest.yaml
kubectl apply -f directory/  # All files in directory
kubectl apply -k kustomize/  # Kustomize

# Delete resources
kubectl delete pod <pod-name>
kubectl delete deployment <deployment-name>
kubectl delete -f manifest.yaml
kubectl delete all -l app=nginx  # Delete by label
```

### Update Resources

```bash
# Scale deployment
kubectl scale deployment <name> --replicas=5
kubectl scale deployment <name> --replicas=0  # Scale to zero

# Update image
kubectl set image deployment/<name> container=image:tag

# Edit resource
kubectl edit deployment <name>

# Patch resource
kubectl patch deployment <name> -p '{"spec":{"replicas":3}}'
```

### Rollouts

```bash
# Rollout status
kubectl rollout status deployment/<name>

# Rollout history
kubectl rollout history deployment/<name>
kubectl rollout history deployment/<name> --revision=2

# Rollback
kubectl rollout undo deployment/<name>
kubectl rollout undo deployment/<name> --to-revision=2

# Pause and resume
kubectl rollout pause deployment/<name>
kubectl rollout resume deployment/<name>

# Restart (recreate pods)
kubectl rollout restart deployment/<name>
```

### ConfigMaps and Secrets

```bash
# ConfigMaps
kubectl create configmap app-config --from-file=config.json
kubectl create configmap app-config --from-literal=key=value
kubectl get configmap app-config -o yaml
kubectl edit configmap app-config

# Secrets
kubectl create secret generic app-secret --from-file=secret.txt
kubectl create secret generic app-secret --from-literal=password=secret
kubectl create secret tls tls-secret --cert=cert.pem --key=key.pem
kubectl get secret app-secret -o jsonpath='{.data.password}' | base64 -d
```

### Events and Troubleshooting

```bash
# Events
kubectl get events
kubectl get events --sort-by='.lastTimestamp'
kubectl get events --field-selector involvedObject.name=<pod-name>

# Resource usage
kubectl top nodes
kubectl top pods
kubectl top pod <pod-name> --containers

# Autoscaling
kubectl autoscale deployment <name> --min=2 --max=10 --cpu-percent=70
kubectl get hpa  # Horizontal Pod Autoscaler
```

### Namespace Management

```bash
# Create namespace
kubectl create namespace dev
kubectl create namespace staging

# Set default namespace
kubectl config set-context --current --namespace=dev

# Delete namespace (deletes all resources in it!)
kubectl delete namespace dev
```

### Labels and Annotations

```bash
# Add label
kubectl label pod <pod-name> env=production
kubectl label pod <pod-name> env=staging --overwrite

# Remove label
kubectl label pod <pod-name> env-

# Add annotation
kubectl annotate pod <pod-name> description="Production app"
```

---

## Terraform

### Initialize and Plan

```bash
# Initialize Terraform
terraform init
terraform init -upgrade  # Upgrade providers

# Validate configuration
terraform validate

# Format code
terraform fmt
terraform fmt -recursive

# Plan changes
terraform plan
terraform plan -out=plan.tfplan  # Save plan
terraform plan -var="instance_type=t2.micro"
terraform plan -var-file="prod.tfvars"

# Show plan
terraform show plan.tfplan
```

### Apply and Destroy

```bash
# Apply changes
terraform apply
terraform apply plan.tfplan  # Apply saved plan
terraform apply -auto-approve  # Skip confirmation (CI/CD)
terraform apply -target=aws_instance.app  # Specific resource

# Destroy infrastructure
terraform destroy
terraform destroy -auto-approve
terraform destroy -target=aws_instance.app
```

### State Management

```bash
# List resources in state
terraform state list

# Show resource
terraform state show aws_instance.app

# Move resource in state
terraform state mv aws_instance.old aws_instance.new

# Remove resource from state (doesn't destroy)
terraform state rm aws_instance.app

# Pull current state
terraform state pull > backup.tfstate

# Import existing resource
terraform import aws_instance.app i-1234567890abcdef0

# Refresh state
terraform refresh
```

### Workspaces

```bash
# List workspaces
terraform workspace list

# Create workspace
terraform workspace new dev
terraform workspace new prod

# Switch workspace
terraform workspace select dev

# Delete workspace
terraform workspace delete dev
```

### Output

```bash
# Show outputs
terraform output
terraform output instance_ip
terraform output -json  # JSON format
```

### Troubleshooting

```bash
# Enable debug logging
TF_LOG=DEBUG terraform plan
TF_LOG=TRACE terraform apply

# Taint resource (force recreation)
terraform taint aws_instance.app
terraform untaint aws_instance.app

# Graph visualization
terraform graph | dot -Tsvg > graph.svg
```

---

## Git (DevOps Context)

### Common Workflows

```bash
# Clone repository
git clone https://github.com/org/repo.git
git clone -b develop https://github.com/org/repo.git  # Specific branch

# Commit and push
git add .
git commit -m "feat: Add new feature"
git push origin main

# Create branch
git checkout -b feature/new-feature
git push -u origin feature/new-feature

# Merge and delete branch
git checkout main
git merge feature/new-feature
git branch -d feature/new-feature
git push origin --delete feature/new-feature

# Tags (for releases)
git tag v1.2.3
git push origin v1.2.3
git tag -a v1.2.3 -m "Release version 1.2.3"  # Annotated tag
```

### Rebase and Reset

```bash
# Rebase on main
git checkout feature/branch
git rebase main

# Interactive rebase (squash commits)
git rebase -i HEAD~3  # Last 3 commits

# Reset (DANGER)
git reset --soft HEAD~1  # Undo commit, keep changes
git reset --hard HEAD~1  # Undo commit, discard changes
git reset --hard origin/main  # Match remote
```

### GitOps Workflows

```bash
# Update version in manifest
git checkout -b update-version
sed -i 's/image: app:v1.0/image: app:v1.1/' k8s/deployment.yaml
git add k8s/deployment.yaml
git commit -m "chore: Update app version to v1.1"
git push origin update-version

# Create tag for release
git tag -a v1.1.0 -m "Release v1.1.0"
git push origin v1.1.0
```

---

## CI/CD Platforms

### GitHub Actions

```bash
# Trigger workflow manually
gh workflow run ci-cd.yml

# List workflows
gh workflow list

# View workflow runs
gh run list

# View specific run
gh run view <run-id>

# Re-run failed jobs
gh run rerun <run-id>

# Download artifacts
gh run download <run-id>
```

### GitLab CI

```bash
# Trigger pipeline
curl -X POST \
  -F token=<token> \
  -F ref=main \
  https://gitlab.com/api/v4/projects/<project-id>/trigger/pipeline

# View pipeline status
curl https://gitlab.com/api/v4/projects/<project-id>/pipelines/latest
```

---

## Monitoring (Prometheus)

### PromQL Queries

```bash
# HTTP request rate
rate(http_requests_total[5m])

# Error rate
rate(http_requests_total{status=~"5.."}[5m])

# Memory usage
container_memory_usage_bytes{pod="app"}

# CPU usage percentage
rate(container_cpu_usage_seconds_total[5m]) * 100

# Prediction
predict_linear(node_memory_MemAvailable_bytes[1h], 3600)  # Predict 1 hour ahead
```

### cURL for Prometheus API

```bash
# Query
curl 'http://prometheus:9090/api/v1/query?query=up'

# Range query
curl 'http://prometheus:9090/api/v1/query_range?query=up&start=2024-01-01T00:00:00Z&end=2024-01-01T23:59:59Z&step=1h'

# Targets
curl 'http://prometheus:9090/api/v1/targets'

# Alerts
curl 'http://prometheus:9090/api/v1/alerts'
```

---

## Helm (Kubernetes Package Manager)

```bash
# Add repository
helm repo add prometheus https://prometheus-community.github.io/helm-charts
helm repo update

# Search charts
helm search repo prometheus
helm search hub prometheus

# Install chart
helm install prometheus prometheus/prometheus
helm install prometheus prometheus/prometheus --namespace monitoring --create-namespace
helm install prometheus prometheus/prometheus --values custom-values.yaml

# List releases
helm list
helm list -A  # All namespaces

# Upgrade release
helm upgrade prometheus prometheus/prometheus
helm upgrade prometheus prometheus/prometheus --reuse-values
helm upgrade prometheus prometheus/prometheus --set server.replicaCount=2

# Rollback release
helm rollback prometheus
helm rollback prometheus 1  # Specific revision

# Uninstall release
helm uninstall prometheus

# Get values
helm get values prometheus
helm show values prometheus/prometheus  # Default values

# Chart management
helm create my-chart  # Create new chart
helm package my-chart  # Package chart
helm lint my-chart  # Validate chart
```

---

## OpenSSL (Certificates)

```bash
# Generate private key
openssl genrsa -out key.pem 2048

# Generate self-signed certificate
openssl req -new -x509 -key key.pem -out cert.pem -days 365

# View certificate
openssl x509 -in cert.pem -text -noout

# Check certificate expiration
openssl x509 -in cert.pem -noout -dates

# Verify certificate
openssl verify -CAfile ca.pem cert.pem
```

---

## Common Patterns

### Check if service is running
```bash
# Docker
docker ps | grep app-name

# Kubernetes
kubectl get pods -l app=app-name

# Process
ps aux | grep app-name

# Port
netstat -tuln | grep :8080
lsof -i :8080
```

### Health check
```bash
# HTTP endpoint
curl -f http://localhost:8080/health || echo "Unhealthy"

# TCP port
nc -zv localhost 5432

# DNS resolution
nslookup service.namespace.svc.cluster.local
```

### Copy files
```bash
# Docker
docker cp app:/app/logs/app.log ./app.log
docker cp ./config.json app:/app/config.json

# Kubernetes
kubectl cp <pod-name>:/app/logs/app.log ./app.log
kubectl cp ./config.json <pod-name>:/app/config.json
```

---

## Cheat Sheet Summary

| Tool | Common Commands |
|------|-----------------|
| **Docker** | `build`, `run`, `ps`, `logs`, `exec`, `stop`, `rm`, `push`, `pull` |
| **Kubernetes** | `get`, `describe`, `logs`, `exec`, `apply`, `delete`, `scale`, `rollout` |
| **Terraform** | `init`, `plan`, `apply`, `destroy`, `state`, `output` |
| **Git** | `clone`, `add`, `commit`, `push`, `pull`, `merge`, `tag` |
| **Helm** | `install`, `upgrade`, `rollback`, `list`, `uninstall` |
