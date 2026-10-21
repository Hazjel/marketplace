# Isolated Blukios browser tests

Requirements: local Docker with Compose v2+ and Bash (Git Bash on Windows). No host PHP, Node, database or real `.env` is used.

From the repository root:

```sh
bash e2e/run.sh
# Keep the stack alive for manual/agentic QA:
bash e2e/run.sh up
bash e2e/run.sh test tests/smoke.spec.ts
bash e2e/run.sh down
```

Without arguments the runner builds images, starts PostgreSQL 17, MongoDB 8, Redis 7, the API and both FE builds, waits for health, runs Chromium, then tears down its Compose project and volumes on success, failure, Ctrl-C or TERM. Its exit status is the test status (or a startup/cleanup failure). `up` seeds and leaves a healthy stack running; a failed startup still cleans up. `test` rebuilds the test image to include new specs, runs Playwright on that stack and preserves it even if a test fails. `down` removes the project containers, networks and volumes. The API startup migrates and seeds a fresh disposable database before becoming healthy. Build caches/images are retained for the next run.

All containers, named volumes and networks belong to **blukios-e2e**, with no external/shared resources. Buyer is `http://127.0.0.1:18080`, seller/admin is `http://127.0.0.1:18081`, and API health is `http://127.0.0.1:18080/api/health` (API routes use the `/api` prefix). These ports bind only to loopback; free them before startup if occupied. The runner refuses overlapping startups. It never uses the repository's dev/production Compose files, Jenkinsfile, nginx configuration or `.env`; `compose.env` prevents implicit `.env` loading. The owner dev stack is neither started nor stopped. AI/chat/recommendation services are omitted. Runtime limits total about 2.5 GiB including Chromium; image builds can temporarily use more memory.

API, databases and browser use an internal network without an internet route. Only the two nginx frontends also join a dedicated edge network, so Docker can publish their loopback ports for host QA; nginx serves static files and proxies the local API. The browser fixture blocks other origins and all WebSockets (Reverb is not started), and nginx restricts external content with CSP. Dependency downloads happen during image builds, before tests. Midtrans and Google OAuth are fake **only with `APP_ENV=e2e`**; production keeps its original bindings, covered by `E2EGatewayIsolationTest`. OAuth returns the seeded buyer. Snap tokens, refunds and manual status polling are synthetic; no real payment is possible and the real Snap SDK is blocked. These tests do not validate third-party service compatibility.

Synthetic accounts, all using password `password`:

| Role | Email | Username |
| --- | --- | --- |
| Buyer | buyer@e2e.test | e2e-buyer |
| Buyer with zero Saldo | buyer-two@e2e.test | e2e-buyer-two |
| Seller | seller@e2e.test | e2e-seller |
| Second seller | seller-two@e2e.test | e2e-seller-two |
| Admin | admin@e2e.test | e2e-admin |

The first buyer has Saldo Rp 100,000 with a synthetic opening ledger. Stores `e2e-store` and `e2e-store-two` own `E2E Notebook` (`/product/e2e-notebook`, Rp 25,000) and `E2E Pen` (`/product/e2e-pen`, Rp 15,000), each with available stock 20. Each buyer has one unpaid and one paid order (`E2E-B1-UNPAID`, `E2E-B1-PAID`, `E2E-B2-UNPAID`, `E2E-B2-PAID`), with details and escrow ledger for paid orders. The first store has active fixed-discount voucher `E2EHEMAT` (Rp 5,000 off, minimum Rp 10,000). `E2ESeeder` refuses to run outside E2E. Each default run gets a fresh database; specs within a running stack share its data, so use separate accounts/products for mutating scenarios or restart with `down` then `up`.

Add specs under `e2e/tests/`, importing `test` and `expect` from `./fixtures` to retain the network guard. Use visible roles/labels and retrying assertions instead of sleeps. Container Playwright uses `http://web`; host QA uses the loopback URLs above. Cross-build SSO URLs target the host ports, so container smoke stays within the buyer app. Playwright's npm package/lock and Docker image are pinned to **1.64.1**; update both together.

`bash e2e/tests/runner.spec.sh` checks runner exit codes and cleanup behavior with a fake Docker CLI, without starting containers.

On failure, screenshots, retained traces and the HTML report remain under gitignored `e2e/artifacts/`, along with stack logs. Playwright writes inside the container first, then the wrapper copies failures out; direct trace writes to Windows bind mounts can stall browser teardown. Successful runs remove their browser artifacts. For a report, run `npx playwright show-report e2e/artifacts/report` with the pinned version installed locally, or archive that directory in a future Jenkins job.

If Docker is killed or unavailable during cleanup, rerun the project-scoped command once Docker is back:

```sh
docker compose -p blukios-e2e --project-directory . --env-file e2e/compose.env -f e2e/docker-compose.e2e.yml down -v --remove-orphans
```

No deployment or production calls are part of this harness.
