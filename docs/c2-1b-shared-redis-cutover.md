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
   to `shared-infra-net` (e.g. `docker run --rm --network shared-infra-net
   redis:7-alpine redis-cli -h shared-redis -a '<password>' --user blukios
   ...`) — never from a marketplace container, and never from this repo's
   scripts. This PR does not and must not provision ACL state itself.
9. **Verify `blukios` can read/write `blukios:*`.**
10. **Verify `blukios` cannot read/write the existing `asynq:*` keys** (or
    anything outside `blukios:*`) — this is the actual proof the
    isolation works, not just that the happy path works.
11. **Set marketplace's production `REDIS_HOST`, `REDIS_PORT`,
    `REDIS_USERNAME`, `REDIS_PASSWORD`, `REDIS_PREFIX` in the production
    `.env`** (gitignored, never committed).
12. **Only after 1–11 pass**, merge this PR.
13. Jenkins picks it up (`pollSCM`) and deploys — this rebuilds
    `api`/`queue`/`reverb`/`scheduler`/`chat-service` and attaches them to
    `shared-infra-net`.
14. **Verify each service actually reconnected**: `api` health check,
    `queue` processing a job, `scheduler`'s next tick, `reverb`
    broadcasting, `chat-service` session/summary/cache round-tripping.
15. **Verify new keys are `blukios:*`** — `redis-cli --user blukios ...
    KEYS 'blukios:*'` (or `SCAN`, for a shared instance — avoid `KEYS` on
    a shared production instance with an unknown key count; use `SCAN`
    with a `MATCH` pattern instead).
16. **Verify the existing `asynq:*` workload is untouched** — same
    `SCAN`/sampling approach as the original inventory, comparing before
    and after.
17. **Retain the `blue-redis` container/volume as a rollback path** until
    production verification (14–16) passes. See the note below on why
    this deploy won't remove it automatically anyway.
18. **Only then** retire `marketplace`-local Redis (stop/remove
    `blue-redis`, eventually reclaim its volume) — a separate, later
    change, not part of this PR or this deploy.
19. **Shared `default` credential rotation is a separate, later,
    coordinated operation** — out of scope here entirely; this cutover
    must not require or trigger it.

## `blue-redis` is not automatically removed by this deploy

The Jenkins `Deploy` stage's `docker compose -p marketplace up -d --no-build
...` and `--force-recreate nginx` do **not** pass `--remove-orphans`. Since
`blue-redis` is no longer declared in `docker-compose.yml` after this PR,
Compose treats it as an orphaned container it no longer manages — it does
**not** stop or remove it. It keeps running, untouched, alongside the new
`shared-redis`-backed containers.

This is convenient as an implicit rollback fallback (the old container and
its volume are still there if the cutover needs to be reverted), but it
also means **retiring it is a deliberate, separate step (§18 above)**, not
something that happens for free. If a future change adds
`--remove-orphans` to the Deploy stage, this sequencing assumption breaks —
recheck this section before doing so.

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
