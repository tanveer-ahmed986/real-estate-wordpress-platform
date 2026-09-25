#!/usr/bin/env bash
#
# CI/CD Setup Wizard
# Interactive script to set up CI/CD pipeline
#

set -euo pipefail

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}================================${NC}"
echo -e "${GREEN}   CI/CD Setup Wizard${NC}"
echo -e "${GREEN}================================${NC}"
echo

# Check prerequisites
check_prerequisites() {
    echo "Checking prerequisites..."

    if ! command -v git &> /dev/null; then
        echo -e "${RED}❌ git is not installed${NC}"
        exit 1
    fi

    if ! git rev-parse --git-dir > /dev/null 2>&1; then
        echo -e "${RED}❌ Not in a git repository${NC}"
        exit 1
    fi

    echo -e "${GREEN}✓ Prerequisites met${NC}"
    echo
}

# Detect platform
detect_platform() {
    PLATFORM="unknown"

    if git remote -v | grep -q "github.com"; then
        PLATFORM="github"
    elif git remote -v | grep -q "gitlab.com"; then
        PLATFORM="gitlab"
    fi

    echo "Detected platform: $PLATFORM"
    echo
}

# Detect project type
detect_project_type() {
    if [ -f "package.json" ]; then
        PROJECT_TYPE="nodejs"
        echo -e "${GREEN}Detected: Node.js project${NC}"
    elif [ -f "requirements.txt" ] || [ -f "setup.py" ]; then
        PROJECT_TYPE="python"
        echo -e "${GREEN}Detected: Python project${NC}"
    elif [ -f "pom.xml" ]; then
        PROJECT_TYPE="java-maven"
        echo -e "${GREEN}Detected: Java (Maven) project${NC}"
    elif [ -f "go.mod" ]; then
        PROJECT_TYPE="go"
        echo -e "${GREEN}Detected: Go project${NC}"
    else
        PROJECT_TYPE="generic"
        echo -e "${YELLOW}Could not detect project type${NC}"
    fi
    echo
}

# Create GitHub Actions workflow
create_github_actions() {
    echo "Creating GitHub Actions workflow..."

    mkdir -p .github/workflows

    case "$PROJECT_TYPE" in
        nodejs)
            cat > .github/workflows/ci-cd.yml <<'EOF'
name: CI/CD Pipeline

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main]

jobs:
  build-and-test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: '20'
          cache: 'npm'

      - name: Install dependencies
        run: npm ci

      - name: Lint
        run: npm run lint || echo "Add 'lint' script to package.json"

      - name: Test
        run: npm test || echo "Add 'test' script to package.json"

      - name: Build
        run: npm run build || echo "Add 'build' script to package.json"

  security-scan:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Run security audit
        run: npm audit --audit-level=moderate || true

      - name: Run Trivy scanner
        uses: aquasecurity/trivy-action@master
        with:
          scan-type: 'fs'
          severity: 'HIGH,CRITICAL'

  deploy:
    needs: [build-and-test, security-scan]
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/main'
    steps:
      - name: Deploy
        run: echo "Add deployment steps here"
EOF
            ;;

        python)
            cat > .github/workflows/ci-cd.yml <<'EOF'
name: CI/CD Pipeline

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main]

