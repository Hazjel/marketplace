# MySQL → PostgreSQL cutover runbook

Marketplace drops its own `blue-mysql` (MySQL 8, root with an empty
password) and `blue-mongo` (MongoDB 7, no auth) and moves both onto the
shared instances in `/opt/shared-infra`, alongside the shared Redis that
Sprint C2.1B already cut over to. After this, marketplace owns **no**
datastore of its own.

| | Before | After |
|---|---|---|
| Primary DB | `blue-mysql`, MySQL 8, `api_blue`, root / empty password | `shared-postgres`, PostgreSQL 17, `blukios`, role `blukios_app` |
| Document DB | `blue-mongo`, MongoDB 7, unauthenticated | `shared-mongo`, MongoDB 8, authenticated |
| Cache / queue | `shared-redis` (already migrated, C2.1B) | unchanged |

MySQL support is removed outright rather than kept behind a driver flag,
so the rollback path is "restore the dump", not "flip a variable" — see
Rollback at the end. The dump must exist, and must have been read back,
before Phase C stops anything.

## What changed in the code

`sqlite` remains the test-suite driver, so every driver-conditional
branch is now `pgsql` vs `sqlite` where it used to be `mysql` vs
`sqlite`.

- `api-blue/Dockerfile` — `pdo_mysql` → `pdo_pgsql pgsql`,
  `default-mysql-client` → `libpq-dev postgresql-client`.
- `api-blue/config/database.php` — `mysql` and `mariadb` connections
  deleted; default connection is now `pgsql`.
- `app/Support/PostgresSearch.php` (new) — one place that owns the
  tsvector expression, the tsquery sanitiser, and the case-insensitive
  LIKE operator.
- `app/Models/Product.php`, `app/Models/Store.php` — `MATCH … AGAINST`
  → `to_tsvector @@ to_tsquery`. `Store::scopeSearch` previously had
  **no** driver guard at all, so its MySQL-only syntax also reached
  sqlite; it has one now.
- `app/Repositories/StoreRepository.php` — `ST_Distance_Sphere(POINT(…))`
  → `earth_distance(ll_to_earth(…))`. Note the argument order flips:
  `POINT()` takes (lng, lat), `ll_to_earth()` takes (lat, lng).
- `app/Repositories/TransactionAnalyticsRepository.php` — `DATE(x)` has
  no PostgreSQL equivalent by that name; `CAST(x AS DATE)` on `pgsql`,
  `DATE(x)` elsewhere (sqlite has the function but not the type).
- **12 `LIKE` call sites** across models, `SearchController`, and two
  `orderByRaw` prefix-match clauses → `PostgresSearch::likeOperator()`.
  This is the one change that is a silent behaviour difference rather
  than a syntax error: MySQL matched case-insensitively because of the
  `utf8mb4_unicode_ci` collation, PostgreSQL's `LIKE` does not. Without
  `ILIKE`, searching "Sepatu" would simply stop finding "sepatu" — no
  error, just missing results.
- Migrations — `ADD FULLTEXT INDEX` → `CREATE INDEX … USING GIN`;
  `ALTER TABLE … MODIFY COLUMN … ENUM(…)` → dropping and re-adding the
  `CHECK` constraint that Laravel's `$table->enum()` produces on
  PostgreSQL.
- New migration `2026_09_20_000001_enable_postgres_geo_extensions.php` —
  `cube` and `earthdistance`, in that order.
- `docker-compose.yml` — `mysql`, `mongodb` and `phpmyadmin` services
  and their volumes removed; `DB_*` and `DB_MONGO_*` now come from
  `.env` with no defaults for the credentials.
- `docker-compose.local.yml` (new) — local stand-ins for all three
  shared datastores, under the same container names.

## Extension privileges

`earthdistance` is **not** a trusted extension (neither is `cube`, its
dependency), so `blukios_app` cannot create it. Both are created once by
the `postgres` superuser in Phase A3; the migration's
`CREATE EXTENSION IF NOT EXISTS` is then a no-op when it runs as the
application role. If Phase A3 is skipped, the migration fails with a
permission error — loudly, which is the intent.

## Environment facts this runbook assumes

