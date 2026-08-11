<?php

namespace App\Mcp\Tools;

use App\Services\ApiQueries;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('get_message_timeline')]
class GetMessageTimeline extends Tool
{
    protected string $description = 'Full event history of one email, oldest first: send, delivery, opens, clicks, or bounce with SMTP diagnostics. Look up the SES message ID with search_events if you only know the recipient or subject.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'ses_message_id' => $schema->string()->description('The SES message ID.')->required(),
        ];
    }

    public function handle(Request $request, ApiQueries $queries): Response
    {
        $arguments = $request->validate(['ses_message_id' => ['required', 'string']]);

        $timeline = $queries->messageTimeline($arguments['ses_message_id']);

        if ($timeline === null) {
            return Response::error('No message with this SES message ID.');
        }

        return Response::json($timeline);
    }
}
