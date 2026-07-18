pipeline {
    agent none

    options {
        timeout(time: 30, unit: 'MINUTES')
        disableConcurrentBuilds()
        // JENKINS_HOME numpuk terus tiap build (workspace + build record) sampai
        // disk host hampir penuh — batasi histori biar otomatis kebersihin
        buildDiscarder(logRotator(numToKeepStr: '15'))
    }

    triggers {
        // Gak ada webhook GitHub -> Jenkins (Jenkins ini gak publicly reachable),
        // jadi pakai polling tiap 5 menit sebagai gantinya
        pollSCM('H/5 * * * *')
    }

    stages {
        stage('Detect Changes') {
            agent any
            steps {
                script {
                    // pollSCM cuma checkout HEAD, gak ada histori commit sebelumnya
                    // di workspace secara default -> fetch depth cukup buat diff
                    // terhadap commit sebelum HEAD saat ini
                    sh 'git fetch --depth=2 origin main || true'
                    def changed = sh(
                        script: 'git diff --name-only HEAD~1 HEAD 2>/dev/null || echo "ALL"',
                        returnStdout: true
                    ).trim()

                    // build pertama / histori dangkal -> HEAD~1 gak ada -> anggap semua berubah
                    env.BACKEND_CHANGED  = (changed == 'ALL' || changed.contains('api-blue/')).toString()
                    env.FRONTEND_CHANGED = (changed == 'ALL' || changed.contains('fe-blue/')).toString()

                    echo "File berubah:\n${changed}"
                    echo "Backend changed: ${env.BACKEND_CHANGED} | Frontend changed: ${env.FRONTEND_CHANGED}"
                }
            }
        }

        stage('Backend: Install') {
            agent {
                docker {
                    image 'composer:2'
                    args '-u root'
                }
            }
            when {
                expression { env.BACKEND_CHANGED == 'true' }
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
            when {
                expression { env.BACKEND_CHANGED == 'true' }
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
            when {
                expression { env.FRONTEND_CHANGED == 'true' }
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
                // jalan dari /host-project (bind mount direktori host), BUKAN workspace
                // checkout Jenkins sendiri — biar .env host (gitignored, gak ada di
                // checkout Jenkins) ikut kepakai. Project name di-pin manual ke
                // "marketplace" (-p marketplace) karena nama folder mount di dalam
                // container Jenkins ("host-project") beda dari nama folder host
                // ("marketplace") — kalau nggak di-pin, compose infer project name
                // beda dan container_name yang di-hardcode (blue-mongo dkk) bentrok
                // sama container punya stack yang udah running.
                sh '''
                    git config --global --add safe.directory "$HOST_PROJECT_DIR"
                    cd "$HOST_PROJECT_DIR"
                    git fetch origin main
                    git checkout main
                    git reset --hard origin/main

                    # api_vendor named volume nge-override folder vendor/ dari bind mount
                    # ./api-blue (biar vendor gak ketiban bind-mount kosong dari host --
                    # vendor/ digitignore, gak pernah ada fisik di host). Tapi volume ini
                    # persisten -- kalau composer.json berubah & image di-rebuild, volume
                    # LAMA tetap dipasang ke container baru, jadi package baru "sukses"
                    # ke-install di image tapi container tetap pakai vendor basi (silent
                    # bug, ketauannya cuma lewat "Class not found" pas runtime). Hapus
                    # volume sebelum build kalau ada perubahan di api-blue/, biar volume
                    # dibuat ulang fresh dari image setiap kali dependency berubah.
                    if [ "$BACKEND_CHANGED" = "true" ]; then
                        docker compose -p marketplace stop api queue reverb || true
                        docker compose -p marketplace rm -f api queue reverb || true
                        docker volume rm marketplace_api_vendor || true
                    fi

                    docker compose -p marketplace up -d --build api queue reverb frontend chat-service recommendation-service
                    # nginx sendiri jarang berubah -> compose gak recreate dia, tapi upstream
                    # (blue-api dkk) di atas barusan direcreate dan dapet IP Docker baru.
                    # nginx cuma resolve DNS internal sekali pas start, jadi upstream-nya basi
                    # -> 502 connect() failed. Paksa restart biar re-resolve IP yang baru.
                    docker compose -p marketplace up -d --force-recreate nginx

                    # tiap --build bikin image baru, image lama nganggur numpuk terus
                    # (disk sempat 93% penuh) -- bersihin image gak kepake tiap abis deploy
                    docker image prune -af || true
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
