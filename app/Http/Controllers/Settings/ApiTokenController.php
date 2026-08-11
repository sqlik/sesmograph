<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApiTokenController extends Controller
{
    public function index(): View
    {
        return view('settings.api-tokens', [
            'tokens' => ApiToken::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        [, $plain] = ApiToken::issue($data['name']);

        return redirect()->route('settings.api-tokens')
            ->with('plainToken', $plain)
            ->with('status', 'Token created - copy it now, it is shown only once');
    }

    public function destroy(ApiToken $token): RedirectResponse
    {
        $token->delete();

        return redirect()->route('settings.api-tokens')
            ->with('status', "Token \"{$token->name}\" revoked");
    }
}
