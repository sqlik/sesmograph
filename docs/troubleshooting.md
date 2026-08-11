# Troubleshooting

## The SNS subscription never confirms

The app confirms subscriptions automatically, so a "Pending confirmation" in
the AWS console means the confirmation request never reached it or was
rejected:

- The endpoint must be **HTTPS with a valid certificate** - SNS refuses
  self-signed or expired certs, and the app forces HTTPS in production.
- The webhook URL must match exactly what the topic's AWS setup page shows
  (the token is part of the path).
- The topic must be **Active** - inactive topics answer `410` to everything.
- Check `storage/logs/laravel.log` - signature validation failures and
  rejected `SubscribeURL` hosts (anything not ending in `.amazonaws.com`)
  are logged there.
- In the SNS console you can hit **Request confirmation** to resend.

## Events stopped arriving / never arrive

- Send a test mail **through the configuration set** - either with the
  `X-SES-CONFIGURATION-SET` header or with the set attached as the
  identity's default. Mail sent without the configuration set produces no
  events.
- In SES -> Configuration set -> Event destinations check that the SNS
  destination is enabled and covers all event types.
- In SNS -> your topic -> Subscriptions the HTTPS subscription must be
  **Confirmed**.
- Set up a **Silence** alert rule (Settings -> Alerts) so the next outage
  notifies you instead of failing silently; the dashboard also badges quiet
  topics ("Silent N h").

## Subjects and recipients show as message ids

Enable **"Include original email headers"** on the SES event destination -
that is where subjects, senders and recipients come from. Without it events
still count, but messages have no metadata.

## 419 Page Expired on sign-in

Session/cookie problem: check that `APP_URL` matches the real URL
(scheme included) and that you access the app via that URL, not by IP.

## Locked out of the panel

```bash
php artisan app:reset-password               # new password
php artisan app:reset-password --disable-2fa # also drop 2FA enrollment
```

## Numbers look wrong

Aggregates and the suppression list are derived data and can always be
rebuilt from raw events:

```bash
php artisan app:rebuild-aggregates
php artisan app:rebuild-suppressed
```

Note that raw events are pruned after the retention window (30 days by
default) - rebuilding only covers what is still stored; the historical
`daily_aggregates` rows are never deleted.

## Opens and clicks are zero

SES only produces open/click events if tracking is enabled on the
configuration set (SES -> Configuration set -> Event destinations must include
Opens and Clicks, and the emails must be sent as HTML).

## Alert channel fails

Use **Send test** on the channel (Settings -> Alerts). The exact error is
shown in the flash message and recorded per-delivery in Settings -> Activity
(hover the red channel badge).
