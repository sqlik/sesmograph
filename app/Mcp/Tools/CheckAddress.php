<?php

namespace App\Mcp\Tools;

use App\Services\ApiQueries;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('check_address')]
class CheckAddress extends Tool
{
    protected string $description = 'Check whether an email address is on the suppressed list (hard bounce or complaint history). Suppressed means sending to it risks the SES account reputation.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'address' => $schema->string()->description('The email address to check.')->required(),
        ];
    }

    public function handle(Request $request, ApiQueries $queries): Response
    {
        $arguments = $request->validate(['address' => ['required', 'string']]);

        return Response::json($queries->checkAddress($arguments['address']));
    }
}
