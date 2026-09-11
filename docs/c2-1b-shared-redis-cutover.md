# C2.1B — Shared Redis cutover runbook

Sprint C2.1B removes `marketplace`'s own Redis (`blue-redis`) and points
every Redis-using service at a shared Redis instance (`shared-redis`)
running outside this repo, in `/opt/shared-infra`.

## Status (2026-09-11)

The shared-infra ACL provisioning gate (steps 1–10 below) has been
**completed and verified in production**, independently of this PR's
code — see "Final ACL policy (as provisioned)" below for exactly what
was set up. What has **not** happened yet:

- Marketplace production `.env` has **not** been switched to shared
  Redis — `api`/`queue`/`scheduler`/`reverb`/`chat-service` are still
  running against `blue-redis`.
- `api` has **not** been stopped for the queue/idempotency quiescence
  gates (steps 11–13).
- **PR #20 is still open and unmerged.** No marketplace deploy driven by
  this PR has occurred.
- No credential or password hash is committed to this repo at any point
  in this process — the `blukios` password lives only in the operator's
  shell and, eventually, production's gitignored `.env`.
- The shared `default` credential was **not** rotated as part of this —
  out of scope, see step 22.

This PR's code has not changed since `a0300c6` — this revision is a
documentation reconciliation only, to make the runbook match what
`/opt/shared-infra` actually looks like now, and to fix a verification
step that the final ACL policy makes impossible as originally written
(see "Post-cutover namespace verification" below).

## ⚠️ Merge is still forbidden until production `.env` is wired

The ACL gate passing doesn't change this: merging this PR into `main`
auto-deploys via Jenkins (`pollSCM`, no manual gate). This compose file
requires `REDIS_USERNAME`/`REDIS_PASSWORD` (`${VAR:?...}` — compose
refuses to start without them). **If production `.env` isn't updated
with real `blukios` credentials before this merges, the very next deploy
fails to bring the API back up.**

Do not merge until every gate below has passed, in order.

## Sequence

1. ✅ **Done (2026-09-11).** Prepare persistent Redis ACL configuration in
   `/opt/shared-infra`. Not part of this PR — a separate, manual change
   to the shared-infra stack, reviewed independently.
2. ✅ **Done.** Preserve the existing `default` user/credential. Other
   applications already depend on it; verified it survived
   `shared-redis`'s recreate onto the new `--aclfile` startup, and was
   **not** rotated.
3. ✅ **Done.** Create a dedicated `blukios` ACL identity — its own
   username and password, never reusing `default`'s credential.
4. ✅ **Done.** Key restriction: `~blukios:*`. This is the actual
   isolation boundary, not the Redis logical DB — `db0` holds ~9,605
   other keys (`asynq:*`, another service's workload) and `db5` holds 1
   more; Redis ACL key patterns aren't scoped per logical DB, and both
   survived the cutover work unchanged. Verified: `blukios` gets
   `NOPERM` reading an existing `asynq:*` key.
5. ✅ **Done.** Channel restriction: `&blukios:*` (Blukios doesn't
   currently use Redis pub/sub, restricted defensively anyway).
6. ✅ **Done — and hardened further than originally planned.** See
   "Final ACL policy (as provisioned)" below for the exact policy and
   why it also denies `SCAN`/`RANDOMKEY`/`PUBSUB` on top of the
   originally-planned admin-command denials — that wasn't part of the
   original plan and was added after testing found a real gap.
7. ✅ **Done.** ACL persisted via `--aclfile /data/users.acl` on a
   persistent named Docker volume — verified the ACL (both `default` and
   `blukios`, including the hardening in step 6) survives a `shared-redis`
   container recreate, not just an in-memory `ACL SETUSER`. File is
   `redis:redis`, mode `0600`, contains password **hashes**, not
   plaintext. A pre-hardening and a post-hardening copy of the ACL file
   are both kept outside this repo under `/opt/shared-infra/backups/`
   (root-only, `0600`, SHA-256 recorded) — no credential or hash is
   committed here.
8. ✅ **Done.** Tested the `blukios` credential from a disposable
   container attached to `shared-infra-net` — not from a marketplace
   container, not from this repo's scripts (this PR still does not and
   must not provision ACL state itself). Verified unauthenticated `PING`
   returns `NOAUTH` and `blukios` authenticates successfully. Password
   passed via `REDISCLI_AUTH`, never `-a` (which would land in shell
   history and be visible to anyone running `ps` on the host during the
   command):
   ```sh
   read -rs REDISCLI_AUTH   # paste the password, it won't echo
   export REDISCLI_AUTH
   docker run --rm --network shared-infra-net -e REDISCLI_AUTH \
     redis:7-alpine redis-cli -h shared-redis --user blukios PING
   unset REDISCLI_AUTH
   ```
