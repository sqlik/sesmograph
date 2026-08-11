<?php

namespace App\Mcp\Tools;

use App\Models\Event;
use App\Services\ApiQueries;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('search_events')]
class SearchEvents extends Tool
{
    protected string $description = 'Search SES email events (sends, deliveries, bounces, complaints, opens, clicks) with the same filters as the panel. Returns matching events newest first, with message context and per-type details.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'q' => $schema->string()->description('Match against recipient, subject, sender, or exact SES message ID.'),
            'topic' => $schema->string()->description('Limit to one topic, by name or ID.'),
            'type' => $schema->string()->enum(Event::TYPES)->description('Limit to one event type.'),
            'from' => $schema->string()->description('Earliest date, YYYY-MM-DD (inclusive).'),
            'to' => $schema->string()->description('Latest date, YYYY-MM-DD (inclusive).'),
            'page' => $schema->integer()->description('Page number, 25 events per page.'),
        ];
    }

    public function handle(Request $request, ApiQueries $queries): Response
    {
        // The schema only hints; a client can still send arrays or wrong
        // types. Validate so those fail cleanly instead of as 500s.
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'topic' => ['nullable', 'string'],
            'type' => ['nullable', 'string'],
            'from' => ['nullable', 'string'],
            'to' => ['nullable', 'string'],
            'page' => ['nullable', 'integer'],
        ]);

        return Response::json($queries->searchEvents($filters));
    }
}
