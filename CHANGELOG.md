# Changelog

All notable changes to this project are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
This project does not yet follow semantic versioning strictly — while the money
calculation layer (Sprint B) is in flux, minor versions may carry behavioural
changes.

## [Unreleased]

### Changed
- **Money calculation migration (Sprint B3.2).** Checkout tax, voucher
  discount and the platform admin fee are now computed on
  `App\ValueObjects\Money` with basis-point rates and HALF_UP rounding
  only at the percentage step:
  - tax = `subtotal->percentage(1100)` (11% PPN), shipping untaxed;
  - `Voucher::validateFor()` takes and returns `Money`; percentage rate
    parsed to exact basis points from the `decimal:2` string; fixed value
    capped at the subtotal;
  - admin fee = `netSales->percentage(admin_fee_basis_points)`, locked at
    credit; release/refund reuse the locked value.
- `products.price` and `variants.*.price` must be whole rupiah (`integer`
  validation on create and update). Voucher `min_purchase` / `max_discount`
  likewise; a fixed voucher `value` must parse as whole-rupiah Money, a
  percentage `value` is capped at 2 decimal places.
- Frontend `cart.js` totals mirror the backend boundary — PPN rounded at
  the tax step, then exact — so the Cart and Checkout totals agree.
- Config: `marketplace.admin_fee_percentage` (float `0.10`) →
  `marketplace.admin_fee_basis_points` (int `1000`), env
  `ADMIN_FEE_BASIS_POINTS`.

### Removed
- `App\Services\TransactionService` — dead code, and the second divergent
  seller-amount implementation.

## [0.1.0] - 2026-09-10

First tagged release. Snapshot of a CI-gated, production-deployed `main` after
the B3.1 money-primitive work and the repository documentation pass.

### Added
- `App\ValueObjects\Money` fixed-point value object (whole rupiah, scale 0) and
  `App\Enums\RoundingMode`; `App\Casts\MoneyCast` bridging `decimal(26,2)`
  columns. Pilot: `transaction_details.subtotal`. (B3.1, #15)
- `api-blue/docs/money-contract.md` — frozen money contract and the list of
  known calculation gaps to migrate in B3.2.
- Repository documentation: `README.md` rewrite, `CONTRIBUTING.md`,
  `SECURITY.md`, this changelog, GitHub PR/issue templates, and a root
  `.env.example` for the Compose stack. (#16)

### Changed
- `TransactionDetailResource` emits `subtotal` as an integer (was a float);
  same value, narrower type. (#15)
- Frontend `maplibre-gl` pinned to `^6.9.0` via npm `overrides` to clear a
  critical XSS advisory pulled transitively through `@unovis`. (#14)
- MongoDB PHP extension bumped to `2.4.1` (CVE fix). (#13)

### CI
- Migrated the pipeline to Jenkins (`Jenkinsfile`); GitHub Actions removed.
  `pollSCM` every 5 minutes on `main`, in-place deploy with SHA verification and
  a production health check.
- Backend test agent: bookworm Node image, internal DNS, IPv4 MongoDB download,
  npm integrity / network-retry hardening. (#10–#12)

### Housekeeping
- Cleared 21 merged branches; archived `feat/hp-layout-restructure` as tag
  `archive/hp-layout-restructure` (audited: 0 unique commits).

### Known limitations
- Money calculation migration (B3.2) not done — tax, voucher and admin fee are
  not yet on the `Money` primitive (`api-blue/docs/money-contract.md` §6).
- `docker-compose.yml` is a development configuration and must be hardened for
  any deployment (`SECURITY.md`).
- The Jenkins `Security: Secret Scan` (gitleaks) stage is non-blocking and does
  not yet exclude `vendor/`.
- End-to-end Midtrans Snap payment flow is not covered by an automated test.

[Unreleased]: https://github.com/Hazjel/marketplace/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/Hazjel/marketplace/releases/tag/v0.1.0
