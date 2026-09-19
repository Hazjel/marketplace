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

## Phase C — cutover (this is the downtime window)

```bash
cd /home/fatihtesting/testingDeploy/marketplace
git fetch origin
git checkout chore/postgres-migration    # the branch, NOT main — Jenkins only polls main
git pull --ff-only

# C1. Build the new image (pdo_pgsql). Running containers are untouched.
docker compose -p marketplace build api

# C2. Stop every writer. The site is down from here.
docker compose -p marketplace stop api queue scheduler reverb

# C3. Create the schema in the empty database.
docker compose -p marketplace run --rm --no-deps --entrypoint "" api \
  php artisan migrate --force --no-interaction

# C4. Clear the migrations table so the copy below can bring MySQL's own rows
#     across without colliding on the primary key. The one migration that
#     exists only in the new code is re-applied in C6, and it is idempotent.
docker exec shared-postgres psql -U postgres -d blukios -c "TRUNCATE migrations"

# C5. Copy the data. pgloader is used rather than a dumped-and-edited SQL file
#     because MySQL and Postgres disagree on things a text substitution gets
#     silently wrong: tinyint(1) has to become a real boolean (Postgres
#     rejects INSERT ... VALUES (0) into a boolean column), and MySQL escapes
#     quotes with backslashes, which Postgres reads literally. Connecting as
#     the superuser is what allows "disable triggers", which is what makes
#     foreign-key ordering across 36 tables a non-issue.
PGPW=$(sudo grep '^POSTGRES_ROOT_PASSWORD=' /opt/shared-infra/.env | cut -d= -f2-)
docker run --rm --network host dimitri/pgloader:latest pgloader \
  --with "data only" --with "disable triggers" \
  mysql://root@127.0.0.1:3307/api_blue \
  "postgresql://postgres:$PGPW@127.0.0.1:20029/blukios"
unset PGPW

# C6. Re-apply the one migration whose record C4 erased.
docker compose -p marketplace run --rm --no-deps --entrypoint "" api \
  php artisan migrate --force --no-interaction
```

Verify the copy before bringing anything up — compare both sides table by
table, do not eyeball a total:

```bash
for t in $(docker exec blue-mysql mysql -uroot api_blue -N \
             -e "SELECT table_name FROM information_schema.tables \
                 WHERE table_schema='api_blue' ORDER BY table_name"); do
  m=$(docker exec blue-mysql mysql -uroot api_blue -N -e "SELECT COUNT(*) FROM \`$t\`")
  p=$(docker exec shared-postgres psql -U postgres -d blukios -At \
        -c "SELECT COUNT(*) FROM \"$t\"" 2>/dev/null || echo MISSING)
  if [ "$m" = "$p" ]; then echo "ok    $t $m"; else echo "DIFF  $t mysql=$m pg=$p"; fi
done
```

`migrations` is expected to differ by one (52 in Postgres, 51 in MySQL).
Every other line must read `ok`. If anything else differs, stop and go to
Rollback rather than starting the site.

## Phase D — bring it back up

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

MySQL is removed from the code outright, so rolling back means moving the
deployment back to a commit that still has it — `d08c8f4` or earlier — and
restoring `.env` from the `.env.bak.*` written in Phase B. `blue-mysql`
and its volume survive until Phase E, so as long as that container has not
been removed, no restore from the dump is needed. Once it has, the dump in
`~/marketplace-pg-cutover/` is the only way back.
