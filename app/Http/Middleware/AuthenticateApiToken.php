<?php

namespace App\Http\Middleware;

use App\Models\ApiRequestLog;
use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = ApiToken::findByPlainText($request->bearerToken());

        if ($token === null) {
            abort(401, 'A valid API token is required.');
        }

        $token->forceFill(['last_used_at' => now()])->save();

        $response = $next($request);

        // Health pings from uptime monitors would drown the trail.
        if ($request->path() === 'api/v1/health') {
            return $response;
        }

        // Usage trail for Settings -> Activity; pruned with app:prune-events.
        ApiRequestLog::create([
            'api_token_id' => $token->id,
            'method' => $request->method(),
            'path' => Str::limit('/'.$request->path(), 190, ''),
            'status' => $response->getStatusCode(),
            'ip' => $request->ip(),
        ]);

        return $response;
    }
}
