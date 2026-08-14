# Architecture

sesmograph is a single-admin Laravel application that receives Amazon SES
events pushed by Amazon SNS and turns them into dashboards, searchable
messages, alerts and suppression lists. It holds **no AWS credentials** -
AWS pushes to it, never the other way around.

```
your app --(SES send)--> Amazon SES --> SNS topic --HTTPS--> /webhooks/{token}
                                                                 |
                                              SNS signature check + idempotent insert
                                                                 |
                    +--------------+---------------+-------------+
                    v              v               v             v
                messages        events      daily_aggregates  suppressed_addresses
                    |              |               |             |
                    +-------- panel / REST API / MCP / alerts ---+
```

## Ingestion

- `POST /webhooks/{token}` - one secret 48-char token per topic; CSRF-exempt.
- Every SNS message goes through signature validation
  (`aws/aws-php-sns-message-validator`) before anything is stored.
  `SubscriptionConfirmation` messages are confirmed automatically (the
  `SubscribeURL` host must end in `.amazonaws.com`).
- The first signed delivery pins its `TopicArn` on the topic; messages from
  any other SNS topic are rejected afterwards, so a leaked webhook URL cannot
  be fed forged events from another AWS account. The pinned ARN is editable
  on the topic's edit page (clear it to re-pin after recreating the SNS topic).
- `SesEventProcessor` upserts the `Message` and inserts the `Event`
  idempotently - a unique key on `(message_id, type, occurred_at)` makes SNS
  redeliveries harmless.
- Message status follows the newest status-bearing event (`delivery`,
  `bounce`, `complaint`...); opens and clicks never change status.
- Event types are stored snake_case (`delivery_delay`, `rendering_failure`).

## Aggregates - why the dashboards stay fast

`daily_aggregates` holds one row per topic per day with one counter per event
type (unique `(topic_id, date)`); rates are always derived, never stored.
Each newly inserted event bumps its topic-day counter with a single atomic
increment (`DailyAggregate::record`) - O(1) per event, inline in the webhook
request, and **no queue worker is required** for ingestion. The first event
of a topic-day falls back to a full recount, which creates the row;
`recount()` stores absolute counts, so replays and races cannot
double-count. Dashboards read aggregates only; the raw
`events` table is touched just for sub-day counters (last hour / 24 h) and
the activity feeds.

Raw events and messages are pruned after the retention window (default 30
days, per-topic override, `SESMOGRAPH_EVENT_RETENTION_DAYS`); aggregates are
kept forever. `app:rebuild-aggregates` and `app:rebuild-suppressed` can
recompute everything from raw events at any time.

## Suppression list

Hard bounces (`bounceType: Permanent`) and complaints land in
`suppressed_addresses` (unique per topic + lowercased address, with hit
count and the last SMTP diagnostic). Recording happens inline during
ingestion and only for newly inserted events, so replays never double-count.
Check an address before sending via `GET /api/v1/suppressed?address=...`.

## Alerts

Channels (SMTP, Telegram, Pushover, webhook with an HMAC
`X-Sesmograph-Signature`) x rules:

- **immediate** - fires on every hard bounce / complaint, dispatched
  after-response from ingestion,
- **threshold** - bounce/complaint rate over a trailing window, evaluated by
  `app:evaluate-alerts` every 5 minutes,
- **silence** - an active topic that received events before goes quiet for
  N hours (a broken SNS subscription fails silently).

Every fired alert is written to `alerts_log` with the per-channel delivery
outcome; the same table is the cooldown ledger (one alert per rule+topic per
cooldown window).

## Auth

Single admin account, created only via `php artisan app:create-admin`.
Sign-in requires TOTP 2FA - enrollment is forced before anything else is
reachable. Recovery codes are single-use; secrets are encrypted at rest; a
timestamp column prevents TOTP replay. Sign-in and the 2FA challenge are
rate-limited per identity + IP.

## Read API and MCP

`App\Services\ApiQueries` is the single shared read model: the REST
controllers (`GET /api/v1/{events,messages/{id},stats,suppressed,health}`)
and the MCP tools (`search_events`, `get_message_timeline`, `get_stats`,
`check_address` on `POST /mcp`, Streamable HTTP) return identical shapes and
share Bearer tokens (SHA-256 hashed at rest). API usage is logged to
`api_request_logs` (Settings -> Activity), except health-check pings.

## Frontend

Blade + Alpine.js + Tailwind 4, ApexCharts for charts. Two user-switchable
themes share every view: **Hum** (cream paper, mint accent, top navigation)
and **Mono** (black on white, bold outlines, sidebar). A theme is a set of
CSS custom-property overrides keyed off `data-theme` on `<html>` plus a
layout shell - new UI must be built against the tokens, never hardcoded
colors. Fonts (Onest, Poppins) are self-hosted; no CDN requests anywhere.