Verified on the host, not assumed:

| | |
|---|---|
| Compose project | `marketplace`, working dir `/home/fatihtesting/testingDeploy/marketplace` |
| MySQL host port | **3307** (not 3306 — `DB_HOST_PORT` is set in the production `.env`) |
| shared-postgres | `127.0.0.1:20029`, superuser `postgres`, password in `/opt/shared-infra/.env` |
| shared-mongo | `127.0.0.1:20031`, root `admin`, password in `/opt/shared-infra/.env` |
| App credentials | `/opt/shared-infra/app-credentials.env`, `0600`, convention `<APP>_APP_PASSWORD` |
| Jenkins | `pollSCM('H/5 * * * *')` on `main`, **no manual gate** |
| Backup already taken | `~/marketplace-pg-cutover/api_blue-<timestamp>.sql` |

Production MongoDB is **empty** — `blukios_mongo` does not exist, only
`admin`/`config`/`local`. There is no document data to move; Mongo needs
a user and nothing else.

Row counts at the time of writing: ~1,400 rows across 36 tables, largest
being `sessions` (518), `product_images` (198), `product_views` (137),
`products` (69), `users` (46). `transactions` is 4.

## Run these in bash, not the login shell

The account's login shell is zsh, and zsh does not honour `#` comments in
an interactive shell unless `interactive_comments` is set. Pasting any of
the blocks below straight into that prompt runs every comment line as a
command; a comment containing a `*` then becomes a glob that matches
nothing, and under `set -e` that first failure closes the session. Type
`bash` first, paste into that, and every block behaves as written.

## Ordering, and why it matters

Jenkins polls `main` every five minutes and deploys without a gate. The
moment the migration commit lands on `main`, the next poll rebuilds and
recreates the containers against whatever state the database is in. So
the commit goes to a **branch**, the cutover is performed by hand, and
the merge to `main` happens last — by then the redeploy is a no-op,
because the data is already in place and `migrate` has nothing to run.

Updating the production `.env` early is safe: running containers keep the
environment they started with, so nothing changes until they are
recreated.

## Phase A — provision (no downtime, nothing live is touched)

Write it to a file and run it with bash. Do **not** paste it straight into
the login shell: that shell is zsh, and zsh does not treat `#` as a comment
interactively unless `interactive_comments` is set — so every comment line
runs as a command, and a comment containing a `*` becomes a glob that
matches nothing. Combined with `set -e`, that first failure closes the
session.

