<?php

return [
    // Days to keep raw events and messages, unless a topic overrides it.
    // Daily aggregates are never pruned.
    'event_retention_days' => env('SESMOGRAPH_EVENT_RETENTION_DAYS', 30),

    // Days to keep stored message content (HTML/text bodies). Shorter than
    // event retention on purpose: bodies are for debugging fresh problems.
    'content_retention_days' => env('SESMOGRAPH_CONTENT_RETENTION_DAYS', 30),

    // Upper bound for a single ingested body part, in bytes.
    'content_max_bytes' => env('SESMOGRAPH_CONTENT_MAX_BYTES', 2 * 1024 * 1024),

    // Days to keep the API request log shown on Settings -> Activity.
    'api_log_retention_days' => env('SESMOGRAPH_API_LOG_RETENTION_DAYS', 30),

    // Hours without any event before an active topic is flagged as silent
    // on the dashboard (a broken SNS subscription fails quietly).
    'silent_topic_hours' => env('SESMOGRAPH_SILENT_TOPIC_HOURS', 24),
];
