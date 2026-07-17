pipeline {
    agent none

    options {
        timeout(time: 30, unit: 'MINUTES')
        disableConcurrentBuilds()
    }

    stages {
        stage('Backend: Install') {
            agent {
                docker {
                    image 'composer:2'
                    args '-u root'
                }
            }
            steps {
                dir('api-blue') {
                    sh 'composer install --no-interaction --prefer-dist --no-progress'
                    stash name: 'backend-vendor', includes: 'vendor/**'
                }
            }
        }

        stage('Backend: Lint & Test') {
            agent {
                docker {
                    image 'php:8.4-cli'
                    args '-u root'
                }
            }
            steps {
                dir('api-blue') {
                    unstash 'backend-vendor'
                    sh '''
                        apt-get update -qq && apt-get install -y -qq git unzip libsqlite3-dev >/dev/null
                        docker-php-ext-install pdo_sqlite >/dev/null
                        vendor/bin/pint --test
                        cp .env.example .env
                        php artisan key:generate
                        php artisan test
                    '''
                }
            }
        }

        stage('Frontend: Install & Test') {
            agent {
                docker {
                    image 'node:20-alpine'
                }
            }
            steps {
                dir('fe-blue') {
                    sh '''
                        npm ci
                        npx eslint . --max-warnings=200
                        npm run test -- --run
                        npm run build
                    '''
                }
            }
        }

        stage('Deploy') {
            agent any
            when {
                branch 'main'
            }
            steps {
                sh 'docker compose up -d --build api queue nginx frontend ai-service'
            }
        }
    }

    post {
        failure {
            echo 'Build gagal — cek log stage di atas.'
        }
        success {
            echo 'Build sukses.'
        }
    }
}
