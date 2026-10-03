#!/bin/sh
# Weekly dump of Blukios' own databases: Postgres `blukios` in shared-postgres and
# Mongo `blukios_mongo` in shared-mongo (other teams' databases are not touched).
# Runs on the server from host cron, in the production checkout; see README "Backups".
# Only ONE backup is kept, in $BACKUP_DIR/latest: each verified run replaces the
# previous one. A run that fails leaves the previous backup untouched.
#
#   20 2 * * 0 cd ~/testingDeploy/marketplace && sh scripts/backup-db.sh >> ~/backups/blukios/backup.log 2>&1
#
# Credentials come from the checkout's .env and are passed to docker by variable
# name or on stdin, never as arguments: `ps` on this shared host shows argv to
# every user.
#
# The owner's laptop pulls latest/ weekly (scripts/pull-backup.ps1), so a lost
# server disk does not take the only copy with it.
set -eu
umask 077   # dumps hold buyers' names, addresses and phone numbers

cd "$(dirname "$0")/.."
BACKUP_DIR="${BACKUP_DIR:-$HOME/backups/blukios}"

# Read one key from .env without sourcing it (values are not shell-quoted).
env_val() { sed -n "s/^$1=//p" .env | tail -n 1 | sed -e 's/^"\(.*\)"$/\1/' -e "s/^'\(.*\)'$/\1/"; }

PG_CONTAINER="$(env_val DB_HOST)";             PG_CONTAINER="${PG_CONTAINER:-shared-postgres}"
PG_DB="$(env_val DB_DATABASE)";                PG_DB="${PG_DB:-blukios}"
PG_USER="$(env_val DB_USERNAME)"
MONGO_CONTAINER="$(env_val DB_MONGO_HOST)";    MONGO_CONTAINER="${MONGO_CONTAINER:-shared-mongo}"
MONGO_DB="$(env_val DB_MONGO_DATABASE)";       MONGO_DB="${MONGO_DB:-blukios_mongo}"
MONGO_USER="$(env_val DB_MONGO_USERNAME)"
MONGO_AUTH_DB="$(env_val DB_MONGO_AUTHENTICATION_DATABASE)"; MONGO_AUTH_DB="${MONGO_AUTH_DB:-admin}"

work="$BACKUP_DIR/.partial"
rm -rf "$work"   # leftover of a run that was killed
mkdir -p "$work"
trap 'rm -rf "$work"' EXIT
echo "$(date -Is) backup mulai"

# Postgres: custom format (compressed, restorable table by table with pg_restore).
PGPASSWORD="$(env_val DB_PASSWORD)" docker exec -e PGPASSWORD "$PG_CONTAINER" \
    pg_dump -U "$PG_USER" -d "$PG_DB" -Fc > "$work/postgres.dump"
# Restore drill: a dump only counts if it restores. Load it into a throwaway
# Postgres (same image as production, no network, gone afterwards) and require
# the users table back. shared-postgres is not touched.
drill="blukios-restore-drill-$$"
trap 'docker rm -f "$drill" > /dev/null 2>&1; rm -rf "$work"' EXIT
docker run -d --rm --name "$drill" --network none -e POSTGRES_HOST_AUTH_METHOD=trust \
    "$(docker inspect -f '{{.Config.Image}}' "$PG_CONTAINER")" > /dev/null
# TCP, not the socket: the image's init server answers on the socket and then restarts.
i=0; until docker exec "$drill" pg_isready -q -h 127.0.0.1 -U postgres; do
    i=$((i + 1)); [ "$i" -le 60 ] || { echo "restore drill: Postgres tidak siap" >&2; exit 1; }; sleep 1
done
docker exec -i "$drill" pg_restore -U postgres -d postgres --no-owner --no-privileges --exit-on-error \
    < "$work/postgres.dump"
users="$(docker exec "$drill" psql -U postgres -Atc 'select count(*) from users')"
[ "$users" -gt 0 ] || { echo "restore drill: tabel users kosong setelah restore" >&2; exit 1; }
docker rm -f "$drill" > /dev/null
echo "restore drill ok: $users users"

# Mongo: the password goes in a config file read from stdin, not on the command line.
printf "password: '%s'\n" "$(env_val DB_MONGO_PASSWORD | sed "s/'/''/g")" |
    docker exec -i "$MONGO_CONTAINER" mongodump --config=/dev/stdin --quiet \
        --username="$MONGO_USER" --authenticationDatabase="$MONGO_AUTH_DB" \
        --db="$MONGO_DB" --archive --gzip > "$work/mongo.archive.gz"
gzip -t "$work/mongo.archive.gz"

date -Is > "$work/created_at"

# Verified: only now replace the old backup.
rm -rf "$BACKUP_DIR/.previous"
[ -d "$BACKUP_DIR/latest" ] && mv "$BACKUP_DIR/latest" "$BACKUP_DIR/.previous"
mv "$work" "$BACKUP_DIR/latest"
trap - EXIT
rm -rf "$BACKUP_DIR/.previous"

# Only a verified dump counts; ops:check emails when this goes stale (config/ops.php).
docker exec -u www-data blue-api php artisan ops:backup-done
echo "$(date -Is) backup selesai: $(du -sh "$BACKUP_DIR/latest" | cut -f1)"