```bash
cat > ~/phase-a.sh <<'PHASE_A'
#!/usr/bin/env bash
set -euo pipefail

CRED=/opt/shared-infra/app-credentials.env

# Application credentials, following the existing per-app password convention.
if sudo grep -q '^BLUKIOS_APP_PASSWORD=' "$CRED" 2>/dev/null; then
  echo "postgres credential: already present, reusing"
else
  echo "BLUKIOS_APP_PASSWORD=$(openssl rand -hex 24)" | sudo tee -a "$CRED" >/dev/null
  echo "postgres credential: generated"
fi

if sudo grep -q '^BLUKIOS_MONGO_PASSWORD=' "$CRED" 2>/dev/null; then
  echo "mongo credential: already present, reusing"
else
  echo "BLUKIOS_MONGO_PASSWORD=$(openssl rand -hex 24)" | sudo tee -a "$CRED" >/dev/null
  echo "mongo credential: generated"
fi
sudo chmod 600 "$CRED"

PW=$(sudo grep '^BLUKIOS_APP_PASSWORD=' "$CRED" | cut -d= -f2-)

# Role and database. The SQL arrives on stdin, never as an argument, so the
# password is not visible to anyone running `ps` on the host.
docker exec -i shared-postgres psql -U postgres -v ON_ERROR_STOP=1 -q <<SQL
DO \$\$
BEGIN
  IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'blukios_app') THEN
    ALTER ROLE blukios_app WITH LOGIN PASSWORD '$PW';
  ELSE
    CREATE ROLE blukios_app LOGIN PASSWORD '$PW';
  END IF;
END\$\$;
SQL
echo "role blukios_app: ok"

if docker exec shared-postgres psql -U postgres -At -c "SELECT 1 FROM pg_database WHERE datname='blukios'" | grep -q 1; then
  echo "database blukios: already exists"
else
  docker exec shared-postgres psql -U postgres -q -c "CREATE DATABASE blukios OWNER blukios_app"
  echo "database blukios: created"
fi
unset PW

# cube and earthdistance are NOT trusted extensions, so only a superuser can
# create them. Doing it here is what makes the Laravel migration's
# CREATE EXTENSION IF NOT EXISTS a harmless no-op when it runs as blukios_app.
docker exec -i shared-postgres psql -U postgres -d blukios -v ON_ERROR_STOP=1 -q <<'SQL'
CREATE EXTENSION IF NOT EXISTS cube;
CREATE EXTENSION IF NOT EXISTS earthdistance;
SQL
echo "extensions: ok"

# Mongo user. Production Mongo is empty, so a user is all it needs. Both
# secrets go through the environment rather than the command line, for the
# same reason the SQL above goes over stdin.
MROOT=$(sudo grep '^MONGO_ROOT_PASSWORD=' /opt/shared-infra/.env | cut -d= -f2-)
BMPW=$(sudo grep '^BLUKIOS_MONGO_PASSWORD=' "$CRED" | cut -d= -f2-)
export MROOT BMPW
docker exec -i -e MROOT -e BMPW shared-mongo mongosh --quiet <<'EOF'
const adminDb = db.getSiblingDB("admin");
if (!adminDb.auth("admin", process.env.MROOT)) {
  throw new Error("mongo auth failed");
}
const roles = [{ role: "readWrite", db: "blukios_mongo" }];
if (adminDb.getUser("blukios_app")) {
  adminDb.updateUser("blukios_app", { pwd: process.env.BMPW, roles: roles });
  print("mongo user: updated");
} else {
  adminDb.createUser({ user: "blukios_app", pwd: process.env.BMPW, roles: roles });
  print("mongo user: created");
}
EOF
unset MROOT BMPW

echo "phase A complete"
PHASE_A

bash ~/phase-a.sh
```

The script is idempotent — re-running it reuses the existing passwords and
leaves the role, database and user as they are.

Verify. Safe to re-run, and reveals no secrets:

```bash
docker exec shared-postgres psql -U postgres -d blukios -At -c "SELECT extname FROM pg_extension ORDER BY extname"
docker exec shared-postgres psql -U postgres -At -c "SELECT datname, pg_get_userbyid(datdba) FROM pg_database WHERE datname='blukios'"
```

Expect `cube`, `earthdistance`, `plpgsql`, and `blukios | blukios_app`.

Then remove the script. It holds no secrets itself, but it is one less
thing on disk that names where they live:

```bash
rm ~/phase-a.sh
```

## Phase B — wire the production `.env` (no downtime)

Running containers keep their current environment, so this changes
nothing until Phase D recreates them. It must happen before the merge to
`main`, because Compose now refuses to start without these.

```bash
cd /home/fatihtesting/testingDeploy/marketplace
cp .env ".env.bak.$(date +%Y%m%d-%H%M%S)"

PW=$(sudo grep '^BLUKIOS_APP_PASSWORD=' /opt/shared-infra/app-credentials.env | cut -d= -f2-)
BMPW=$(sudo grep '^BLUKIOS_MONGO_PASSWORD=' /opt/shared-infra/app-credentials.env | cut -d= -f2-)

set_env() {   # set_env KEY VALUE — replace in place, append if absent
  if grep -q "^$1=" .env; then
    sed -i "s|^$1=.*|$1=$2|" .env
  else
    printf '%s=%s\n' "$1" "$2" >> .env
  fi
}

set_env DB_CONNECTION pgsql
set_env DB_HOST shared-postgres
set_env DB_PORT 5432
set_env DB_DATABASE blukios
set_env DB_USERNAME blukios_app
set_env DB_PASSWORD "$PW"
set_env DB_MONGO_HOST shared-mongo
set_env DB_MONGO_PORT 27017
set_env DB_MONGO_DATABASE blukios_mongo
set_env DB_MONGO_USERNAME blukios_app
set_env DB_MONGO_PASSWORD "$BMPW"
set_env DB_MONGO_AUTHENTICATION_DATABASE admin
sed -i '/^DB_HOST_PORT=/d' .env    # MySQL's published port; nothing left to publish

unset PW BMPW
grep -oE '^DB[A-Z_]*=' .env        # key names only, no values
```

