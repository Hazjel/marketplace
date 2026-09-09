# Contributing

## Workflow

```
branch off main  →  local checks  →  push  →  open PR  →  review  →  merge commit
      →  Jenkins polls main  →  build + test + deploy  →  verify production
```

1. **Branch** off the latest `main`. Naming: `feat/…`, `fix/…`, `ci/…`,
   `docs/…`, `chore/…`, `security/…`.
2. **Keep it scoped.** One concern per PR. If a change touches many services,
   say why in the description.
3. **Run the checks locally** before pushing (see below) — this is the real
   pre-merge gate.
4. **Open a PR** against `main`. Fill in the template. Get a review.
5. **Merge** (a merge commit, not squash — matches the existing history).
6. **Jenkins** picks up `main` on its next SCM poll (every ~5 min; there is no
   webhook), runs the full pipeline, and — if green — deploys the merge commit
   and health-checks production. Watch that run; a red pipeline on `main` means
   production may be affected.

Jenkins does **not** run per-PR. Nothing automated gates the PR itself, so the
local checks and the review are what protect `main`. There is no long-lived
`develop` branch — `main` is always deployable, and every merge deploys
(docs-only changes included).

## Local checks

### Backend (`api-blue`)

```bash
docker exec blue-api vendor/bin/pint --test       # PSR-12 formatting
docker exec blue-api vendor/bin/phpstan analyse   # Larastan level 5
docker exec blue-api php artisan test             # PHPUnit
```

Run `vendor/bin/pint` (no `--test`) to autofix.

### Frontend (`fe-blue`)

```bash
cd fe-blue
npm run lint        # ESLint 9 (also autofixes)
npm run test        # Vitest
npm run build       # Vite production build must succeed
```

### Python services

```bash
cd chat-service            # or recommendation-service
ruff check .
pytest
```

## Commit messages

Conventional-commit style:

```
type(scope): short summary in the imperative

Body: what changed and why. Reference the sprint/task id if there is one.
```

`type` ∈ `feat` `fix` `refactor` `chore` `ci` `docs` `test` `perf` `security`.
`scope` is usually the service (`api`, `fe`, `chat`, `reco`) or an area.

## Code style

| Area | Rule |
|---|---|
| PHP | Laravel Pint (PSR-12). `vendor/bin/pint` before commit. |
| PHP static | PHPStan/Larastan level 5 — no new baseline entries without a reason. |
| Vue / JS | Prettier: no semicolons, single quotes, 2-space indent, 100-col, no trailing commas. |
| Python | Ruff (`ruff.toml` per service). |
| Naming | See `CLAUDE.md` (if present) or match the surrounding code. |
| Language | UI text, validation and error messages are in **Bahasa Indonesia**. |
| IDs | Core domain entities predominantly use UUID v4; some supporting tables (`jobs`, `addresses`, `store_followers`) use integer IDs. No soft deletes except `users`. |
| Money | Never do ad-hoc float arithmetic on currency. Use `App\ValueObjects\Money`; rounding only at percentage boundaries. See `api-blue/docs/money-contract.md`. |

## Tests

- New behaviour needs a test. Bug fixes need a regression test.
- Backend: Feature tests for HTTP/behaviour, Unit tests for pure logic.
- A characterization test is expected when a change alters an existing API
  response shape or type.

## Database changes

- Migrations only — never edit a shipped migration.
- The `decimal` money columns stay `decimal(26,2)` for now; a `bigint`
  migration is a separate, deferred decision.

## Security

- Never commit secrets. `.env`, credential JSON, and Postman
  globals/environments are gitignored; gitleaks runs in Jenkins on `main` and
  is currently non-blocking.
- Report vulnerabilities privately — see [`SECURITY.md`](SECURITY.md).
