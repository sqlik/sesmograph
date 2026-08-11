# Security policy

## Reporting a vulnerability

Please **do not open a public issue** for security problems. Instead use
GitHub's private reporting: **Security -> Report a vulnerability** on the
repository. You will get an answer within a few days.

Please include reproduction steps and affected versions. Fixes are released
for the latest version only.

## Scope notes

- sesmograph is designed for a single trusted admin; there is no in-app
  privilege separation to escalate between.
- The webhook endpoint is intentionally unauthenticated HTTP-wise - its
  security model is the secret URL token plus SNS signature verification.
  Reports about "missing auth on /webhooks" without a signature bypass are
  expected behavior.
- Self-hosted deployments are only as safe as their `.env`, database and
  TLS setup - see the security checklist in [INSTALL.md](INSTALL.md).
