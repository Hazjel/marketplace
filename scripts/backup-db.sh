#!/bin/sh
# Nightly dump of Blukios' own databases: Postgres `blukios` in shared-postgres and
# Mongo `blukios_mongo` in shared-mongo (other teams' databases are not touched).
# Runs on the server from host cron, in the production checkout; see README "Backups".
#
#   20 2 * * * cd ~/testingDeploy/marketplace && sh scripts/backup-db.sh >> ~/backups/blukios/backup.log 2>&1
#
# Credentials come from the checkout's .env and are passed to docker by variable
# name or on stdin, never as arguments: `ps` on this shared host shows argv to
# every user.
#
# ponytail: dumps stay on the same disk as the databases, which covers a bad query
# or a dropped table but not a lost disk. Copy BACKUP_DIR off the server once there
# is somewhere to put it.
set -eu
umask 077   # dumps hold buyers' names, addresses and phone numbers

cd "$(dirname "$0")/.."
BACKUP_DIR="${BACKUP_DIR:-$HOME/backups/blukios}"
KEEP_DAYS="${KEEP_DAYS:-14}"

# Read one key from .env without sourcing it (values are not shell-quoted).
env_val() { sed -n "s/^$1=//p" .env | tail -n 1 | sed -e 's/^"\(.*\)"$/\1/' -e "s/^'\(.*\)'$/\1/"; }

PG_CONTAINER="$(env_val DB_HOST)";             PG_CONTAINER="${PG_CONTAINER:-shared-postgres}"
PG_DB="$(env_val DB_DATABASE)";                PG_DB="${PG_DB:-blukios}"
PG_USER="$(env_val DB_USERNAME)"
MONGO_CONTAINER="$(env_val DB_MONGO_HOST)";    MONGO_CONTAINER="${MONGO_CONTAINER:-shared-mongo}"
MONGO_DB="$(env_val DB_MONGO_DATABASE)";       MONGO_DB="${MONGO_DB:-blukios_mongo}"
MONGO_USER="$(env_val DB_MONGO_USERNAME)"
MONGO_AUTH_DB="$(env_val DB_MONGO_AUTHENTICATION_DATABASE)"; MONGO_AUTH_DB="${MONGO_AUTH_DB:-admin}"

stamp="$(date +%Y-%m-%d_%H%M)"
work="$BACKUP_DIR/.partial-$stamp"
mkdir -p "$work"
trap 'rm -rf "$work"' EXIT
echo "$(date -Is) backup $stamp mulai"

# Postgres: custom format (compressed, restorable table by table with pg_restore).
PGPASSWORD="$(env_val DB_PASSWORD)" docker exec -e PGPASSWORD "$PG_CONTAINER" \
    pg_dump -U "$PG_USER" -d "$PG_DB" -Fc > "$work/postgres.dump"
# A dump pg_restore cannot list is not a backup.
docker exec -i "$PG_CONTAINER" pg_restore --list < "$work/postgres.dump" > /dev/null

# Mongo: the password goes in a config file read from stdin, not on the command line.
printf "password: '%s'\n" "$(env_val DB_MONGO_PASSWORD | sed "s/'/''/g")" |
    docker exec -i "$MONGO_CONTAINER" mongodump --config=/dev/stdin --quiet \
        --username="$MONGO_USER" --authenticationDatabase="$MONGO_AUTH_DB" \
        --db="$MONGO_DB" --archive --gzip > "$work/mongo.archive.gz"
gzip -t "$work/mongo.archive.gz"

mv "$work" "$BACKUP_DIR/$stamp"
trap - EXIT
find "$BACKUP_DIR" -mindepth 1 -maxdepth 1 -type d -name '20*' -mtime +"$KEEP_DAYS" -exec rm -rf {} +

# Only a verified dump counts; ops:check emails when this goes stale (config/ops.php).
docker exec -u www-data blue-api php artisan ops:backup-done
echo "$(date -Is) backup $stamp selesai: $(du -sh "$BACKUP_DIR/$stamp" | cut -f1)"