## What went wrong on the first attempt

Recorded because each failure was avoidable, and the ordering below exists
to make them impossible rather than unlikely.

1. **`git checkout` failed halfway.** 26,118 files in the deployment
   working tree were owned by root, so the checkout switched branches but
   could not replace 22 files or create 4 new ones — including
   `PostgresSearch.php` and the new migration. The `.git` directory had
   the same problem earlier in the session and was fixed; the working
   tree was never checked, although the cause (`git` run under `sudo`)
   obviously applied to both.

2. **The image never built.** `docker compose build api` printed
   `[+] build 0/1` and `NotFound: forwarding Ping: no such job …`, and
   the run continued anyway. The containers were then stopped and
   restarted from the *old* image, which has no `pdo_pgsql` — hence
   `could not find driver`. The build result was never checked before
   the writers were stopped.

3. **pgloader cannot authenticate to MySQL 8 at all.** It failed with
   `Condition QMYND:MYSQL-UNSUPPORTED-AUTHENTICATION was signalled`.
   Giving it a dedicated `mysql_native_password` account did not help --
   verified, the account really did have that plugin and pgloader still
   refused -- because its Lisp client rejects the `caching_sha2_password`
   the server advertises in the opening handshake, before any per-account
   auth switch can happen. The only fix for pgloader would be changing
   the server default and restarting production MySQL. It was replaced
   by `scripts/copy-mysql-to-postgres.py` instead.

Nothing was corrupted: `TRUNCATE migrations` only touched the still-empty
`blukios`, and pgloader failed before writing anything. The site was down
for roughly fifteen minutes and was restored by
`git checkout -f main`, restoring `.env` from its backup, and
`docker compose -p marketplace up -d`.

## Phase C1 — prepare, with no downtime

Nothing here stops a container. Every step must pass before Phase C3.

```bash
cd /home/fatihtesting/testingDeploy/marketplace

# Files the containers own (storage, bootstrap/cache) stay as they are;
# only what git needs to write has to belong to this account.
sudo chown -R fatihtesting:fatihtesting /home/fatihtesting/testingDeploy/marketplace
sudo chown -R www-data:www-data api-blue/storage api-blue/bootstrap/cache

git fetch origin
git checkout -f chore/postgres-migration
git pull --ff-only

# The checkout must be complete. Anything printed here means it is not,
# and Phase C3 must not be attempted.
test -f api-blue/app/Support/PostgresSearch.php || echo "MISSING PostgresSearch.php"
test -f api-blue/database/migrations/2026_09_20_000001_enable_postgres_geo_extensions.php \
  || echo "MISSING geo extensions migration"
git status --short | grep -vE 'storage/|bootstrap/cache|\.env\.bak' || echo "worktree clean"
```

Now build, and **verify the result** rather than trusting the exit code:

```bash
docker compose -p marketplace build api queue scheduler reverb
```

Four services build from `./api-blue` -- `api`, `queue`, `scheduler` and
`reverb` -- and Compose gives each one its own image. Building only `api`
leaves the other three on the previous image, which carries `pdo_mysql`
and not `pdo_pgsql`. They start, they log nothing obviously wrong, and
every scheduled command and queued job then fails with `could not find
driver` while the site itself looks perfectly healthy.

Verify the driver inside each **running** container, not just in the image:

```bash
for c in blue-api blue-queue blue-scheduler blue-reverb; do
  echo -n "$c: "
  docker exec $c php -r 'echo in_array("pgsql", PDO::getAvailableDrivers()) ? "pdo_pgsql OK" : "MISSING"; echo PHP_EOL;'
done
```

Anything other than four times `pdo_pgsql OK` means Phase C3 must not be
attempted. The site is still up and nothing has been lost.