9. ✅ **Done.** Verified `blukios` can `PING`/`SET`/`GET` under
   `blukios:*`.
10. ✅ **Done.** Verified `blukios` cannot read/write the existing
    `asynq:*` keys (`NOPERM`), and — after the hardening in step 6 —
    cannot `SCAN`, `RANDOMKEY`, `PUBSUB CHANNELS`, or `INFO` either. This
    is the actual proof the isolation works, not just that the happy
    path works.
11. **Stop the `api` container** (`docker compose -p marketplace stop
    api`) — **do not stop `queue` yet.** See "Producer quiescence" below
    for why this, not Laravel maintenance mode, is what actually closes
    the queue/idempotency race without breaking Jenkins' own health
    check later in this same sequence. This intentionally starts an
    application outage — see the note on outage duration below before
    running this step.
12. **With `api` stopped, inventory the queue via the still-running
    `queue` container** (not `api` — it's down). All of pending, delayed,
    reserved must read 0. See "Queue drain gate" below for the exact
    command.
13. **With `api` still stopped, re-check the idempotency count on
    `blue-redis` db1.** Must read 0. See "Idempotency & cache state gate"
    below. If either this or step 12 is non-zero, **abort** — do not
    proceed to step 14, and see "Aborting before cutover completes"
    below for how to safely restore service.
14. **Set marketplace's production `REDIS_HOST`, `REDIS_PORT`,
    `REDIS_USERNAME`, `REDIS_PASSWORD`, `REDIS_PREFIX` in the production
    `.env`** (gitignored, never committed).
15. **Only after 1–14 pass**, merge this PR.
16. Jenkins picks it up (`pollSCM`) and deploys. Because `blue-api` isn't
    running, Jenkins' own pre-recreate migration step already defers to
    the new container's entrypoint (existing `Jenkinsfile` behavior,
    unrelated to this PR) — this is expected, not a failure. This
    rebuilds `api`/`queue`/`reverb`/`scheduler`/`chat-service` and
    attaches them to `shared-infra-net`. See "chat-service reload window"
    below for a subtlety specific to this deploy's bind-mount +
    `--reload` setup.
17. **Verify each service actually reconnected**: Jenkins' own
    `/api/health` poll passing is the first signal (the new `api`
    container starts fresh, not in maintenance mode, so it can answer
    200 normally); also check `queue` processing a job, `scheduler`'s
    next tick, `reverb` broadcasting, `chat-service` session/summary/cache
    round-tripping.
18. **Verify the app is actually writing under `blukios:*`** using
    real application behavior, not `SCAN` as `blukios` — the final ACL
    denies that command to this identity by design. See "Post-cutover
    namespace verification" below for the corrected procedure.
19. **Verify the existing `asynq:*` workload is untouched** — an
    administrator-credential-only check, same as step 18's note; see
    below.
20. **Keep the orphaned `blue-redis` container as a rollback path** until
    production verification (17–19) passes. See "Rollback: what
    `blue-redis` actually gives you" below — its persistence guarantee is
    weaker than "a volume," and rollback isn't free once shared Redis has
    accepted new queue work.
21. **Only then** retire `marketplace`-local Redis (stop/remove
    `blue-redis`, eventually reclaim its writable layer) — a separate,
    later change, not part of this PR or this deploy.
22. **Shared `default` credential rotation is a separate, later,
    coordinated operation** — out of scope here entirely; this cutover
    must not require or trigger it.

## Final ACL policy (as provisioned)

The `blukios` ACL identity's effective policy, conceptually (no password
or password hash belongs in this repo — the real `ACL SETUSER` line lives
only in `/opt/shared-infra`, never here):

```
reset
on
<dedicated password, set only in /opt/shared-infra>
~blukios:*
&blukios:*
+@all
-@dangerous
-scan
-randomkey
-pubsub
```

`+@all -@dangerous` is the "remove dangerous admin commands, tighten
later from observed usage" starting point steps 6/10 originally called
for — `@dangerous` covers `CONFIG`, `DEBUG`, `MODULE`, `FLUSHALL`,
`FLUSHDB`, `SHUTDOWN`, `ACL`, and similar. Verified: `INFO` and those
commands all return `NOPERM` for `blukios`.

