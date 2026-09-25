#!/usr/bin/env bash
#
# Kubernetes Setup & Validation Script
# Validates K8s cluster and sets up essential components
#

set -euo pipefail

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${GREEN}================================${NC}"
echo -e "${GREEN}   Kubernetes Setup & Validation${NC}"
echo -e "${GREEN}================================${NC}"
echo

# Check kubectl
check_kubectl() {
    echo "Checking kubectl..."
    if ! command -v kubectl &> /dev/null; then
        echo -e "${RED}❌ kubectl not installed${NC}"
        echo "Install: https://kubernetes.io/docs/tasks/tools/"
        exit 1
    fi

    kubectl version --client --short 2>/dev/null || kubectl version --client
    echo -e "${GREEN}✓ kubectl installed${NC}"
    echo
}

# Check cluster connectivity
check_cluster() {
    echo "Checking cluster connectivity..."
    if ! kubectl cluster-info &> /dev/null; then
        echo -e "${RED}❌ Cannot connect to cluster${NC}"
        echo "Check your kubeconfig: kubectl config view"
        exit 1
    fi

    kubectl cluster-info | head -2
    echo -e "${GREEN}✓ Connected to cluster${NC}"
    echo
}

# Validate cluster resources
validate_cluster() {
    echo "Validating cluster resources..."

    # Check nodes
    NODE_COUNT=$(kubectl get nodes --no-headers | wc -l)
    echo "Nodes: $NODE_COUNT"
    kubectl get nodes

    if [ "$NODE_COUNT" -eq 0 ]; then
        echo -e "${RED}❌ No nodes found${NC}"
        exit 1
    fi
    echo -e "${GREEN}✓ Nodes available${NC}"
    echo

    # Check node resources
    echo "Node resource capacity:"
    kubectl top nodes 2>/dev/null || echo "Install metrics-server for resource metrics"
    echo
}

# Create namespace
create_namespace() {
    read -p "Create a new namespace? (y/n): " create_ns

    if [[ "$create_ns" == "y" ]]; then
        read -p "Namespace name: " NS_NAME

        if kubectl get namespace "$NS_NAME" &> /dev/null; then
            echo -e "${YELLOW}Namespace '$NS_NAME' already exists${NC}"
        else
            kubectl create namespace "$NS_NAME"
            echo -e "${GREEN}✓ Namespace '$NS_NAME' created${NC}"
        fi

        # Set as default
        kubectl config set-context --current --namespace="$NS_NAME"
        echo -e "${GREEN}✓ Set '$NS_NAME' as default namespace${NC}"
        echo
    fi
}

# Setup metrics-server (if not present)
setup_metrics_server() {
    echo "Checking metrics-server..."

    if kubectl get deployment metrics-server -n kube-system &> /dev/null; then
        echo -e "${GREEN}✓ metrics-server already installed${NC}"
    else
        echo "metrics-server not found"
        read -p "Install metrics-server? (y/n): " install_metrics

        if [[ "$install_metrics" == "y" ]]; then
            kubectl apply -f https://github.com/kubernetes-sigs/metrics-server/releases/latest/download/components.yaml
            echo "Waiting for metrics-server to be ready..."
            kubectl wait --for=condition=available --timeout=60s deployment/metrics-server -n kube-system
            echo -e "${GREEN}✓ metrics-server installed${NC}"
        fi
    fi
    echo
}

# Create sample deployment
create_sample_app() {
    read -p "Deploy sample application? (y/n): " deploy_sample

    if [[ "$deploy_sample" == "y" ]]; then
        cat <<EOF | kubectl apply -f -
apiVersion: apps/v1
kind: Deployment
metadata:
  name: nginx-demo
  labels:
    app: nginx-demo
spec:
  replicas: 2
  selector:
    matchLabels:
      app: nginx-demo
  template:
    metadata:
      labels:
        app: nginx-demo
    spec:
      containers:
      - name: nginx
        image: nginx:1.25
        ports:
        - containerPort: 80
        resources:
          requests:
            memory: "64Mi"
            cpu: "100m"
          limits:
            memory: "128Mi"
            cpu: "200m"
        livenessProbe:
          httpGet:
            path: /
            port: 80
          initialDelaySeconds: 3
          periodSeconds: 3
        readinessProbe:
          httpGet:
            path: /
            port: 80
          initialDelaySeconds: 5
          periodSeconds: 5
---
apiVersion: v1
kind: Service
metadata:
  name: nginx-demo
spec:
  selector:
    app: nginx-demo
  ports:
  - protocol: TCP
    port: 80
    targetPort: 80
  type: ClusterIP
EOF

        echo "Waiting for deployment..."
        kubectl wait --for=condition=available --timeout=60s deployment/nginx-demo

        echo -e "${GREEN}✓ Sample app deployed${NC}"
        echo
        echo "Test the deployment:"
        echo "  kubectl get pods"
        echo "  kubectl port-forward service/nginx-demo 8080:80"
        echo "  curl http://localhost:8080"
        echo
    fi
}

