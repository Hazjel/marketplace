pipeline {
    agent any

    options {
        timeout(time: 30, unit: 'MINUTES')
        disableConcurrentBuilds()
    }

    stages {
        stage('Backend: Install') {
            steps {
                dir('api-blue') {
                    sh 'composer install --no-interaction --prefer-dist --no-progress'
                }
            }
        }

        stage('Backend: Lint (Pint)') {
            steps {
                dir('api-blue') {
                    sh 'vendor/bin/pint --test'
                }
            }
        }

        stage('Backend: Test') {
            steps {
                dir('api-blue') {
                    sh '''
                        cp .env.example .env
                        php artisan key:generate
                        php artisan test
                    '''
                }
            }
        }

        stage('Frontend: Install') {
            steps {
                dir('fe-blue') {
                    sh 'npm ci'
                }
            }
        }

        stage('Frontend: Lint') {
            steps {
                dir('fe-blue') {
                    sh 'npx eslint . --max-warnings=200'
                }
            }
        }

        stage('Frontend: Test') {
            steps {
                dir('fe-blue') {
                    sh 'npm run test -- --run'
                }
            }
        }

        stage('Frontend: Build') {
            steps {
                dir('fe-blue') {
                    sh 'npm run build'
                }
            }
        }

        stage('Deploy') {
            when {
                branch 'main'
            }
            steps {
                sh '''
                    docker compose up -d --build api queue nginx frontend ai-service
                '''
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
