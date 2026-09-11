# C2.1B — Shared Redis cutover runbook

Sprint C2.1B removes `marketplace`'s own Redis (`blue-redis`) and points
every Redis-using service at a shared Redis instance (`shared-redis`)
running outside this repo, in `/opt/shared-infra`.

## ⚠️ Merge is forbidden until the shared ACL user exists

Merging this PR into `main` auto-deploys via Jenkins (`pollSCM`, no manual
gate). This compose file requires `REDIS_USERNAME`/`REDIS_PASSWORD`
(`${VAR:?...}` — compose refuses to start without them) and assumes an
external Docker network named `shared-infra-net` already exists on the
host. **If either the `blukios` ACL user or the network doesn't exist yet
when this merges, the very next deploy fails to bring the API back up.**

Do not merge until every gate below has passed, in order.

## Sequence

1. **Prepare persistent Redis ACL configuration in `/opt/shared-infra`.**
   Not part of this PR — a separate, manual change to the shared-infra
   stack, reviewed independently.
2. **Preserve the existing `default` user/credential.** Other applications
   already depend on it; this cutover must not touch or rotate it.
3. **Create a dedicated `blukios` ACL identity** — its own username and
   password, never reusing `default`'s credential.
4. **Key restriction: `~blukios:*`.** This is the actual isolation
   boundary, not the Redis logical DB — `db0` already holds another
   service's `asynq:*` keys, and Redis ACL key patterns aren't scoped per
   logical DB.
5. **Channel restriction**, if pub/sub channels are used: `&blukios:*`
   (Blukios doesn't currently use Redis pub/sub, but restrict this
   defensively rather than leaving channels unrestricted).
6. **No administrative capability** — the `blukios` user must not be able
   to run `ACL`, `CONFIG`, `DEBUG`, `MODULE`, `FLUSHALL`, `FLUSHDB`, or
   `SHUTDOWN`. Do not otherwise hand-craft a restrictive command allowlist
   before confirming what Laravel's queue/cache/lock operations and
   chat-service actually issue — start from "remove dangerous admin
   commands" and tighten later from observed command usage, not the
   reverse.
7. **Make the ACL persistent** (`aclfile` or equivalent) so it survives a
   container recreate or host reboot — an in-memory-only `ACL SETUSER`
   disappears on the next `shared-redis` restart.
8. **Test the `blukios` credential from a disposable container** attached
   to `shared-infra-net` — never from a marketplace container, and never
   from this repo's scripts. This PR does not and must not provision ACL
   state itself. Do **not** pass the password via `-a` (it lands in shell
   history and is visible to anyone who can run `ps` on the host while the
   command executes); use `REDISCLI_AUTH` instead:
   ```sh
   read -rs REDISCLI_AUTH   # paste the password, it won't echo
   export REDISCLI_AUTH
   docker run --rm --network shared-infra-net -e REDISCLI_AUTH \
     redis:7-alpine redis-cli -h shared-redis --user blukios PING
   unset REDISCLI_AUTH
   ```
9. **Verify `blukios` can read/write `blukios:*`.**
10. **Verify `blukios` cannot read/write the existing `asynq:*` keys** (or
    anything outside `blukios:*`) — this is the actual proof the
    isolation works, not just that the happy path works.
11. **Enter maintenance mode** (`docker compose -p marketplace exec -T api
    php artisan down`) — see "Producer quiescence" below for why this,
    not "traffic is probably low," is what actually closes the
    queue/idempotency race.
12. **Inventory Redis-dependent state still on `blue-redis`, with the app
    quiesced.** See "Redis state before cutover" below — this step is not
    optional, and a non-empty queue backlog or an unexpectedly large
    idempotency-key count on db1 is a hard stop, not a warning.
13. **Set marketplace's production `REDIS_HOST`, `REDIS_PORT`,
    `REDIS_USERNAME`, `REDIS_PASSWORD`, `REDIS_PREFIX` in the production
    `.env`** (gitignored, never committed).
14. **Only after 1–13 pass**, merge this PR.
15. Jenkins picks it up (`pollSCM`) and deploys — this rebuilds
    `api`/`queue`/`reverb`/`scheduler`/`chat-service` and attaches them to
    `shared-infra-net`. See "chat-service reload window" below for a
    subtlety specific to this deploy's bind-mount + `--reload` setup.
16. **Verify each service actually reconnected**: `api` health check,
    `queue` processing a job, `scheduler`'s next tick, `reverb`
    broadcasting, `chat-service` session/summary/cache round-tripping.
