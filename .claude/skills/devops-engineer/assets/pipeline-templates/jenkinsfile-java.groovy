pipeline {
    agent any

    environment {
        JAVA_VERSION = '17'
        MAVEN_OPTS = '-Xmx1024m'
        DOCKER_REGISTRY = 'registry.example.com'
        APP_NAME = 'my-java-app'
        IMAGE_TAG = "${env.BUILD_NUMBER}"
    }

    tools {
        maven 'Maven 3.9'
        jdk "JDK ${JAVA_VERSION}"
    }

    stages {
        stage('Checkout') {
            steps {
                checkout scm
                script {
                    env.GIT_COMMIT_SHORT = sh(
                        script: 'git rev-parse --short HEAD',
                        returnStdout: true
                    ).trim()
                }
            }
        }

        stage('Build') {
            steps {
                sh 'mvn clean compile'
            }
        }

        stage('Test') {
            parallel {
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

                stage('Static Analysis') {
                    steps {
                        sh 'mvn sonar:sonar -Dsonar.host.url=${SONAR_URL} -Dsonar.login=${SONAR_TOKEN}'
                    }
                }
            }
        }

        stage('Security Scan') {
            parallel {
                stage('Dependency Check') {
                    steps {
                        sh 'mvn dependency-check:check'
                    }
                    post {
                        always {
                            publishHTML([
                                reportDir: 'target/dependency-check-report',
                                reportFiles: 'dependency-check-report.html',
                                reportName: 'OWASP Dependency Check'
                            ])
                        }
                    }
                }

                stage('SAST Scan') {
                    steps {
                        sh 'mvn spotbugs:spotbugs'
                    }
                }
            }
        }

        stage('Package') {
            steps {
                sh 'mvn package -DskipTests'
            }
            post {
                success {
                    archiveArtifacts artifacts: 'target/*.jar', fingerprint: true
                }
            }
        }

        stage('Build Docker Image') {
            steps {
                script {
                    docker.build("${DOCKER_REGISTRY}/${APP_NAME}:${IMAGE_TAG}")
                    docker.build("${DOCKER_REGISTRY}/${APP_NAME}:${GIT_COMMIT_SHORT}")
                }
            }
        }

        stage('Container Security Scan') {
            steps {
                sh """
                    trivy image --severity HIGH,CRITICAL \
                    ${DOCKER_REGISTRY}/${APP_NAME}:${IMAGE_TAG}
                """
            }
        }

        stage('Push Docker Image') {
            steps {
                script {
                    docker.withRegistry("https://${DOCKER_REGISTRY}", 'docker-credentials') {
                        docker.image("${DOCKER_REGISTRY}/${APP_NAME}:${IMAGE_TAG}").push()
                        docker.image("${DOCKER_REGISTRY}/${APP_NAME}:${GIT_COMMIT_SHORT}").push()

                        if (env.BRANCH_NAME == 'main') {
                            docker.image("${DOCKER_REGISTRY}/${APP_NAME}:${IMAGE_TAG}").push('latest')
                        }
                    }
                }
            }
        }

        stage('Deploy to Staging') {
            when {
                branch 'develop'
            }
            steps {
                script {
                    sh """
                        kubectl set image deployment/${APP_NAME} \
                        ${APP_NAME}=${DOCKER_REGISTRY}/${APP_NAME}:${IMAGE_TAG} \
                        -n staging

                        kubectl rollout status deployment/${APP_NAME} -n staging
                    """
                }
            }
        }

        stage('Deploy to Production') {
            when {
                branch 'main'
            }
            steps {
                input message: 'Deploy to production?', ok: 'Deploy'
                script {
                    sh """
                        kubectl set image deployment/${APP_NAME} \
                        ${APP_NAME}=${DOCKER_REGISTRY}/${APP_NAME}:${IMAGE_TAG} \
                        -n production

                        kubectl rollout status deployment/${APP_NAME} -n production
                    """
                }
            }
        }
    }

    post {
        always {
            cleanWs()
        }
        success {
            slackSend(
                color: 'good',
                message: "Build ${env.BUILD_NUMBER} succeeded for ${env.JOB_NAME}"
            )
        }
        failure {
            slackSend(
                color: 'danger',
                message: "Build ${env.BUILD_NUMBER} failed for ${env.JOB_NAME}"
            )
        }
    }
}
