<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\CheckAddress;
use App\Mcp\Tools\GetMessageTimeline;
use App\Mcp\Tools\GetStats;
use App\Mcp\Tools\SearchEvents;
use Laravel\Mcp\Server;

class SesmographServer extends Server
{
    protected string $name = 'sesmograph';

    protected string $instructions = <<<'TEXT'
        Read-only access to sesmograph, a self-hosted Amazon SES email
        observability tool. Use it to diagnose deliverability: search SES
        events, inspect a message's full timeline (including SMTP bounce
        diagnostics), read delivery stats per topic and date range, and
        check whether an address is on the suppressed list before sending.
        TEXT;

    protected array $tools = [
        SearchEvents::class,
        GetMessageTimeline::class,
        GetStats::class,
        CheckAddress::class,
    ];
}