17. **Verify new keys are `blukios:*`** — `redis-cli --user blukios ...
    SCAN 0 MATCH 'blukios:*'` (never `KEYS` on a shared production
    instance with an unknown/large key count — `KEYS` blocks the whole
    server while it runs; `SCAN` doesn't).
18. **Verify the existing `asynq:*` workload is untouched** — same
    `SCAN`/sampling approach as the original inventory, comparing before
    and after.
19. **Only after 16–18 pass, exit maintenance mode** (`php artisan up`).
    The app stays quiesced from step 11 until this point — that's the
    entire point of bracketing the cutover in maintenance mode.
20. **Keep the orphaned `blue-redis` container as a rollback path** until
    production verification (16–18) passes. See "Rollback: what
    `blue-redis` actually gives you" below — its persistence guarantee is
    weaker than "a volume," and rollback isn't free once shared Redis has
    accepted new queue work.
21. **Only then** retire `marketplace`-local Redis (stop/remove
    `blue-redis`, eventually reclaim its writable layer) — a separate,
    later change, not part of this PR or this deploy.
22. **Shared `default` credential rotation is a separate, later,
    coordinated operation** — out of scope here entirely; this cutover
    must not require or trigger it.

## Producer quiescence (why "low-traffic risk is small" isn't the policy)

Both the queue gate and the idempotency gate below are point-in-time
counts. A count of 0 taken while the app can still accept requests proves
nothing about the moment the deploy actually flips `REDIS_HOST` — a
checkout arriving in between re-populates exactly the state the gate just
confirmed was empty. Reducing `queue` worker consumers doesn't help either:
it slows draining, but does nothing to stop the `api` container from still
accepting requests that dispatch new jobs or write new idempotency
results.

The deterministic fix already built into Laravel: `php artisan down`
before checking anything, `php artisan up` after post-deploy verification
passes (steps 11 and 19). While down, `api` returns 503 to normal traffic
(a `--secret=<token>` bypass exists for verifying `/api/health` yourself
during the window if needed) — no new HTTP request can dispatch a job or
write an idempotency-cached result for the entire merge → deploy → verify
sequence. This does not stop the `scheduler` container's own
`schedule:run` loop, but the two scheduled commands
(`transaction:check-expiry`, `transaction:auto-complete`) run their logic
directly against MySQL and don't dispatch to the Redis queue, so they
don't reopen either gate.

## Redis state before cutover

Flipping `REDIS_HOST`/`REDIS_USERNAME`/`REDIS_PASSWORD` doesn't move any
existing data from `blue-redis` to `shared-redis` — every key currently in
`blue-redis` simply becomes unreachable to the app the moment the new
containers come up pointed elsewhere. That's fine for state that's
genuinely disposable, and not fine for state that isn't. Classify each
kind before cutover, don't assume:

| State | Where | Disposition |
|---|---|---|
| Laravel queue (`queues:default`, `:delayed`, `:reserved`) | `api`/`queue`, `default` Redis connection (db0) | **Must be drained to empty before cutover — see gate below.** Jobs left behind in `blue-redis` are silently abandoned; nothing re-delivers them once the app stops looking at that instance. |
| **Idempotency locks/results (`IdempotencyMiddleware`)** | `api`, `cache` Redis connection (**db1**, not db0) | **Must be inventoried before cutover — see gate below. Not disposable.** A completed transaction's result is cached for **24 hours** specifically so a client retry replays it instead of re-processing — that's the double-charge/double-stock-decrement protection the middleware's own docblock describes. Losing an active entry mid-window doesn't just lose a cache hit: a legitimate client retry with the same `X-Idempotency-Key` after cutover would see no matching lock at all on the new instance and get processed as a brand-new request. This is *not* equivalent to an ordinary deploy — an ordinary deploy doesn't swap Redis instances, `blue-redis` stays up and reachable across it. |
| chat-service session/summary/LLM cache | `chat-service` | Deliberately reset. TTL-bound already (`SESSION_TTL_SECONDS`=1h, LLM cache=5min) — losing it mid-conversation is a minor UX blip (history restarts), not a data-loss or correctness issue. No migration needed. |
| Laravel application cache (`cache` connection, db1) | `api` | Deliberately reset. Derived/rebuildable data (e.g. the product listing cache) — repopulates on next request/write. No migration needed. |
| Rate-limit counters | `api`, same cache store | Deliberately reset. Losing them just means every client's rate-limit window restarts at zero-used, which is *more* permissive briefly, not less — not a security regression. |

### Queue drain gate (mandatory, blocks merge if non-empty)

The repo currently has two `ShouldQueue` jobs (`ProcessProductImageJob`,
`GenerateAiChatReplyJob`) on the default Redis queue connection. With the
app in maintenance mode (step 11) — inventory pending, delayed, and
reserved (in-flight) jobs using Laravel itself rather than hand-rolling
prefixed key names against `blue-redis`:

```sh
docker compose -p marketplace exec -T api php artisan tinker --execute="
use Illuminate\Support\Facades\Redis;
\$c = Redis::connection('default');
echo 'pending:  ' . \$c->llen('queues:default') . PHP_EOL;
echo 'delayed:  ' . \$c->zcard('queues:default:delayed') . PHP_EOL;
echo 'reserved: ' . \$c->zcard('queues:default:reserved') . PHP_EOL;
"
```

(Laravel's Redis client applies the connection's configured prefix
automatically — this doesn't need to know or guess what `REDIS_PREFIX` is
currently set to on `blue-redis`.)

- **All three must read 0** before merging. **Verified in production
  2026-09-11 (pre-maintenance-mode, informational only — re-run this
  inside the actual maintenance-mode window at real cutover time, not
  carried forward from this reading): `pending=0, delayed=0, reserved=0`.**
- With maintenance mode active (step 11) this is now an actual guarantee,
  not a snapshot — nothing can enqueue a new job while `api` is down, so a
  0 reading here stays 0 until you bring the app back up in step 19.
- If a backlog won't drain (a stuck/failing job) even with the worker
  still running before you enter maintenance mode, that's a decision
  point, not something to script around here: either fix/discard the
  stuck job through normal `failed_jobs` handling, or explicitly decide +
  document an app-level replay (e.g. re-dispatching
  `ProcessProductImageJob` for the affected products after cutover)
  before proceeding. Do not merge with a known non-empty backlog on the
  assumption it'll "still be there."

### Idempotency & cache state gate (db1, mandatory)

With the app still in maintenance mode, count active idempotency entries
on the `cache` connection's database — read-only, counts only, never
prints a key name or a cached response body (a cached idempotent result
is the actual response payload of a past transaction):

```sh
docker exec blue-redis sh -lc '
count=$(redis-cli -n 1 --scan --pattern "*idempotency:*" | wc -l)
echo "idempotency_keys_db1=$count"
'
```

- **Verified in production 2026-09-11: `idempotency_keys_db1=0`.** Same
  caveat as the queue gate — this is a snapshot from the review, not a
  standing guarantee; re-run it inside the actual maintenance-mode window
  at cutover time.
- **If the count is 0** (as it was here): no active replay-protection
  entries would be stranded — proceed.
- **If the count is `> 0`**: this is a hard stop, not a judgment call to
  wave through. A non-zero count means at least one client has a
  legitimate reason to retry with the same idempotency key sometime in
  the next 24 hours and expects that retry to replay, not reprocess.
  Options, in order of preference:
  1. Wait — maintenance mode is already blocking new requests, so the
     count can only fall from here; if it's small, waiting a few minutes
     inside the same window may be enough for the relevant clients'
     retries to already have landed against the old instance.
  2. If it isn't converging quickly, this needs a deliberate, reviewed
     decision (not something to automate from this doc): either extend
     the maintenance window, or accept and *document* the specific
     narrow-window risk before proceeding (which specific transactions,
     what the actual exposure is), rather than silently discarding it.
  3. Migrating the entries to `shared-redis` (`RESTORE`/`DUMP`-based
     copy, or a one-off script that reads db1 on `blue-redis` and writes
     the equivalent keys under `blukios:` on `shared-redis`) is possible
     but adds real complexity for what should be a short-lived, low-count
     window — only worth it if the count is consistently non-trivial;
     don't build this speculatively.

## chat-service reload window (why config.py has a legacy REDIS_URL fallback)

The production `chat-service` container runs `uvicorn --reload` against a
bind-mounted checkout (`./chat-service:/app`), and Jenkins' Deploy stage
does `git reset --hard` on that checkout **before** rebuilding/recreating
containers. That means the *already-running* old process can reload this
PR's new source while its container environment still only has the old
`REDIS_URL=redis://redis:6379/0` — component vars
(`REDIS_HOST`/`PORT`/`USERNAME`/`PASSWORD`/`DB`) don't exist in that
container's env until Compose actually recreates it later in the same
deploy.

`chat-service/config.py`'s `_resolve_redis_config()` handles this
narrowly: component vars are authoritative whenever `REDIS_HOST` is
present; `REDIS_URL` is parsed as a fallback *only* when `REDIS_HOST` is
absent. During the reload window this means the stale process keeps
talking to `redis` (still resolvable — nothing has stopped `blue-redis`
yet at that point in the deploy), instead of falling through to a dead
`localhost` default for the rest of the build. Once Compose recreates the
container with the new compose file's env (component vars only, no
`REDIS_URL` set at all), it connects to `shared-redis` correctly.

