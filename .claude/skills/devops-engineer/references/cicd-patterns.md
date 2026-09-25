# CI/CD Pipeline Patterns

## Pipeline Stage Definitions

### 1. Build Stage
**Purpose**: Compile code, resolve dependencies, create artifacts

```yaml
build:
  steps:
    - Checkout code
    - Install dependencies (npm install, pip install, mvn package)
    - Compile/build (if compiled language)
    - Run linters (ESLint, Pylint, Rubocop)
    - Create artifact (JAR, wheel, binary)
  outputs:
    - Build artifacts
    - Dependency manifest
```

**Best Practices**:
- Use dependency caching to speed up builds
- Pin dependency versions (use lock files)
- Fail fast on linting errors
- Keep build time under 10 minutes

### 2. Test Stage
**Purpose**: Verify code correctness and quality

```yaml
test:
  unit-tests:
    - Fast tests (< 1 minute total)
    - Run on every commit
    - High coverage (>80%)
    - Mock external dependencies

  integration-tests:
    - Test component interactions
    - Use test databases/services
    - Run on pull requests
    - Moderate speed (< 5 minutes)

  e2e-tests:
    - Full system tests
    - Real browsers/environments
    - Run before deployment
    - Slower (< 15 minutes)
```

**Test Pyramid**:
```
      /\
     /E2E\        ← Fewer (slow, brittle)
    /━━━━━\
   / Integ \      ← Moderate
  /━━━━━━━━━\
 /   Unit    \    ← Many (fast, reliable)
/━━━━━━━━━━━━━\
```

### 3. Security Scan Stage
**Purpose**: Detect vulnerabilities before deployment

```yaml
security:
  sast:
    - Static code analysis
    - Tools: SonarQube, Semgrep, CodeQL

  dependency-scan:
    - Check for vulnerable dependencies
    - Tools: Snyk, Dependabot, npm audit

  secret-scan:
    - Detect hardcoded secrets
    - Tools: GitGuardian, TruffleHog

  container-scan:
    - Scan Docker images
    - Tools: Trivy, Aqua, Anchore
```

**Fail Strategy**:
- Critical vulnerabilities → Block deployment
- High vulnerabilities → Require approval
- Medium/Low → Log and track

### 4. Package Stage
**Purpose**: Create deployable artifacts

```yaml
package:
  docker:
    - Build container image
    - Tag with version/commit SHA
    - Push to registry

  non-docker:
    - Create ZIP/TAR archive
    - Upload to artifact repository
    - Generate SBOM (Software Bill of Materials)
```

**Image Tagging Strategy**:
```bash
# Multi-tag approach
docker tag app:build app:latest
docker tag app:build app:v1.2.3
docker tag app:build app:commit-abc123
docker tag app:build app:prod  # for current production
```

### 5. Deploy Stage
**Purpose**: Release to environments

```yaml
deploy:
  dev:
    - Automatic on main branch
    - No approval required

  staging:
    - Automatic after dev success
    - Run smoke tests

  production:
    - Manual approval OR
    - Automatic with canary
    - Require passing tests
    - Implement rollback plan
```

## Complete Pipeline Examples

### GitHub Actions - Node.js

```yaml
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
        run: npm run lint

      - name: Unit tests
        run: npm test

      - name: Build
        run: npm run build

      - name: Upload artifacts
        uses: actions/upload-artifact@v4
        with:
          name: build-artifacts
          path: dist/

  security-scan:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Run Trivy vulnerability scanner
        uses: aquasecurity/trivy-action@master
        with:
          scan-type: 'fs'
          severity: 'CRITICAL,HIGH'

      - name: Check dependencies
        run: npm audit --audit-level=high

  build-docker:
    needs: [build-and-test, security-scan]
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Set up Docker Buildx
        uses: docker/setup-buildx-action@v3

      - name: Login to Registry
        uses: docker/login-action@v3
        with:
          registry: ${{ secrets.REGISTRY_URL }}
          username: ${{ secrets.REGISTRY_USERNAME }}
          password: ${{ secrets.REGISTRY_PASSWORD }}

      - name: Build and push
        uses: docker/build-push-action@v5
        with:
          context: .
          push: true
          tags: |
            ${{ secrets.REGISTRY_URL }}/app:latest
            ${{ secrets.REGISTRY_URL }}/app:${{ github.sha }}
          cache-from: type=gha
          cache-to: type=gha,mode=max

  deploy-staging:
    needs: build-docker
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/main'
    steps:
      - name: Deploy to staging
        run: |
          kubectl set image deployment/app \
            app=${{ secrets.REGISTRY_URL }}/app:${{ github.sha }} \
            --namespace=staging

  deploy-production:
    needs: deploy-staging
    runs-on: ubuntu-latest
    environment: production  # Requires approval
    steps:
      - name: Deploy to production
        run: |
          kubectl set image deployment/app \
            app=${{ secrets.REGISTRY_URL }}/app:${{ github.sha }} \
            --namespace=production
```

