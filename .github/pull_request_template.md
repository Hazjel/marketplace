<!-- Keep the PR scoped to one concern. -->

## What & why

<!-- What does this change and why. Link the sprint/task id or issue. -->

## Scope

- Services touched:
- Breaking API/response changes: <!-- none / describe + characterization test -->
- DB migrations: <!-- none / describe -->

## Checks

- [ ] `vendor/bin/pint --test` clean
- [ ] `vendor/bin/phpstan analyse` clean (no new baseline entries, or explained)
- [ ] `php artisan test` passing
- [ ] Frontend (if touched): `npm run lint`, `npm run test`, `npm run build`
- [ ] Python service (if touched): `ruff check`, `pytest`
- [ ] New behaviour has a test; bug fix has a regression test
- [ ] No secrets committed

## Deploy notes

<!-- `main` auto-deploys on merge. Anything ops needs to know:
     new env var, one-off command, ordering constraint, feature flag. -->

## Security

<!-- Auth/authorization, input trust boundaries, money math, rate limits.
     "n/a" if genuinely none. -->
