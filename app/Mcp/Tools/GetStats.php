<?php

namespace App\Mcp\Tools;

use App\Services\ApiQueries;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('get_stats')]
class GetStats extends Tool
{
    protected string $description = 'Daily delivery statistics from the aggregates: per-type event counts, bounce rate, and complaint rate over a date range. AWS warns at 5% bounce and 0.1% complaint rate. Defaults to the last 30 days across all topics.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'topic' => $schema->string()->description('Limit to one topic, by name or ID.'),
            'from' => $schema->string()->description('Start date, YYYY-MM-DD (inclusive). Defaults to 29 days ago.'),
            'to' => $schema->string()->description('End date, YYYY-MM-DD (inclusive). Defaults to today.'),
        ];
    }

    public function handle(Request $request, ApiQueries $queries): Response
    {
        // The schema only hints; validate so array/wrong-typed arguments
        // fail cleanly instead of as 500s.
        $args = $request->validate([
            'topic' => ['nullable', 'string'],
            'from' => ['nullable', 'string'],
            'to' => ['nullable', 'string'],
        ]);

        return Response::json($queries->stats(
            $args['topic'] ?? null,
            $args['from'] ?? null,
            $args['to'] ?? null,
        ));
    }
}