### GitLab CI - Python

```yaml
stages:
  - build
  - test
  - security
  - package
  - deploy

variables:
  DOCKER_REGISTRY: registry.gitlab.com
  IMAGE_NAME: $CI_REGISTRY_IMAGE:$CI_COMMIT_SHA

build:
  stage: build
  image: python:3.11
  cache:
    paths:
      - .venv/
  script:
    - python -m venv .venv
    - source .venv/bin/activate
    - pip install -r requirements.txt
    - python -m pylint app/
  artifacts:
    paths:
      - .venv/

unit-test:
  stage: test
  image: python:3.11
  dependencies:
    - build
  script:
    - source .venv/bin/activate
    - pytest tests/unit/ --cov=app --cov-report=xml
  coverage: '/TOTAL.*\s+(\d+%)$/'
  artifacts:
    reports:
      coverage_report:
        coverage_format: cobertura
        path: coverage.xml

integration-test:
  stage: test
  image: python:3.11
  services:
    - postgres:15
  variables:
    POSTGRES_DB: testdb
    POSTGRES_USER: test
    POSTGRES_PASSWORD: test
  dependencies:
    - build
  script:
    - source .venv/bin/activate
    - pytest tests/integration/

security-scan:
  stage: security
  image: aquasec/trivy:latest
  script:
    - trivy fs --severity HIGH,CRITICAL .
  allow_failure: false

dependency-check:
  stage: security
  image: python:3.11
  script:
    - pip install safety
    - safety check --json

build-docker:
  stage: package
  image: docker:latest
  services:
    - docker:dind
  script:
    - docker login -u $CI_REGISTRY_USER -p $CI_REGISTRY_PASSWORD $CI_REGISTRY
    - docker build -t $IMAGE_NAME .
    - docker push $IMAGE_NAME
    - docker tag $IMAGE_NAME $CI_REGISTRY_IMAGE:latest
    - docker push $CI_REGISTRY_IMAGE:latest

deploy-staging:
  stage: deploy
  image: bitnami/kubectl:latest
  environment:
    name: staging
  only:
    - main
  script:
    - kubectl set image deployment/app app=$IMAGE_NAME -n staging
    - kubectl rollout status deployment/app -n staging

deploy-production:
  stage: deploy
  image: bitnami/kubectl:latest
  environment:
    name: production
  when: manual
  only:
    - main
  script:
    - kubectl set image deployment/app app=$IMAGE_NAME -n production
    - kubectl rollout status deployment/app -n production
```

### Jenkins - Java