**`-scan`, `-randomkey`, and `-pubsub` were not part of the original
plan — they were added after production testing found a real gap.**
Redis's key-pattern ACL (`~blukios:*`) correctly denies *reading or
writing* a key outside that pattern (confirmed: `blukios` gets `NOPERM`
touching an existing `asynq:*` key). It does **not**, by itself, stop
`SCAN` from *enumerating the names* of keys outside the pattern — `SCAN`
walks the whole keyspace and only key-pattern-filters what it returns if
you ask it to with `MATCH`, and even then the command itself isn't
namespace-restricted. A `blukios`-authenticated client could `SCAN` and
see that keys named `asynq:...` exist (metadata: names, not values or
access) even though it could never read their contents. `RANDOMKEY` has
the same shape of leak for a single key. `PUBSUB CHANNELS` is the
equivalent for channel names. These three explicit denies close that
metadata-enumeration path — `blukios` now cannot learn anything about
what else lives on the shared instance, not just fail to access it.

Verified after hardening (persisted via `ACL SAVE` into
`/data/users.acl`, confirmed to survive a `shared-redis` recreate):
`SCAN`/`RANDOMKEY`/`PUBSUB CHANNELS` → `NOPERM`; normal `PING`/`SET`/`GET`
on `blukios:*` → still works; `EXISTS` on an `asynq:*` key → still
`NOPERM`.

**Consequence for this runbook: `blukios` cannot run the `SCAN`-based
post-cutover verification originally written into steps 18–19.** See
"Post-cutover namespace verification" below for the corrected procedure
— do not weaken this ACL to make the old command work.

## Producer quiescence (why this is `docker compose stop api`, not `php artisan down`)

Both the queue gate and the idempotency gate are point-in-time counts. A
count of 0 taken while the app can still accept requests proves nothing
about the moment the deploy actually flips `REDIS_HOST` — a checkout
arriving in between re-populates exactly the state the gate just
confirmed was empty. Reducing `queue` worker consumers doesn't help
either: it slows draining, but does nothing to stop `api` from still
accepting requests that dispatch new jobs or write new idempotency
results.

An earlier draft of this runbook used `php artisan down`/`up` to bracket
the cutover. **That's incompatible with the existing Jenkins deploy and
was corrected after review caught it:** Jenkins' Deploy stage polls
`https://blukios.store/api/health` — a normal API route, not the
framework's own `/up` health route — and requires an HTTP 200 within 3
minutes of recreating the containers, with no maintenance-mode bypass
secret configured anywhere in that curl. Laravel's maintenance mode
returns 503 for every route including `/api/health` unless a bypass
cookie from a `--secret` is presented — which nothing in the Jenkins
pipeline does. Keeping the app in maintenance mode across the deploy
would make Jenkins' own health check fail by design, and the runbook's
"exit maintenance mode after verification passes" step would never be
reached.

**`docker compose -p marketplace stop api` instead** — actually stopping
the container that's the only producer of both queue jobs and
idempotency writes (both are triggered from HTTP request handlers) is a
harder, more direct guarantee than a Laravel-level flag, and it happens
to align with an existing Jenkinsfile behavior: the Deploy stage's
pre-recreate migration step already checks whether `blue-api` is running
and defers to the new container's entrypoint when it isn't — so stopping
`api` ahead of merge doesn't fight that logic, it uses it. When Jenkins'
`docker compose up -d --no-build api queue ...` later starts the new
`api` container, it comes up fresh (not in any maintenance state) against
`shared-redis`, and `/api/health` can return 200 normally.

This does not stop the `scheduler` container's own `schedule:run` loop,
but the two scheduled commands (`transaction:check-expiry`,
`transaction:auto-complete`) run their logic directly against MySQL and
don't dispatch to the Redis queue, so they don't reopen either gate.

**This is a real, not-short outage.** Stopping `api` in step 11 makes the
site fully unavailable from that moment. Everything between there and
Jenkins actually reaching its Deploy stage — merging, `pollSCM` noticing
the merge (up to 5 minutes by design), the Backend/Frontend/Chat/
Recommendation test-and-build stages, then Deploy itself recreating
containers — all happens with `api` down. Do not describe or plan around
this as a "brief maintenance window"; size the actual cutover attempt
(merge timing, communication to anyone who needs to know) around a real
multi-stage-pipeline-length outage.

## Aborting before cutover completes

If step 12 or 13's gate fails (non-zero), or the operator decides not to
proceed for any other reason **before merging**, restore service with:

```sh
docker compose -p marketplace start api
```