This is transitional. Once every environment (including local dev, if it
ever set `REDIS_URL`) has moved to the component vars, this fallback path
can be deleted — track that as follow-up cleanup after C2.1B is fully
deployed and verified, not before.

## Rollback: what `blue-redis` actually gives you

`docker-compose.yml` (both before and after this PR) declares **no named
volume** for `blue-redis` — unlike `mysql_data`/`mongo_data`, there is no
`redis_data:` entry. Whatever persistence `blue-redis` has lives entirely
in that specific container's own writable layer:

- Survives a plain `stop`/`start` or host reboot (the container's
  filesystem persists).
- Does **not** survive `docker rm`/`docker compose down` or any
  accidental recreation of that specific container — there is no separate
  volume to reattach afterward. Once the container is removed, rollback
  has nothing to roll back to.

Do not describe or rely on a "`blue-redis` volume" — there isn't one.
**Before relying on it as a rollback path**, confirm at runtime what's
actually there rather than assuming docker-compose.yml tells the whole
story (someone may have added a bind mount manually, outside compose):

```sh
docker inspect blue-redis --format '{{json .Mounts}}'
```

If that returns an empty list (`[]`), `blue-redis`'s only copy of
whatever's in it is that one container's writable layer — do not `rm` or
otherwise recreate that specific container until cutover confidence is
established, and do not treat it as durable storage.

