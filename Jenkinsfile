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
                    sh 'composer install --no-interaction --prefer-dist --no-progress --ignore-platform-req=ext-mongodb'
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
                        apt-get update -qq
                        apt-get install -y -qq git unzip libsqlite3-dev libzip-dev libssl-dev libpng-dev libjpeg-dev libfreetype6-dev libwebp-dev libonig-dev >/dev/null
                        docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp >/dev/null
                        docker-php-ext-install pdo_sqlite zip bcmath gd exif mbstring >/dev/null
                        pecl install mongodb redis >/dev/null 2>&1
                        docker-php-ext-enable mongodb redis
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
                // job Pipeline biasa (bukan Multibranch) tidak set env.BRANCH_NAME,
                // jadi cek GIT_BRANCH dari step checkout sebagai gantinya
                expression { env.GIT_BRANCH == 'origin/main' || env.GIT_BRANCH == 'main' }
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
