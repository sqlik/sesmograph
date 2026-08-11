# REST API and MCP

Both interfaces are read-only (plus one content-ingest endpoint), share the
same Bearer tokens (**Settings -> API tokens**, one per consuming service)
and return identical data shapes. Read endpoints are rate-limited to 120
requests/min. All requests are logged to **Settings -> Activity** except
health checks.

```
Authorization: Bearer <token>
```

## REST endpoints

Base URL: `https://your-host/api/v1`

### `GET /events`

Search events, newest first. Query params: `q` (matches recipient, subject,
sender or SES message id), `topic` (topic name), `type` (snake_case event
type, e.g. `bounce`, `delivery_delay`), `from` / `to` (dates), `page`.

### `GET /messages/{sesMessageId}`

One message's full timeline, oldest first, including SMTP bounce
diagnostics.

### `GET /stats`

Daily counts, totals and bounce/complaint rates from the aggregates.
Params: `topic`, `from`, `to` (defaults to the last 30 days).

### `GET /suppressed`

With `?address=user@example.com`: is this address safe to send to
(`suppressed: true/false` with reason and diagnostic). Without it: the full
list, filterable by `topic` and `reason` (`bounce` | `complaint`).

### `POST /messages/{sesMessageId}/content`

Push the sent email's body so it is viewable on the message page - SES
events never carry the body. JSON: `{"html": "...", "text": "..."}` (either
part optional, 2 MB limit per part). Call it right after sending, using the
message id SES returned. Bodies are kept 30 days
(`SESMOGRAPH_CONTENT_RETENTION_DAYS`).

### `GET /health`

For uptime monitors: `{"status":"ok","time":...,"last_event_at":...,
"last_event_age_seconds":...}`. Not recorded in the activity log, so a
per-minute monitor does not drown it.

Filter validation errors return `422` with a standard Laravel error body.

## MCP server

Streamable HTTP endpoint at `https://your-host/mcp`, same Bearer tokens.
Tools: `search_events`, `get_message_timeline`, `get_stats`,
`check_address` - same filters and shapes as REST.

Claude Code:

```bash
claude mcp add --transport http sesmograph https://your-host/mcp \
  --header "Authorization: Bearer <token>"
```

Cursor and other clients (`mcp.json`):

```json
{
  "mcpServers": {
    "sesmograph": {
      "url": "https://your-host/mcp",
      "headers": { "Authorization": "Bearer <token>" }
    }
  }
}
```

With this connected you can ask an AI assistant things like "why did mail to
alice@example.com bounce yesterday?" or "what's the bounce rate on acme-app
this week?" and it answers from your own data.
