#!/usr/bin/env bash
# Backup harian database Blukios di shared-infra: Postgres (pg_dump -Fc) dan Mongo (mongodump --archive --gzip).
# Terpasang di crontab user deploy: 0 2 * * * ~/bin/backup-databases.sh >> ~/backups/blukios/backup.log 2>&1
set -euo pipefail

ENV_FILE=${ENV_FILE:-/home/fatihtesting/testingDeploy/marketplace/.env}
DEST=${DEST:-$HOME/backups/blukios}
KEEP_DAYS=${KEEP_DAYS:-14}

env_get() { grep -E "^$1=" "$ENV_FILE" | tail -1 | cut -d= -f2- | sed -E 's/^"(.*)"$/\1/' || true; }

umask 077
mkdir -p "$DEST"
ts=$(date +%Y%m%d-%H%M%S)

pg_db=$(env_get DB_DATABASE)
pg="$DEST/postgres-$pg_db-$ts.dump"
export PGPASSWORD
PGPASSWORD=$(env_get DB_PASSWORD)
docker exec -e PGPASSWORD shared-postgres pg_dump -U "$(env_get DB_USERNAME)" -d "$pg_db" -Fc > "$pg.part"
# pg_restore -l membaca seluruh katalog arsip, jadi dump yang terpotong gagal sekarang, bukan saat dibutuhkan.
tables=$(docker exec -i shared-postgres pg_restore -l < "$pg.part" | grep -c 'TABLE DATA' || true)
[ "$tables" -gt 0 ] || { echo "$(date -Is) GAGAL: $pg tanpa TABLE DATA"; exit 1; }
mv "$pg.part" "$pg"

mg_db=$(env_get DB_MONGO_DATABASE)
mg_auth=$(env_get DB_MONGO_AUTHENTICATION_DATABASE)
mg="$DEST/mongo-$mg_db-$ts.archive.gz"
export MONGO_PWD
MONGO_PWD=$(env_get DB_MONGO_PASSWORD)
# Password lewat file config sementara di dalam container, bukan argumen yang terlihat di daftar proses.
docker exec -i -e MONGO_PWD shared-mongo sh -c '
    umask 077; f=$(mktemp); printf "password: %s\n" "$MONGO_PWD" > "$f"
    mongodump --quiet --config "$f" --username "$1" --authenticationDatabase "$2" --db "$3" --archive --gzip
    rc=$?; rm -f "$f"; exit $rc' _ "$(env_get DB_MONGO_USERNAME)" "${mg_auth:-admin}" "$mg_db" > "$mg.part"
gzip -t "$mg.part"
mv "$mg.part" "$mg"

find "$DEST" -maxdepth 1 -type f \( -name '*.dump' -o -name '*.archive.gz' \) -mtime +"$KEEP_DAYS" -delete
find "$DEST" -maxdepth 1 -type f -name '*.part' -mtime +1 -delete

echo "$(date -Is) ok $(basename "$pg") ($(du -h "$pg" | cut -f1), $tables tabel) $(basename "$mg") ($(du -h "$mg" | cut -f1))"