`docker compose logs scheduler` printing `DONE` after each run does **not**
mean the command succeeded: that is the scheduler reporting it dispatched
the command, and the command's own output goes to `/dev/null`. Look for
`Scheduled command ... failed with exit code [1]` in
`storage/logs/laravel.log` instead.

## Phase C2 — rehearse the copy, still with no downtime

The data is copied into a throwaway database first, so that the method is
proven before the site is taken down. This is what the first attempt
skipped.

```bash
# A scratch copy of the schema, built by the same migrations.
PGPW=$(sudo grep '^POSTGRES_ROOT_PASSWORD=' /opt/shared-infra/.env | cut -d= -f2-)
docker exec shared-postgres psql -U postgres -c "DROP DATABASE IF EXISTS blukios_rehearsal"
docker exec shared-postgres psql -U postgres -c "CREATE DATABASE blukios_rehearsal OWNER blukios_app"
docker exec -i shared-postgres psql -U postgres -d blukios_rehearsal -q <<'SQL'
CREATE EXTENSION IF NOT EXISTS cube;
CREATE EXTENSION IF NOT EXISTS earthdistance;
SQL

docker compose -p marketplace run --rm --no-deps --entrypoint "" \
  -e DB_DATABASE=blukios_rehearsal api \
  php artisan migrate --force --no-interaction

docker exec shared-postgres psql -U postgres -d blukios_rehearsal -c "TRUNCATE migrations"

docker run --rm --network host \
  -v "$PWD/scripts/copy-mysql-to-postgres.py:/app/copy.py:ro" \
  -e MYSQL_HOST=127.0.0.1 -e MYSQL_PORT=3307 -e MYSQL_USER=root -e MYSQL_PASSWORD= \
  -e MYSQL_DB=api_blue \
  -e PG_HOST=127.0.0.1 -e PG_PORT=20029 -e PG_USER=postgres -e PG_PASSWORD="$PGPW" \
  -e PG_DB=blukios_rehearsal \
  python:3.12-slim \
  sh -c 'pip install -q pymysql cryptography "psycopg[binary]" && python /app/copy.py'
```

Compare every table. This is the gate for Phase C3:

```bash
for t in $(docker exec blue-mysql mysql -uroot api_blue -N \
             -e "SELECT table_name FROM information_schema.tables \
                 WHERE table_schema='api_blue' ORDER BY table_name"); do
  m=$(docker exec blue-mysql mysql -uroot api_blue -N -e "SELECT COUNT(*) FROM \`$t\`")
  p=$(docker exec shared-postgres psql -U postgres -d blukios_rehearsal -At \
        -c "SELECT COUNT(*) FROM \"$t\"" 2>/dev/null || echo MISSING)
  if [ "$m" = "$p" ]; then echo "ok    $t $m"; else echo "DIFF  $t mysql=$m pg=$p"; fi
done
```

Everything except `migrations` must read `ok`. Spot-check that the types
survived, since row counts alone would not catch a boolean or a decimal
arriving wrong:

```bash
docker exec shared-postgres psql -U postgres -d blukios_rehearsal -c \
  "SELECT id, name, price, weight, has_variants FROM products LIMIT 3"
docker exec shared-postgres psql -U postgres -d blukios_rehearsal -c \
  "SELECT count(*) FILTER (WHERE has_variants) AS with_variants, count(*) FROM products"
```

Then throw the rehearsal away:

```bash
docker exec shared-postgres psql -U postgres -c "DROP DATABASE blukios_rehearsal"
```

Keep `$PGPW` in the shell — Phase C3 reuses it. If the shell is lost,
re-read it from `/opt/shared-infra/.env`.

## Phase C3 — the real cutover (this is the downtime window)

Only if C1 and C2 both passed. The site goes down at the first line here
and comes back in Phase D.

