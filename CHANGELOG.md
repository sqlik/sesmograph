# Changelog

## 1.0.0 - 2026-08-12

First public release.

- Topics with per-topic secret webhooks; SNS signature verification and
  automatic subscription confirmation; idempotent event ingestion
- Message search and per-message event timelines with SMTP bounce
  diagnostics; optional message-body ingest via API
- Dashboards from daily aggregates: volume, bounce/complaint rates against
  AWS limits, event-type donut, recent activity; topic color labels with
  multi-select filtering
- Alerts: SMTP / Telegram / Pushover / signed webhook channels; immediate,
  threshold and silence rules with cooldowns and a delivery log
- Suppression list built from hard bounces and complaints, with CSV export
  and an address-check endpoint
- Read-only REST API + MCP server (shared Bearer tokens) for AI assistants
- Mandatory TOTP 2FA with recovery codes; single admin created via CLI
- Two user-switchable themes (Hum, Mono); self-hosted fonts, no CDN calls
- Runs on plain PHP hosting: no Docker, no Redis, no queue daemon