`start`, not `up -d --force-recreate` or any other form that rebuilds or
recreates the container — the original `blue-api` container still exists
at this point (it was `stop`ped, not `rm`'d) and starting it back up
preserves its original captured environment (still pointed at
`blue-redis`) exactly as it was before step 11. This only works if
nothing after step 11 has removed that container.

If the abort happens **after** Jenkins has already reached its Deploy
stage and recreated `api` (i.e., cutover is already partially or fully
live against `shared-redis`), `docker compose start api` no longer
applies — that original container is gone. Follow "Rollback: what
`blue-redis` actually gives you" below instead, including its two-way
queue-stranding warning; do not treat this as the same recovery path.

## Redis state before cutover

Flipping `REDIS_HOST`/`REDIS_USERNAME`/`REDIS_PASSWORD` doesn't move any
existing data from `blue-redis` to `shared-redis` — every key currently in
`blue-redis` simply becomes unreachable to the app the moment the new
containers come up pointed elsewhere. That's fine for state that's
genuinely disposable, and not fine for state that isn't. Classify each
kind before cutover, don't assume:

| State | Where | Disposition |
|---|---|---|
| Laravel queue (`queues:default`, `:delayed`, `:reserved`) | `api`/`queue`, `default` Redis connection (db0) | **Must be drained to empty before cutover — see gate below.** Jobs left behind in `blue-redis` are silently abandoned; nothing re-delivers them once the app stops looking at that instance. Both producers of this queue are `api` request handlers, so stopping `api` (step 11) is what actually stops new jobs from appearing, not the count-check itself. |
| **Idempotency locks/results (`IdempotencyMiddleware`)** | `api`, `cache` Redis connection (**db1**, not db0) | **Must be inventoried before cutover — see gate below. Not disposable.** A completed transaction's result is cached for **24 hours** specifically so a client retry replays it instead of re-processing — that's the double-charge/double-stock-decrement protection the middleware's own docblock describes. Losing an active entry mid-window doesn't just lose a cache hit: a legitimate client retry with the same `X-Idempotency-Key` after cutover would see no matching lock at all on the new instance and get processed as a brand-new request. This is *not* equivalent to an ordinary deploy — an ordinary deploy doesn't swap Redis instances, `blue-redis` stays up and reachable across it. |
| chat-service session/summary/LLM cache | `chat-service` | Deliberately reset. TTL-bound already (`SESSION_TTL_SECONDS`=1h, LLM cache=5min) — losing it mid-conversation is a minor UX blip (history restarts), not a data-loss or correctness issue. No migration needed. |
| Laravel application cache (`cache` connection, db1) | `api` | Deliberately reset. Derived/rebuildable data (e.g. the product listing cache) — repopulates on next request/write. No migration needed. |
| Rate-limit counters | `api`, same cache store | Deliberately reset. Losing them just means every client's rate-limit window restarts at zero-used, which is *more* permissive briefly, not less — not a security regression. |

### Queue drain gate (mandatory, blocks merge if non-empty)

The repo currently has two `ShouldQueue` jobs (`ProcessProductImageJob`,
`GenerateAiChatReplyJob`) on the default Redis queue connection, both
dispatched from `api` request handlers. **With `api` stopped (step 11)**,
inventory pending, delayed, and reserved (in-flight) jobs via the
still-running `queue` container, using Laravel itself rather than
hand-rolling prefixed key names against `blue-redis`:

```sh
docker compose -p marketplace exec -T queue php artisan tinker --execute="
use Illuminate\Support\Facades\Redis;
\$c = Redis::connection('default');
echo 'pending:  ' . \$c->llen('queues:default') . PHP_EOL;
echo 'delayed:  ' . \$c->zcard('queues:default:delayed') . PHP_EOL;
echo 'reserved: ' . \$c->zcard('queues:default:reserved') . PHP_EOL;
"
```

(Laravel's Redis client applies the connection's configured prefix
automatically — this doesn't need to know or guess what `REDIS_PREFIX` is
currently set to on `blue-redis`. Run against `queue`, not `api` — `api`
is stopped at this point in the sequence.)

- **All three must read 0** before merging. **Verified in production
  2026-09-11 (pre-cutover, `api` still running at the time — informational
  only; re-run this with `api` actually stopped at real cutover time, not
  carried forward from this reading): `pending=0, delayed=0, reserved=0`.**
- With `api` stopped (step 11) this is now an actual guarantee, not a
  snapshot — nothing can enqueue a new job while the only producer is
  down, so a 0 reading here stays 0 for as long as `api` stays stopped.
  `queue`'s own worker keeps running and can still drain any backlog that
  existed before `api` was stopped.
- If a backlog won't drain (a stuck/failing job) even with `queue` still
  processing, that's a decision point, not something to script around
  here: either fix/discard the stuck job through normal `failed_jobs`
  handling, or explicitly decide + document an app-level replay (e.g.
  re-dispatching `ProcessProductImageJob` for the affected products after
  cutover) before proceeding. Do not merge with a known non-empty backlog
  on the assumption it'll "still be there." If it won't clear, abort per
  "Aborting before cutover completes" above rather than proceeding anyway.

### Idempotency & cache state gate (db1, mandatory)

With `api` still stopped, count active idempotency entries on the
`cache` connection's database — read-only, counts only, never prints a
key name or a cached response body (a cached idempotent result is the
actual response payload of a past transaction):

```sh
docker exec blue-redis sh -lc '
count=$(redis-cli -n 1 --scan --pattern "*idempotency:*" | wc -l)
echo "idempotency_keys_db1=$count"
'
```

- **Verified in production 2026-09-11: `idempotency_keys_db1=0`.** Same
  caveat as the queue gate — this is a snapshot from the review, not a
  standing guarantee; re-run it with `api` actually stopped, at real
  cutover time.
- **If the count is 0** (as it was here): no active replay-protection
  entries would be stranded — proceed.
- **If the count is `> 0`**: this is a hard stop, not a judgment call to
  wave through. A non-zero count means at least one client has a
  legitimate reason to retry with the same idempotency key sometime in
  the next 24 hours and expects that retry to replay, not reprocess.
  Note that with `api` stopped, this count can only *fall* from TTL
  expiry, not from retries "landing" anywhere — there's no api to land
  on. Since entries live up to 24h, waiting it out isn't a realistic
  option for a live cutover. In order of preference:
  1. If the count is genuinely small and the business accepts the
     specific narrow-window risk, proceed with it explicitly *documented*
     (which transactions, what the actual exposure is: a client retrying
     one of those specific transactions sometime in the remaining TTL
     window, post-cutover, gets reprocessed instead of replayed) — this
     is a deliberate, reviewed decision each time, not something to
     automate from this doc or wave through by default.
  2. Migrate the entries to `shared-redis` (`DUMP`/`RESTORE`-based copy,
     or a one-off script that reads db1 on `blue-redis` and writes the
     equivalent keys under `blukios:` on `shared-redis` with their
     remaining TTL preserved) if the count is non-trivial or the business
     wants zero risk here — real complexity, only worth building if this
     gate is actually failing, not speculatively ahead of time.

## Post-cutover namespace verification

The final ACL (see "Final ACL policy" above) denies `blukios` the
`SCAN`/`RANDOMKEY`/`PUBSUB` commands on purpose. **Do not weaken the ACL
to make a `SCAN`-as-`blukios` verification command work** — that command
was only ever a convenience for confirming the cutover worked, and the
whole point of denying it to this identity is that the application
itself should never be able to enumerate what else lives on shared
Redis.

**Application-identity verification (step 18)** — use real application
behavior instead of introspection:
- A known-key smoke test: `SET`/`GET` one specific `blukios:`-prefixed
  key as `blukios` directly (you already know its exact name, so no
  `SCAN` is needed to find it).
- Actual behavior: hit `/api/health`, log in and confirm a cache-backed
  read works, submit a chat message and confirm `chat-service`'s session
  round-trips, watch `queue` process a real job.

**Namespace inventory (if an operator wants one) — administrator
identity only, never `blukios`:**

```sh
read -rs REDISCLI_AUTH   # the shared-infra *administrator* credential, not blukios
export REDISCLI_AUTH
docker exec -e REDISCLI_AUTH shared-redis \
  redis-cli --scan --pattern 'blukios:*' | wc -l
unset REDISCLI_AUTH
```

Same shape for confirming the existing `asynq:*` workload is untouched
(step 19) — administrator credential, count only, same
before/after-sampling approach as the original inventory. `blukios` is
never expected to perform this comparison; it structurally can't, and
that's correct.

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

## What this PR's code does and doesn't do

This PR's *code* only prepares `marketplace` to *consume* a shared Redis
identity — it contains no infrastructure-provisioning automation and
embeds no production credential. That remains true regardless of what's
happened operationally outside the repo (see "Status" at the top). This
PR's code does not, and never will:

- touch `/opt/shared-infra` or its compose file
- create, modify, or store any real Redis ACL user or credential
- change production `.env`
- deploy, or merge itself

Separately, and *not* part of this PR's diff: as of 2026-09-11,
`shared-redis` **has** been manually provisioned with the persistent
`blukios` ACL identity described above, as an operational change to
`/opt/shared-infra` reviewed and verified independently of this PR. The
existing shared `default` credential was preserved, not rotated.
Marketplace production `.env` has **not** been switched over yet, and
this PR (#20) is still unmerged — no deploy driven by it has happened.

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
