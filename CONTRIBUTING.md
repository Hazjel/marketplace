# Contributing

## Workflow

```
branch off main  →  commit  →  push  →  open PR  →  Jenkins  →  squash-merge  →  auto-deploy
```

1. **Branch** off the latest `main`. Naming: `feat/…`, `fix/…`, `ci/…`,
   `docs/…`, `chore/…`, `security/…`.
2. **Keep it scoped.** One concern per PR. If a change touches many services,
   say why in the description.
3. **Run the checks locally** before pushing (see below).
4. **Open a PR** against `main`. Fill in the template.
5. **Jenkins** runs on every push. It must be green before merge.
6. **Squash-merge.** `main` is the deploy branch — every merge triggers a
   production deploy via the `Deploy` stage.

There is no long-lived `develop` branch. `main` is always deployable.

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
| IDs | UUID v4 on all tables. No soft deletes except `users`. |
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
  globals/environments are gitignored; gitleaks runs in CI.
- Report vulnerabilities privately — see [`SECURITY.md`](SECURITY.md).