# Setup ingress controller
setup_ingress() {
    echo "Checking ingress controller..."

    if kubectl get pods -n ingress-nginx &> /dev/null 2>&1; then
        echo -e "${GREEN}✓ Ingress controller already installed${NC}"
    else
        echo "No ingress controller found"
        read -p "Install NGINX Ingress Controller? (y/n): " install_ingress

        if [[ "$install_ingress" == "y" ]]; then
            kubectl apply -f https://raw.githubusercontent.com/kubernetes/ingress-nginx/controller-v1.8.1/deploy/static/provider/cloud/deploy.yaml
            echo "Waiting for ingress controller..."
            kubectl wait --namespace ingress-nginx \
              --for=condition=ready pod \
              --selector=app.kubernetes.io/component=controller \
              --timeout=120s
            echo -e "${GREEN}✓ Ingress controller installed${NC}"
        fi
    fi
    echo
}

# Setup monitoring (Prometheus + Grafana)
setup_monitoring() {
    read -p "Setup monitoring stack (Prometheus + Grafana)? (y/n): " setup_mon

    if [[ "$setup_mon" == "y" ]]; then
        # Check if helm is available
        if ! command -v helm &> /dev/null; then
            echo -e "${YELLOW}Helm not installed. Install: https://helm.sh/docs/intro/install/${NC}"
            return
        fi

        # Add Prometheus helm repo
        helm repo add prometheus-community https://prometheus-community.github.io/helm-charts
        helm repo update

        # Create monitoring namespace
        kubectl create namespace monitoring --dry-run=client -o yaml | kubectl apply -f -

        # Install kube-prometheus-stack
        echo "Installing Prometheus + Grafana..."
        helm upgrade --install prometheus prometheus-community/kube-prometheus-stack \
          --namespace monitoring \
          --set prometheus.prometheusSpec.serviceMonitorSelectorNilUsesHelmValues=false \
          --set grafana.adminPassword=admin \
          --wait

        echo -e "${GREEN}✓ Monitoring stack installed${NC}"
        echo
        echo "Access Grafana:"
        echo "  kubectl port-forward -n monitoring svc/prometheus-grafana 3000:80"
        echo "  Open http://localhost:3000 (admin/admin)"
        echo
    fi
}

# Generate kubectl cheat sheet
generate_cheatsheet() {
    cat <<'EOF'

=== Kubernetes Quick Reference ===

## Get Resources
kubectl get pods
kubectl get deployments
kubectl get services
kubectl get all

## Describe Resources
kubectl describe pod <pod-name>
kubectl describe deployment <deployment-name>

## Logs
kubectl logs <pod-name>
kubectl logs <pod-name> -f  # follow
kubectl logs <pod-name> -c <container-name>  # specific container

## Execute Commands
kubectl exec -it <pod-name> -- /bin/bash
kubectl exec <pod-name> -- env

## Port Forward
kubectl port-forward pod/<pod-name> 8080:80
kubectl port-forward service/<service-name> 8080:80

## Scale
kubectl scale deployment/<name> --replicas=5

## Rollout Management
kubectl rollout status deployment/<name>
kubectl rollout history deployment/<name>
kubectl rollout undo deployment/<name>

## Debug
kubectl top nodes
kubectl top pods
kubectl get events --sort-by='.lastTimestamp'

## Contexts
kubectl config get-contexts
kubectl config use-context <context-name>
kubectl config set-context --current --namespace=<namespace>

EOF
}

# Main execution
main() {
    check_kubectl
    check_cluster
    validate_cluster

    create_namespace
    setup_metrics_server
    create_sample_app
    setup_ingress
    setup_monitoring

    generate_cheatsheet

    echo -e "${GREEN}================================${NC}"
    echo -e "${GREEN}   Setup Complete!${NC}"
    echo -e "${GREEN}================================${NC}"
    echo
    echo "Your Kubernetes cluster is ready to use!"
    echo
}

main "$@"
