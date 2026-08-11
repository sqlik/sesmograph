# Alerts

Alerts are **channels** (where to deliver) x **rules** (when to fire),
configured under **Settings -> Alerts**.

## Channels

| Type | Notes |
|---|---|
| SMTP email | Any mailbox; uses your own SMTP server, not SES |
| Telegram | Bot token + chat id ([create a bot](https://core.telegram.org/bots#how-do-i-create-a-bot) with @BotFather, message it once, read the chat id from `getUpdates`) |
| Pushover | User key + app token |
| Webhook | JSON POST to your URL, signed with `X-Sesmograph-Signature: sha256=<hmac>` over the raw body using your shared secret |

Secrets are stored encrypted. When editing a channel, leaving a secret field
blank keeps the stored value. Every channel has a **Send test** button - use
it before trusting a rule with production traffic.

## Rules

A rule watches one topic - or all topics - and fires the selected channels.

- **Immediate** - fires on every hard bounce and/or complaint, seconds after
  the event arrives. Best for low-volume transactional streams where every
  bounce matters.
- **Threshold** - fires when the bounce or complaint rate over a trailing
  window crosses a limit (checked every 5 minutes). `Min sends` stops a
  single bounce in a quiet hour from firing. Sensible starting points:
  bounce rate > 5% (AWS's limit) over 60 min, complaint rate > 0.1%.
- **Silence** - fires when an active topic that has received events before
  goes N hours without any. A broken SNS subscription fails silently; this
  is the rule that tells you about it. Topics that never received anything
  are skipped (they are simply not connected yet).

## Cooldown

One alert per rule + topic per cooldown window, whatever the number of
events - a burst of bounces produces a single notification. For silence
rules the cooldown doubles as the reminder interval.

## Delivery log

Every fired alert lands in **Settings -> Activity** with its per-channel
outcome (sent / failed with the error in the tooltip). Failures are also
written to the Laravel log.