```groovy
pipeline {
    agent any

    environment {
        DOCKER_REGISTRY = 'registry.example.com'
        IMAGE_NAME = "${DOCKER_REGISTRY}/app:${env.BUILD_NUMBER}"
    }

    stages {
        stage('Checkout') {
            steps {
                git branch: 'main', url: 'https://github.com/org/repo.git'
            }
        }

        stage('Build') {
            steps {
                sh 'mvn clean package -DskipTests'
            }
        }

        stage('Unit Tests') {
            steps {
                sh 'mvn test'
            }
            post {
                always {
                    junit 'target/surefire-reports/*.xml'
                    jacoco(
                        execPattern: 'target/jacoco.exec',
                        classPattern: 'target/classes',
                        sourcePattern: 'src/main/java'
                    )
                }
            }
        }

        stage('Integration Tests') {
            steps {
                sh 'mvn verify -DskipUnitTests'
            }
        }

        stage('Security Scan') {
            parallel {
                stage('SAST') {
                    steps {
                        sh 'mvn sonar:sonar -Dsonar.host.url=${SONAR_URL}'
                    }
                }
                stage('Dependency Check') {
                    steps {
                        sh 'mvn dependency-check:check'
                    }
                }
            }
        }

        stage('Build Docker Image') {
            steps {
                script {
                    docker.build(IMAGE_NAME)
                }
            }
        }

        stage('Scan Image') {
            steps {
                sh "trivy image --severity HIGH,CRITICAL ${IMAGE_NAME}"
            }
        }

        stage('Push Image') {
            steps {
                script {
                    docker.withRegistry("https://${DOCKER_REGISTRY}", 'registry-credentials') {
                        docker.image(IMAGE_NAME).push()
                        docker.image(IMAGE_NAME).push('latest')
                    }
                }
            }
        }

        stage('Deploy to Staging') {
            when {
                branch 'main'
            }
            steps {
                sh """
                    kubectl set image deployment/app \
                        app=${IMAGE_NAME} \
                        --namespace=staging
                    kubectl rollout status deployment/app -n staging
                """
            }
        }

        stage('Deploy to Production') {
            when {
                branch 'main'
            }
            input {
                message 'Deploy to production?'
                ok 'Deploy'
            }
            steps {
                sh """
                    kubectl set image deployment/app \
                        app=${IMAGE_NAME} \
                        --namespace=production
                    kubectl rollout status deployment/app -n production
                """
            }
        }
    }

    post {
        always {
            cleanWs()
        }
        success {
            slackSend(color: 'good', message: "Build ${env.BUILD_NUMBER} succeeded")
        }
        failure {
            slackSend(color: 'danger', message: "Build ${env.BUILD_NUMBER} failed")
        }
    }
}
```

## Advanced Patterns

### Matrix Builds (Multi-Platform)

```yaml
# GitHub Actions
strategy:
  matrix:
    os: [ubuntu-latest, windows-latest, macos-latest]
    node: [18, 20, 22]
runs-on: ${{ matrix.os }}
steps:
  - uses: actions/setup-node@v4
    with:
      node-version: ${{ matrix.node }}
```

### Conditional Execution

```yaml
# Only run on tags
if: startsWith(github.ref, 'refs/tags/v')

# Only run if files changed
if: contains(github.event.head_commit.modified, 'src/')
```

### Caching Dependencies

```yaml
# GitHub Actions
- uses: actions/cache@v4
  with:
    path: ~/.npm
    key: ${{ runner.os }}-node-${{ hashFiles('**/package-lock.json') }}
    restore-keys: |
      ${{ runner.os }}-node-
```

### Parallel Execution

```yaml
# GitLab CI
test:
  parallel: 4
  script:
    - npm run test -- --shard=$CI_NODE_INDEX/$CI_NODE_TOTAL
```

## Pipeline Optimization

### Speed Improvements
1. **Parallelize independent stages** (build, lint, test can run together)
2. **Cache dependencies** (npm, pip, Maven)
3. **Use smaller base images** (alpine, distroless)
4. **Skip unchanged services** (monorepo conditional builds)
5. **Incremental builds** (only rebuild changed layers)

### Resource Optimization
```yaml
# Limit resource usage
resources:
  limits:
    cpu: "2"
    memory: "4Gi"
  requests:
    cpu: "1"
    memory: "2Gi"
```

### Cost Optimization (FinOps)
- Use spot instances for non-critical jobs
- Auto-scale runners based on queue depth
- Cleanup old artifacts and images
- Monitor and alert on excessive pipeline costs

## Monitoring Pipelines

### Key Metrics
- **Build success rate**: % of successful builds
- **Build duration**: P50, P95, P99
- **Queue time**: Time waiting for runner
- **MTTR**: Mean time to repair broken builds

### Alerts
```yaml
# Alert if success rate < 80%
# Alert if P95 build time > 30 minutes
# Alert if queue time > 5 minutes
```

## Best Practices Summary

✅ **Do**:
- Keep pipelines fast (< 10 min for common path)
- Fail fast (run quick tests first)
- Make pipelines repeatable (idempotent)
- Version pipeline configuration
- Use semantic versioning for releases
- Implement comprehensive testing
- Scan for security vulnerabilities
- Use infrastructure as code for pipeline setup
- Monitor pipeline metrics

❌ **Don't**:
- Hardcode credentials (use secrets management)
- Skip tests to save time
- Deploy without testing
- Use `latest` tag in production
- Have manual steps in automated pipelines
- Ignore failed builds
- Deploy on Fridays (unless you have excellent rollback)
