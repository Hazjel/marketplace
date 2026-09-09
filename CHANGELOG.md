# Changelog

All notable changes to this project are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
This project does not yet follow semantic versioning strictly — while the money
calculation layer (Sprint B) is in flux, minor versions may carry behavioural
changes. The first tagged release will be `v0.1.0`.

## [Unreleased]

### Added
- `App\ValueObjects\Money` fixed-point value object (whole rupiah, scale 0) and
  `App\Enums\RoundingMode`; `App\Casts\MoneyCast` bridging `decimal(26,2)`
  columns. Pilot: `transaction_details.subtotal`. (B3.1, #15)
- `api-blue/docs/money-contract.md` — frozen money contract and the list of
  known calculation gaps to migrate in B3.2.
- Repository documentation: `README.md` rewrite, `CONTRIBUTING.md`,
  `SECURITY.md`, this changelog, GitHub PR/issue templates.

### Changed
- `TransactionDetailResource` emits `subtotal` as an integer (was a float);
  same value, narrower type.
- Frontend `maplibre-gl` pinned to `^6.9.0` via npm `overrides` to clear a
  critical XSS advisory pulled transitively through `@unovis`. (#14)
- MongoDB PHP extension bumped to `2.4.1` (CVE fix). (#13)

### CI
- Migrated the pipeline to Jenkins (`Jenkinsfile`); GitHub Actions removed.
- Backend test agent: bookworm Node image, internal DNS, IPv4 MongoDB download,
  npm integrity/network-retry hardening. (#10–#12)

### Housekeeping
- Deleted 19 merged branches; archived `feat/hp-layout-restructure` as tag
  `archive/hp-layout-restructure` (audited: 0 unique commits).

## Released

_No tagged releases yet._