**Rollback also isn't symmetric once shared Redis has taken live queue
traffic.** If the cutover is reverted (`REDIS_*` flipped back to
`blue-redis`) *after* jobs have already been dispatched against
`shared-redis`, those jobs are now stranded on the instance nobody's
pointed at anymore — the same one-directional abandonment risk as the
forward cutover, just in reverse. Re-run the same drain/inventory check
against `shared-redis` before rolling back, not just before rolling
forward.

### Why `blue-redis` is still there to roll back to at all

The Jenkins `Deploy` stage's `docker compose -p marketplace up -d --no-build
...` and `--force-recreate nginx` do **not** pass `--remove-orphans`. Since
`blue-redis` is no longer declared in `docker-compose.yml` after this PR,
Compose treats it as an orphaned container it no longer manages — it does
**not** stop or remove it. It keeps running untouched (whatever that's
actually worth — see above) alongside the new `shared-redis`-backed
containers.

This means **retiring it is a deliberate, separate step (§21 above)**, not
something that happens for free. If a future change adds
`--remove-orphans` to the Deploy stage, this assumption breaks — recheck
this section before doing so.

## What this PR does and doesn't do

This PR only prepares `marketplace` to *consume* a shared Redis identity
that doesn't exist yet. It does not:

- touch `/opt/shared-infra` or its compose file
- create, modify, or test any real Redis ACL user
- change production `.env`
- deploy, or merge itself

## Redis consumers in this repo

| Service | Language | Uses Redis for | Env vars |
|---|---|---|---|
| `api` | Laravel | cache, queue (default connection), idempotency lock, rate limiting | `REDIS_HOST/PORT/USERNAME/PASSWORD/PREFIX/CLIENT` |
| `queue` | Laravel | queue worker (same connection as `api`) | same |
| `scheduler` | Laravel | cache (for scheduled command state) | same |
| `reverb` | Laravel | cache store | same |
| `chat-service` | Python (FastAPI) | session history, conversation summaries, LLM response cache, feedback log | `REDIS_HOST/PORT/USERNAME/PASSWORD/DB/KEY_PREFIX` |
| `recommendation-service` | Python (FastAPI) | — (confirmed: does not use Redis) | — |

Laravel's `config/database.php` already had `REDIS_USERNAME`/
`REDIS_PASSWORD`/`REDIS_PREFIX` support (the `options.prefix` applies to
every Redis connection — default *and* cache — uniformly), so no PHP code
changed. chat-service's `config.py`/`utils/redis_helper.py` were refactored
to build the client from components instead of a credential-bearing
`REDIS_URL`, and to prefix every `chat:*` key through one shared helper.

**Confirmed in production (2026-09-11) that two separate prefix layers
exist, not one:** `config('database.redis.options.prefix')` (what
`REDIS_PREFIX` controls — applied by the Redis client itself, outermost,
to every raw command on every connection) currently reads
`blukios-database-`, while `config('cache.prefix')` (a completely
separate Laravel cache-repository layer, applied in PHP *before* the key
reaches the client) reads `blukios-cache-`. A cache/idempotency key's
actual wire-level name is therefore `{REDIS_PREFIX}{cache.prefix}{key}` —
e.g. `blukios:blukios-cache-idempotency:...` once `REDIS_PREFIX=blukios:`
is set. Since the client-level prefix is applied last (outermost), the
ACL pattern `~blukios:*` still correctly covers it — `cache.prefix` is an
internal Laravel namespacing detail, not a second isolation boundary to
account for separately, and this PR doesn't need to (and doesn't) touch
`CACHE_PREFIX`.
