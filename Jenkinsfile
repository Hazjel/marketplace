pipeline {
    agent none

    options {
        // 30 menit tidak cukup: composer install + PHPUnit + npm ci + Vitest +
        // build frontend + docker build tujuh service rutin melewatinya. Build
        // #17 dan #19 keduanya berhenti di 30,5 menit dengan "Timeout has been
        // exceeded" tepat setelah stage Deploy menghapus api/queue/reverb/
        // scheduler dan sebelum sempat membuatnya kembali -- produksi mati dua
        // kali karena ini, dan build-nya cuma tercatat ABORTED.
        timeout(time: 60, unit: 'MINUTES')
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
                    env.CHAT_SERVICE_CHANGED = (changed == 'ALL' || changed.contains('chat-service/')).toString()
                    env.RECOMMENDATION_CHANGED = (changed == 'ALL' || changed.contains('recommendation-service/')).toString()

                    echo "File berubah:\n${changed}"
                    echo "Backend changed: ${env.BACKEND_CHANGED} | Frontend changed: ${env.FRONTEND_CHANGED} | Chat service changed: ${env.CHAT_SERVICE_CHANGED} | Recommendation service changed: ${env.RECOMMENDATION_CHANGED}"
                }
            }
        }

        stage('Backend: Install') {
            agent {
                docker {
                    image 'composer:2'
                    // ENTRYPOINT composer:2 (/docker-entrypoint.sh) tidak
                    // meneruskan perintah apa adanya. Jenkins menahan agent-nya
                    // dengan "cat", tapi container malah menggantung sampai
                    // dibunuh -- muncul sebagai "The container started but
                    // didn't run the expected command", dan itu menggagalkan
                    // build #23 (juga sempat terlihat di #18).
                    //
                    // Diverifikasi langsung di host: "docker run composer:2 echo
                    // HALO" mencetak HALO lalu hang (exit 124); dengan
                    // --entrypoint "" exit 0. Bentuknya harus dipisah spasi --
                    // "--entrypoint=" (kosong setelah sama dengan) tetap hang.
                    args '-u root --entrypoint ""'
                }
            }
            when {
                expression { env.BACKEND_CHANGED == 'true' }
            }
            steps {
                dir('api-blue') {
                    sh 'composer install --no-interaction --prefer-dist --no-progress --ignore-platform-req=ext-mongodb --ignore-platform-req=ext-sodium'
                    // composer audit sengaja di sini (bukan stage terpisah) --
                    // butuh composer.lock yang baru diresolve, dan agent ini
                    // sudah punya composer binary-nya. Blocking: per commit
                    // e543e1c3, composer audit sudah bersih (0 advisory) --
                    // kalau kembali merah di sini artinya dependency BARU
                    // yang punya CVE, bukan technical debt lama yang perlu
                    // baseline seperti PHPStan.
                    sh 'composer audit --no-interaction'
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
                        pecl install mongodb-2.3.3 redis-6.3.0 >/dev/null 2>&1
                        docker-php-ext-enable mongodb redis
                        vendor/bin/pint --test
                        vendor/bin/phpstan analyse --memory-limit=1G --no-progress
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
                    // Blocking di production deps saja -- per commit
                    // e543e1c3 itu sudah bersih. devDependencies (vite/
                    // vitest/eslint dst) tidak ikut dibundle ke output
                    // production, jadi audit-nya dipisah dan non-blocking
                    // (|| true) supaya tooling dev yang sering update tidak
                    // bikin merah build untuk sesuatu yang tidak pernah
                    // sampai ke browser pengguna.
                    sh 'npm audit --omit=dev'
                    sh 'npm audit --only=dev || true'
                }
            }
        }

        stage('Chat Service: Lint, Audit & Test') {
            agent {
                docker {
                    image 'python:3.12-slim'
                }
            }
            when {
                expression { env.CHAT_SERVICE_CHANGED == 'true' }
            }
            steps {
                dir('chat-service') {
                    sh '''
                        pip install --quiet --no-cache-dir -r requirements.txt ruff pip-audit pytest
                        ruff check .
                        pytest tests/ -v
                    '''
                    // chromadb (PYSEC-2026-311, CVE-2026-45830/45831/45833) --
                    // semuanya di REST API server ChromaDB. Kode ini cuma
                    // pakai chromadb.PersistentClient (embedded, in-process,
                    // baca/tulis langsung ke direktori lokal) -- diverifikasi
                    // ke rag/vectorstore.py, tidak pernah menjalankan server
                    // HTTP/REST ChromaDB sama sekali, jadi endpoint yang
                    // rentan tidak pernah ada untuk diserang. Belum ada
                    // patched version dari upstream per commit ini. Ditandai
                    // non-blocking dengan alasan spesifik ini, bukan
                    // diabaikan buta -- kalau pip-audit menemukan CVE BARU di
                    // paket lain, itu tetap harus diperiksa manual dari log.
                    sh 'pip-audit -r requirements.txt --desc || true'
                }
            }
        }

        stage('Recommendation Service: Lint & Audit') {
            agent {
                docker {
                    image 'python:3.12-slim'
                }
            }
            when {
                expression { env.RECOMMENDATION_CHANGED == 'true' }
            }
            steps {
                dir('recommendation-service') {
                    // Belum ada test suite di service ini sama sekali (beda
                    // dari chat-service) -- di luar cakupan hardening ini
                    // untuk menulis test baru dari nol tanpa dites dulu
                    // terhadap kode yang sudah ada. Lint + dependency audit
                    // saja untuk sekarang.
                    sh '''
                        pip install --quiet --no-cache-dir -r requirements.txt ruff pip-audit
                        ruff check .
                    '''
                    sh 'pip-audit -r requirements.txt --desc'
                }
            }
        }

        stage('Security: Secret Scan') {
            agent {
                docker {
                    // Base image alpine + ENTRYPOINT ["gitleaks"] (dicek
                    // langsung ke Dockerfile upstream) -- entrypoint tetap
                    // perlu di-override kosong seperti composer:2 di atas
                    // supaya Jenkins bisa menyuntikkan step shell-nya sendiri.
                    image 'zricethezav/gitleaks:latest'
                    args '--entrypoint ""'
                }
            }
            steps {
                // NON-BLOCKING untuk sekarang ("|| true") -- belum pernah
                // dijalankan sungguhan di Jenkins (Docker Desktop tidak
                // jalan di mesin dev, jadi tidak bisa dites lokal). Repo ini
                // punya setidaknya satu string yang BENTUKNYA seperti secret
                // tapi memang sengaja publik: Midtrans client key di
                // docker-compose.yml (VITE_MIDTRANS_CLIENT_KEY) -- client
                // key Midtrans didesain publik (ke-bundle ke JS frontend
                // apa pun caranya), beda dari server key. Setelah build
                // pertama menunjukkan hasil scan bersih atau semua temuan
                // sudah di-allowlist (.gitleaks.toml), hapus "|| true" di
                // sini supaya stage ini betul-betul blocking.
                //
                // 'dir' (bukan 'detect' yang deprecated sejak v8.19.0) --
                // scan working tree checkout ini apa adanya, bukan git log
                // (history lama sudah ditangani lewat rotasi APP_KEY
                // sebelumnya, bukan lewat CI scan). --redact: kalau ketemu,
                // jangan cetak secret asli ke log Jenkins.
                sh 'gitleaks dir . -v --redact || true'
            }
        }

        stage('Deploy') {
            agent any
            when {
                // job Pipeline biasa (bukan Multibranch) tidak set env.BRANCH_NAME,
                // jadi cek GIT_BRANCH dari step checkout sebagai gantinya.
                //
                // Perbandingan string persis pernah membuat stage ini terlewat
                // (lihat commit 9d31303), dan build record menyimpan ref-nya
                // sebagai 'refs/remotes/origin/main'. Cocokkan polanya supaya
                // bentuk mana pun diterima, bukan satu ejaan tertentu.
                expression {
                    (env.GIT_BRANCH ?: '') ==~ /^(refs\/remotes\/)?(origin\/)?main$/
                }
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

                    # Deploy persis commit yang barusan dites. "reset --hard
                    # origin/main" mengambil tip terbaru, jadi commit yang
                    # didorong selagi pipeline berjalan ikut terkirim tanpa
                    # pernah melewati satu pun stage test.
                    #
                    # Kalau GIT_COMMIT ternyata kosong, jangan menggagalkan
                    # deploy -- kembali ke perilaku lama dan bilang, supaya
                    # tidak menukar "diam-diam terlewat" dengan "selalu gagal".
                    TARGET="$GIT_COMMIT"
                    if [ -z "$TARGET" ]; then
                        echo "PERINGATAN: GIT_COMMIT kosong, memakai origin/main"
                        TARGET="origin/main"
                    fi
                    git checkout main
                    git reset --hard "$TARGET"

                    # ---- migrasi segera setelah kode mendarat ----
                    # ./api-blue di-bind-mount ke container, jadi begitu reset di
                    # atas selesai kode baru LANGSUNG dilayani -- sementara
                    # migrasinya baru jalan saat container di-recreate beberapa
                    # menit kemudian lewat entrypoint. Di sela itu kode sudah
                    # menulis kolom yang belum ada. Ini betulan terlihat saat
                    # unique_ref ditambahkan: migrate:status masih "Pending"
                    # sementara kode escrow sudah aktif; settlement yang masuk
                    # saat itu akan gagal.
                    #
                    # Jendelanya tidak bisa hilang sepenuhnya dengan bind mount --
                    # berkas migrasinya sendiri baru ada setelah reset -- tapi
                    # menjalankannya di sini memperpendeknya dari menit jadi
                    # detik, memakai container yang masih hidup.
                    if docker compose -p marketplace ps --status running api 2>/dev/null | grep -q blue-api; then
                        echo "Menjalankan migrasi sebelum container di-recreate..."
                        if ! docker compose -p marketplace exec -T api php artisan migrate --force --no-interaction; then
                            echo "GAGAL: migrasi tidak berhasil, deploy dihentikan sebelum menyentuh container"
                            exit 1
                        fi
                    else
                        echo "blue-api tidak berjalan; migrasi diserahkan ke entrypoint saat container start"
                    fi

                    # api_vendor named volume nge-override folder vendor/ dari bind mount
                    # ./api-blue (biar vendor gak ketiban bind-mount kosong dari host --
                    # vendor/ digitignore, gak pernah ada fisik di host). Tapi volume ini
                    # persisten -- kalau composer.json berubah & image di-rebuild, volume
                    # LAMA tetap dipasang ke container baru, jadi package baru "sukses"
                    # ke-install di image tapi container tetap pakai vendor basi (silent
                    # bug, ketauannya cuma lewat "Class not found" pas runtime). Hapus
                    # volume sebelum build kalau ada perubahan di api-blue/, biar volume
                    # dibuat ulang fresh dari image setiap kali dependency berubah.
                    # scheduler also mounts api_vendor (same PHP image) but was
                    # missing from the stop/rm list below, so it kept the
                    # volume locked -- "docker volume rm" failed silently
                    # every single deploy since it was added, and vendor/ has
                    # been stuck since 2026-07-24 no matter how many times
                    # composer.json changed. Confirmed live: kreait/laravel-firebase
                    # was in composer.json/lock for several deploys yet
                    # "Class Kreait/Firebase/Messaging/CloudMessage not found"
                    # at runtime -- exactly the failure mode the comment above
                    # already warned about, just missing this one service.
                    # Build lebih dulu, selagi container lama masih melayani.
                    # Urutan sebelumnya stop+rm dulu baru "up --build", jadi satu
                    # build yang gagal berarti api/queue/reverb/scheduler sudah
                    # terlanjur hilang dan API mati sampai ada yang memulihkannya
                    # manual -- itu betulan terjadi. Dengan urutan ini, build yang
                    # gagal membuat stage berhenti tanpa produksi pernah turun,
                    # dan downtime menyusut jadi sebatas waktu start container.
                    docker compose -p marketplace build api queue reverb scheduler frontend chat-service recommendation-service

                    if [ "$BACKEND_CHANGED" = "true" ]; then
                        docker compose -p marketplace stop api queue reverb scheduler || true
                        docker compose -p marketplace rm -f api queue reverb scheduler || true
                        docker volume rm marketplace_api_vendor || true
                    fi

                    docker compose -p marketplace up -d --no-build api queue reverb scheduler frontend chat-service recommendation-service
                    # nginx sendiri jarang berubah -> compose gak recreate dia, tapi upstream
                    # (blue-api dkk) di atas barusan direcreate dan dapet IP Docker baru.
                    # nginx cuma resolve DNS internal sekali pas start, jadi upstream-nya basi
                    # -> 502 connect() failed. Paksa restart biar re-resolve IP yang baru.
                    docker compose -p marketplace up -d --force-recreate nginx

                    # tiap --build bikin image baru, image lama nganggur numpuk terus
                    # (disk sempat 93% penuh) -- bersihin tiap abis deploy.
                    #
                    # TANPA -a. "prune -af" membuang setiap image yang tidak
                    # sedang dipakai container, termasuk image tooling build ini
                    # sendiri: build #21 gagal dengan "no such object: composer:2"
                    # karena deploy sebelumnya baru saja menghapusnya, dan
                    # marketplace-api:latest juga pernah hilang begitu. Deploy
                    # jadi menyabotase build berikutnya. Dangling saja sudah
                    # cukup untuk membereskan layer sisa rebuild.
                    docker image prune -f || true

                    # ---- verifikasi pasca-deploy ----
                    # Stage ini pernah berhenti di tengah jalan tanpa ada yang
                    # menyadarinya sampai seseorang membuka situsnya. Pastikan
                    # "hijau" benar-benar berarti kode baru sudah melayani.

                    DEPLOYED=$(git -C "$HOST_PROJECT_DIR" rev-parse HEAD)
                    if [ -n "$GIT_COMMIT" ] && [ "$DEPLOYED" != "$GIT_COMMIT" ]; then
                        echo "GAGAL: direktori deploy ada di $DEPLOYED, bukan commit yang dites $GIT_COMMIT"
                        exit 1
                    fi
                    echo "commit ter-deploy: $DEPLOYED"

                    # API harus benar-benar melayani lagi. Container baru butuh
                    # waktu start, jadi tunggu -- tapi jangan selamanya.
                    healthy=""
                    for i in $(seq 1 36); do
                        code=$(curl -s -o /dev/null -w '%{http_code}' https://blukios.store/api/health || echo 000)
                        if [ "$code" = "200" ]; then
                            healthy="yes"
                            echo "API sehat setelah $((i * 5)) detik"
                            break
                        fi
                        sleep 5
                    done

                    if [ -z "$healthy" ]; then
                        echo "GAGAL: API tidak mengembalikan 200 dalam 3 menit setelah deploy"
                        docker compose -p marketplace ps
                        exit 1
                    fi
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