```bash
cd /home/fatihtesting/testingDeploy/marketplace
cp .env ".env.bak.$(date +%Y%m%d-%H%M%S)"

docker compose -p marketplace stop api queue scheduler reverb

docker compose -p marketplace run --rm --no-deps --entrypoint "" api \
  php artisan migrate --force --no-interaction

docker exec shared-postgres psql -U postgres -d blukios -c "TRUNCATE migrations"

docker run --rm --network host \
  -v "$PWD/scripts/copy-mysql-to-postgres.py:/app/copy.py:ro" \
  -e MYSQL_HOST=127.0.0.1 -e MYSQL_PORT=3307 -e MYSQL_USER=root -e MYSQL_PASSWORD= \
  -e MYSQL_DB=api_blue \
  -e PG_HOST=127.0.0.1 -e PG_PORT=20029 -e PG_USER=postgres -e PG_PASSWORD="$PGPW" \
  -e PG_DB=blukios \
  python:3.12-slim \
  sh -c 'pip install -q pymysql cryptography "psycopg[binary]" && python /app/copy.py'

docker compose -p marketplace run --rm --no-deps --entrypoint "" api \
  php artisan migrate --force --no-interaction
```

Run the same table-by-table comparison as in C2, against `blukios` this
time. `migrations` differs by one (52 against 51); everything else must
read `ok`, or go to Rollback.

## Rolling back from Phase C3

`blue-mysql` and `blue-mongo` are still running with their data intact, so
this is a checkout and a file copy, not a restore:

```bash
cd /home/fatihtesting/testingDeploy/marketplace
git checkout -f main
cp .env.bak.<the backup written at the top of C3> .env
docker compose -p marketplace up -d
```

Then confirm the site is actually serving, not merely running:

```bash
curl -s http://127.0.0.1:8888/api/health
```

`{"status":"ok", … "database":"connected"}` is the answer to look for.


## Phase D — bring it back up

Use `up -d`, not `restart`. `docker compose restart` gives the application
containers new IP addresses, and nginx resolves its `fastcgi_pass` upstream
once at config load — so it keeps dialling the old address and every request
returns 502 with `connect() failed (111: Connection refused) while
connecting to upstream`. If anything is ever restarted on its own,
`docker compose -p marketplace restart nginx` afterwards is what clears it.


```bash
cd /home/fatihtesting/testingDeploy/marketplace
docker compose -p marketplace up -d
docker compose -p marketplace logs --tail 40 api
```

Then check the live site, not just the database — each of these exercises
a different rewritten query:

| Check | Exercises |
|---|---|
| Search a product, 3+ characters | `to_tsvector @@ to_tsquery` and the GIN index |
| Search with 1–2 characters | the `ILIKE` fallback |
| Search in the "wrong" case (`SEPATU`) | the case-sensitivity fix specifically |
| Store list sorted by distance | `earth_distance(ll_to_earth(…))` |
| Seller dashboard chart | `CAST(created_at AS DATE)` and `GROUP BY` on an alias |
| Place a transaction | writes, row locks, the escrow path |

Confirm the index is actually used, rather than trusting that it exists:

```bash
docker exec shared-postgres psql -U postgres -d blukios -c \
  "EXPLAIN SELECT id FROM products
   WHERE to_tsvector('simple', coalesce(name, '') || ' ' || coalesce(description, ''))
         @@ to_tsquery('simple', 'sepat:*')"
```

`Bitmap Index Scan on ft_products_search` is the answer you want. A
`Seq Scan` on a populated table means the query and the index expression
have drifted apart, and search is quietly doing a full scan.

## Phase E — merge, then decommission

Only after Phase D passes: merge the branch into `main`. Jenkins picks it
up within five minutes and redeploys; that redeploy is a no-op, since the
data is already in place and `migrate` has nothing pending.

Then, and not before:

```bash
docker stop blue-mysql blue-mongo blue-phpmyadmin blue-redis
docker rm   blue-mysql blue-mongo blue-phpmyadmin blue-redis
```

`blue-redis` is included because it has been orphaned since Sprint C2.1B —
the compose file stopped defining it, but the container kept running.

Keep the volumes (`marketplace_mysql_data`, `marketplace_mongo_data`) and
the dump in `~/marketplace-pg-cutover/` until a backup cycle has proven
the new database is being backed up.

## Rollback

See "Rolling back from Phase C3" above. Once Phase E has removed
`blue-mysql` and its volume, that route is gone and the dump in
`~/marketplace-pg-cutover/` is the only way back — which is why Phase E
waits for a backup cycle to prove the new database is being backed up.
