# Blukios Marketplace

Multi-vendor e-commerce platform for gadgets and electronics, focused on the
Indonesian market (Bahasa Indonesia UI, Rupiah, local payment and shipping).
Production: **[blukios.store](https://blukios.store)** (buyer) ·
**[seller.blukios.store](https://seller.blukios.store)** (seller + admin).

This repository is **source-available**. No open-source license is granted —
see [License](#license).

---

## Contents

- [Overview](#overview)
- [Features](#features)
- [Repository structure](#repository-structure)
- [Architecture](#architecture)
- [Tech stack](#tech-stack)
- [Local development](#local-development)
- [Environment variables](#environment-variables)
- [Testing](#testing)
- [CI/CD (Jenkins)](#cicd-jenkins)
- [Production deployment](#production-deployment)
- [API & Postman](#api--postman)
- [Monitoring](#monitoring)
- [Security](#security)
- [Mobile app](#mobile-app)
- [Project status & roadmap](#project-status--roadmap)
- [Contributing](#contributing)
- [License](#license)

---

## Overview

One codebase, four backend services, two frontend builds:

| Service | Role |
|---|---|
| `api-blue` | Laravel 12 REST API — auth, catalog, cart, checkout, escrow payments, chat, vouchers |
| `fe-blue` | Vue 3 SPA — built twice: **buyer app** (`blukios.store`) and **seller app** (`seller.blukios.store`) from `VITE_APP_TARGET` |
| `chat-service` | FastAPI assistant "Ri" — Ollama LLM + RAG (Chroma) over the product catalog |
| `recommendation-service` | FastAPI — content-based similarity + collaborative filtering (SVD) |

Supporting containers: Laravel Reverb (WebSocket), a database queue worker, a
scheduler, nginx (single entry point), MySQL, MongoDB, Redis, Ollama.

## Features

- Multi-vendor catalog with per-product MongoDB variants (price/stock per variant)
- Server-side cart with localStorage sync on login
- Checkout with Midtrans payment, Komerce shipping rates, voucher redemption
- Escrow: seller funds held as `pending_balance`, released on order completion
- Real-time buyer↔seller chat over Reverb private channels
- AI assistant "Ri" with catalog-grounded answers (RAG)
- Personalised recommendations (content-based + collaborative)
- Seller dashboard, wallet, withdrawals; platform admin backoffice
- Google OAuth, Firebase push notifications
- Full-text search on products and stores
- Leaflet/OpenStreetMap address & store location picker

## Repository structure

```
.
├── api-blue/                 Laravel 12 REST API (PHP 8.2+)
├── fe-blue/                  Vue 3 SPA (Vite 7, Tailwind v4, Pinia)
├── chat-service/             FastAPI "Ri" — Ollama + RAG (Chroma)
├── recommendation-service/   FastAPI — content-based + collaborative (SVD)
├── docker/nginx/             nginx site config (two server blocks)
├── monitoring/               Prometheus config, Grafana dashboards, k6 scripts
├── postman/                  Postman collection
├── docker-compose.yml        all services + infra + tools
└── Jenkinsfile               CI/CD pipeline
```

The mobile app lives in a **separate repository** — see [Mobile app](#mobile-app).

## Architecture

### Two-domain frontend (buyer vs seller)

`fe-blue` builds into two apps from one codebase, à la Shopee App vs Seller Centre:

- **`blukios.store`** — buyer app: public marketplace, cart, checkout, personal
  dashboard. Build with `VITE_APP_TARGET=buyer` (default).
- **`seller.blukios.store`** — seller app: store dashboard, product management,
  incoming orders, wallet, buyer chat, plus the platform admin backoffice
  (`/admin/*`). No personal-buyer features. Build with `VITE_APP_TARGET=seller`.

`src/router/index.js` composes routes from three modules — `auth` (shared),
`buyer`, `seller` — and each build renders only two of the three; undeclared
routes 404 and Vite code-splitting keeps unused chunks out of the bundle.

**Cross-domain SSO** — the two origins don't share cookies (auth is pure Sanctum
bearer token), so moving between apps uses a one-time token exchange:
`POST /api/auth/sso/initiate` (needs an active session) → `exchange_token`
(30s TTL, single-use) → full redirect to `{target}/sso/callback?xt=…` →
`POST /api/auth/sso/exchange` → fresh Sanctum token for the target domain.

### Request routing (nginx)

A single nginx container is the only published port. Both server blocks proxy to
the same Laravel API:

| Path | Upstream |
|---|---|
| `/` | SPA static files (buyer root or seller root) |
| `/api`, `/sanctum` | PHP-FPM (`api` container) |
| `/app` | Reverb WebSocket (`reverb:8080`) |
| `/ai` | `chat-service:8001` |
| `/recommend` | `recommendation-service:8002` |
| `/storage` | Laravel public storage |
| `/metrics` | Laravel Prometheus exporter |

### Backend patterns (`api-blue`)

- **Repository pattern** — business logic in `app/Repositories/*`, bound to
  interfaces in `RepositoryServiceProvider`; controllers get repositories by
  constructor injection.
- **Standardised responses** — `ResponseHelper::jsonResponse()`:
  `{ success, message, data, statusCode }`. Every model has an API Resource;
  paginated lists wrap in `PaginateResource` with a `meta` block.
- **Form Requests** — validation in `{Resource}{Action}Request` classes;
  messages in Bahasa Indonesia.
- **Idempotency** — `X-Idempotency-Key` header required on transaction creation;
  cached in Redis for 24h.
- **UUID v4** primary keys on every table; no soft deletes except `users`.
- **Money** — `App\ValueObjects\Money` fixed-point value object (whole rupiah,
  scale 0). Pilot column: `transaction_details.subtotal`. Full calculation
  migration is in progress — see [roadmap](#project-status--roadmap).

## Tech stack

| Layer | Stack |
|---|---|
| API | Laravel 12, PHP 8.2+, Sanctum, Spatie Permission, Reverb, Socialite |
| API data | MySQL 8 (primary), MongoDB 7 (product variants via `mongodb/laravel-mongodb`), Redis (idempotency, cache) |
| Payments / logistics | Midtrans, Komerce (shipping tariffs & tracking) |
| Frontend | Vue 3.5 `<script setup>`, Vite 7, Vue Router 4, Pinia 3, Tailwind CSS v4, CVA, Radix Vue, Axios, Laravel Echo + Pusher JS, Lucide |
| Chat service | FastAPI, Python 3.11, Ollama (`qwen3:1.7b`), ChromaDB (RAG), slowapi, httpx |
| Recommendation service | FastAPI, NumPy/Pandas/scikit-learn, scikit-surprise (SVD), APScheduler |
| Real-time | Laravel Reverb (WebSocket broadcasting) |
| Maps | Leaflet + OpenStreetMap |
| Observability | Prometheus, Grafana, k6 |
| CI/CD | Jenkins (declarative pipeline) |
| Code style | Laravel Pint (PSR-12), PHPStan/Larastan level 5, ESLint 9 + Prettier, Ruff |

## Local development

### Prerequisites

- Docker Desktop (Compose v2)
- Node.js `^20.19 || >=22.12`, npm — for the Vue dev server on the host
- PHP 8.2+ and Composer — only if running artisan commands outside the container
- Python 3.11 — only if running the Python services outside Docker

### 1. Bring up the stack

```bash
docker compose up -d --build
docker compose exec -T ollama ollama pull qwen3:1.7b   # first run only
```

This starts nginx, the API (PHP-FPM), queue, scheduler, Reverb, both frontends,
chat-service, recommendation-service, MySQL, MongoDB, Redis and Ollama.

> **Note:** the default `docker-compose.yml` is tuned for local development —
> MySQL runs with an empty root password, Redis and MongoDB have no auth, and
> phpMyAdmin / mongo-express are exposed without credentials. Do **not** deploy
> it unchanged. See [Security](#security).

### 2. Run migrations & seed

```bash
docker exec blue-api php artisan migrate --seed
```

`ProductionSeeder` creates roles/permissions only (no demo accounts). Use
`db:seed --class=...` for catalog fixtures in development.

### 3. Frontend dev server (hot reload)

```bash
cd fe-blue
npm install
npm run dev            # buyer app  → http://localhost:5173
npm run dev:seller     # seller app → http://localhost:5174
```

Production frontend is built into the image and served by nginx; the dev server
is host-only.

### Services & URLs (default compose)

| Service | URL / host port |
|---|---|
| Web + API (nginx) | `http://localhost` (`${NGINX_HOST_PORT:-80}`) |
| Vue dev server (buyer / seller) | `http://localhost:5173` / `:5174` |
| MySQL | `localhost:${DB_HOST_PORT:-3306}` |
| MongoDB | `localhost:27018` |
| Redis | `localhost:6379` |
| Ollama | `http://localhost:11435` |
| phpMyAdmin | `http://localhost:8080` |
| mongo-express | `http://localhost:8081` |
| Prometheus | `http://localhost:9090` — profile `monitoring` |
| Grafana | `http://localhost:3000` — profile `monitoring` |
| Jenkins | `http://localhost:8082` — profile `cd` |
| k6 load test | profile `loadtest` |

Profile-gated services: `docker compose --profile monitoring up -d`,
`--profile loadtest run --rm k6`, `--profile cd up -d jenkins`.

### Common commands

```bash
docker exec blue-api php artisan migrate         # migrations
docker exec blue-api php artisan tinker          # REPL
docker exec blue-api php artisan geo:backfill    # backfill store/address coords
docker exec blue-api vendor/bin/pint             # PHP formatting
docker exec blue-api vendor/bin/phpstan analyse  # static analysis
cd fe-blue && npm run lint && npm run test       # frontend checks
```

## Environment variables

Each service has its own `.env.example`:

- `api-blue/.env.example` — Laravel: DB (MySQL + MongoDB), Redis, Sanctum,
  Midtrans keys, Google OAuth, Reverb, Komerce/RajaOngkir keys,
  `INTERNAL_SERVICE_KEY` (shared secret for `/internal/*`), `FIREBASE_CREDENTIALS`.
- `chat-service/.env.example` — `OLLAMA_BASE_URL`, `OLLAMA_MODEL`,
  `LARAVEL_API_URL`, `CORS_ALLOWED_ORIGINS`, `RATE_LIMIT_PER_MINUTE`, `REDIS_URL`.
- `recommendation-service/.env.example` — `LARAVEL_API_URL`,
  `INTERNAL_SERVICE_KEY` (must match `api-blue`), CF/CBF tuning.

The Compose file supplies inter-container values (internal hostnames, mandatory
DB/Redis credentials for a hardened deployment) via its own environment / an
`.env` at the repo root — the per-service `.env.example` files above are aimed at
running a service directly on the host.

## Testing

| Suite | Command | Notes |
|---|---|---|
| Backend | `docker exec blue-api php artisan test` | PHPUnit; SQLite in-memory + a local MongoDB in CI |
| Backend lint | `vendor/bin/pint --test` | PSR-12 |
| Backend static | `vendor/bin/phpstan analyse` | Larastan level 5 + baseline |
| Frontend | `cd fe-blue && npm run test` | Vitest + Vue Test Utils |
| Frontend lint | `npm run lint` | ESLint 9 |
| Frontend build | `npm run build` | Vite production build |
| Python services | `pytest` in each service dir | + `ruff check` |
| Load test | `docker compose --profile loadtest run --rm k6` | health + chat scenarios |

## CI/CD (Jenkins)

`Jenkinsfile` — declarative pipeline, triggered on SCM change. Stages:

1. **Detect Changes** — path filter; each service's stage runs only if its files changed
2. **Backend: Install** — `composer install`, `composer audit`
3. **Backend: Lint & Test** — Pint, PHPStan, `artisan test` (SQLite + ephemeral MongoDB)
4. **Frontend: Install & Test** — `npm ci`, ESLint, Vitest, `npm run build`, `npm audit --omit=dev --audit-level=high`
5. **Chat Service: Lint, Audit & Test** — Ruff, pip-audit, pytest
6. **Recommendation Service: Lint, Audit & Test** — Ruff, pip-audit, pytest
7. **Security: Secret Scan** — gitleaks (`.gitleaks.toml`)
8. **Deploy** — on `main` only: fetch the tested commit, `artisan migrate --force`,
   rebuild & recreate the app containers, verify the deployed SHA, health-check
   `https://blukios.store/api/health`

GitHub Actions is **not** used — the pipeline lives entirely in `Jenkinsfile`.

## Production deployment

- Jenkins runs on the production host (`--profile cd`) and deploys in place:
  `docker compose -p marketplace build/up -d api queue reverb scheduler frontend
  chat-service recommendation-service` + `--force-recreate nginx`.
- Only the application containers are rebuilt per deploy; MySQL / MongoDB / Redis /
  Ollama are long-lived.
- A failed migration aborts the deploy before any container is touched.
- Post-deploy: the deployed working-tree SHA is checked against the tested commit,
  then the API must return `200` from `/api/health` within 3 minutes or the stage
  fails.

## API & Postman

- REST, `Authorization: Bearer {sanctum_token}`.
- Standard envelope: `{ success, message, data, statusCode }`.
- Pagination: `?row_per_page=10&page=1`. Search: `?search=keyword` (FULLTEXT).
- Product filters: `min_price`, `max_price`, `min_rating`, `condition`,
  `product_category_id`, `store_id`, `sort_by`, `sort_direction`.
- Rate limits: register/login `6/min`, transactions `10/min`, password reset `5/min`.
- Collection: `postman/collections/Blue Marketplace API (Complete).json`.

## Monitoring

- Prometheus scrapes the chat service and the Laravel `/metrics` exporter
  (15s interval, 15-day retention).
- Grafana auto-provisions dashboards (`monitoring/grafana/`) and alerting rules.
- Both run under `--profile monitoring`; Grafana requires
  `GRAFANA_ADMIN_PASSWORD`.

## Security

- `X-Idempotency-Key` enforced on transaction creation.
- Checkout is server-authoritative: buyer id, store id, shipping cost and voucher
  discount are all re-derived/re-validated server-side; client values are ignored.
- Escrow ledger has DB-level uniqueness so a webhook replay can't double-credit.
- gitleaks runs in CI; secrets live only in `.env` (gitignored) and
  `FIREBASE_CREDENTIALS` JSON (gitignored).
- Midtrans keys in `.env` are sandbox by default (`MIDTRANS_IS_PRODUCTION=false`).

**Deployment hardening is not automatic.** The committed `docker-compose.yml` is a
local-development configuration: empty MySQL root password, no Redis/MongoDB auth,
phpMyAdmin and mongo-express exposed without credentials, and infra ports
published to the host. A production host must override these (mandatory
credentials, no published infra ports, tools behind a profile + auth, host
firewall) before running the stack.

To report a vulnerability, see [`SECURITY.md`](SECURITY.md) — do not open a public
issue.

## Mobile app

The Flutter mobile app is maintained in a **separate repository**
(`Hazjel/marketplace-mobile`). It is not part of this repo (`flutter-app/` is
gitignored). It consumes the same `api-blue` REST API.

## Project status & roadmap

Actively developed. Production is live and CI-gated.

- **Done** — two-domain buyer/seller split, escrow payments, server-authoritative
  checkout, variant-aware pricing/stock, cross-DB (MySQL↔MongoDB) compensation,
  RAG chat, collaborative recommendations, Jenkins pipeline, production hardening
  of the CVE surface.
- **In progress — money refactor (Sprint B)** — `Money` fixed-point primitive
  landed (`B3.1`, pilot on `transaction_details.subtotal`). Next: `B3.2`
  migrate tax / voucher / admin-fee calculations onto the primitive and close
  the known rounding gaps (`api-blue/docs/money-contract.md` §6).
- **Backlog** — end-to-end Midtrans Snap payment verification; `products.price`
  integer validation; `decimal` → `bigint` column migration (deferred).

## Contributing

See [`CONTRIBUTING.md`](CONTRIBUTING.md). In short: branch off `main`, keep
changes scoped, run Pint / PHPStan / tests locally, open a PR, let Jenkins gate
it, squash-merge. `main` auto-deploys.

## License

**No license.** This repository is public and source-available, but no rights to
use, copy, modify, or distribute the code are granted. All rights reserved by the
authors. If you need a license, open an issue to ask.