jobs:
  build-and-test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Setup Python
        uses: actions/setup-python@v5
        with:
          python-version: '3.11'
          cache: 'pip'

      - name: Install dependencies
        run: |
          python -m pip install --upgrade pip
          pip install -r requirements.txt

      - name: Lint
        run: |
          pip install pylint
          pylint **/*.py || true

      - name: Test
        run: |
          pip install pytest pytest-cov
          pytest --cov=. tests/ || echo "Add tests directory"

  security-scan:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Security scan
        run: |
          pip install safety
          safety check || true

      - name: Run Trivy scanner
        uses: aquasecurity/trivy-action@master
        with:
          scan-type: 'fs'
          severity: 'HIGH,CRITICAL'

  deploy:
    needs: [build-and-test, security-scan]
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/main'
    steps:
      - name: Deploy
        run: echo "Add deployment steps here"
EOF
            ;;

        *)
            cat > .github/workflows/ci-cd.yml <<'EOF'
name: CI/CD Pipeline

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main]

jobs:
  build-and-test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Build
        run: echo "Add build steps here"

      - name: Test
        run: echo "Add test steps here"

  security-scan:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Run Trivy scanner
        uses: aquasecurity/trivy-action@master
        with:
          scan-type: 'fs'
          severity: 'HIGH,CRITICAL'

  deploy:
    needs: [build-and-test, security-scan]
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/main'
    steps:
      - name: Deploy
        run: echo "Add deployment steps here"
EOF
            ;;
    esac

    echo -e "${GREEN}✓ GitHub Actions workflow created${NC}"
    echo -e "  Location: .github/workflows/ci-cd.yml"
    echo
}

# Create GitLab CI config
create_gitlab_ci() {
    echo "Creating GitLab CI configuration..."

    case "$PROJECT_TYPE" in
        nodejs)
            cat > .gitlab-ci.yml <<'EOF'
stages:
  - build
  - test
  - security
  - deploy

variables:
  NODE_VERSION: "20"

build:
  stage: build
  image: node:${NODE_VERSION}
  cache:
    paths:
      - node_modules/
  script:
    - npm ci
    - npm run build || echo "Add build script"
  artifacts:
    paths:
      - dist/
      - node_modules/

test:
  stage: test
  image: node:${NODE_VERSION}
  dependencies:
    - build
  script:
    - npm run lint || echo "Add lint script"
    - npm test || echo "Add test script"

security-scan:
  stage: security
  image: aquasec/trivy:latest
  script:
    - trivy fs --severity HIGH,CRITICAL .
  allow_failure: true

deploy-production:
  stage: deploy
  script:
    - echo "Add deployment steps here"
  only:
    - main
  when: manual
EOF
            ;;

        python)
            cat > .gitlab-ci.yml <<'EOF'
stages:
  - build
  - test
  - security
  - deploy

variables:
  PYTHON_VERSION: "3.11"

build:
  stage: build
  image: python:${PYTHON_VERSION}
  cache:
    paths:
      - .venv/
  script:
    - python -m venv .venv
    - source .venv/bin/activate
    - pip install -r requirements.txt
  artifacts:
    paths:
      - .venv/

test:
  stage: test
  image: python:${PYTHON_VERSION}
  dependencies:
    - build
  script:
    - source .venv/bin/activate
    - pytest tests/ || echo "Add tests"

security-scan:
  stage: security
  image: python:${PYTHON_VERSION}
  script:
    - pip install safety
    - safety check || true

deploy-production:
  stage: deploy
  script:
    - echo "Add deployment steps here"
  only:
    - main
  when: manual
EOF
            ;;

        *)
            cat > .gitlab-ci.yml <<'EOF'
stages:
  - build
  - test
  - security
  - deploy

build:
  stage: build
  script:
    - echo "Add build steps here"

test:
  stage: test
  script:
    - echo "Add test steps here"

security-scan:
  stage: security
  image: aquasec/trivy:latest
  script:
    - trivy fs --severity HIGH,CRITICAL .
  allow_failure: true

deploy-production:
  stage: deploy
  script:
    - echo "Add deployment steps here"
  only:
    - main
  when: manual
EOF
            ;;
    esac

    echo -e "${GREEN}✓ GitLab CI configuration created${NC}"
    echo -e "  Location: .gitlab-ci.yml"
    echo
}

# Main execution
main() {
    check_prerequisites
    detect_platform
    detect_project_type

    case "$PLATFORM" in
        github)
            create_github_actions
            ;;
        gitlab)
            create_gitlab_ci
            ;;
        *)
            echo -e "${YELLOW}Platform not detected automatically${NC}"
            echo "Which CI/CD platform do you want to use?"
            echo "1) GitHub Actions"
            echo "2) GitLab CI"
            read -p "Enter choice (1 or 2): " choice

            case "$choice" in
                1)
                    create_github_actions
                    ;;
                2)
                    create_gitlab_ci
                    ;;
                *)
                    echo -e "${RED}Invalid choice${NC}"
                    exit 1
                    ;;
            esac
            ;;
    esac

    echo -e "${GREEN}================================${NC}"
    echo -e "${GREEN}   Setup Complete!${NC}"
    echo -e "${GREEN}================================${NC}"
    echo
    echo "Next steps:"
    echo "1. Review and customize the generated pipeline configuration"
    echo "2. Add deployment steps in the 'deploy' job"
    echo "3. Configure secrets (API keys, credentials) in your CI/CD platform"
    echo "4. Commit and push the changes:"
    echo "   git add ."
    echo "   git commit -m 'Add CI/CD pipeline'"
    echo "   git push"
    echo
}

main "$@"
