# Security Policy

## Reporting a vulnerability

**Do not open a public issue or pull request for a security problem.**

Report privately through **GitHub's private vulnerability reporting**:
Security tab → *Report a vulnerability*
(<https://github.com/Hazjel/marketplace/security/advisories/new>).

If that is unavailable, contact the repository owner directly through their
GitHub profile and wait for a private channel before sharing details.

Please include:

- affected service (`api-blue`, `fe-blue`, `chat-service`,
  `recommendation-service`, infra) and version/commit,
- a description of the issue and its impact,
- reproduction steps or a proof of concept,
- any known mitigation.

You can expect an acknowledgement within a few days. Please give a reasonable
window to fix and deploy before any public disclosure.

## Scope

In scope: the code in this repository and the production services at
`blukios.store` / `seller.blukios.store`.

Out of scope: third-party services (Midtrans, Komerce, Firebase, Ollama models),
the separate mobile repository, and findings that require a compromised host or
physical access.

## Handling of secrets

- Secrets live only in `.env` files and credential JSON, all gitignored.
- `gitleaks` runs in CI on every push (`.gitleaks.toml`).
- Midtrans is in sandbox mode by default (`MIDTRANS_IS_PRODUCTION=false`).

## Known hardening gap

The committed `docker-compose.yml` is a **local-development** configuration:
empty MySQL root password, no Redis/MongoDB authentication, phpMyAdmin and
mongo-express exposed without credentials, infra ports published to the host.
A production deployment must override all of this. This is tracked and is not a
reportable vulnerability in itself — but a production host found running the
unmodified compose file is.
