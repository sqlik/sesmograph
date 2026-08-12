<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AppearanceController extends Controller
{
    public function edit(): View
    {
        return view('settings.appearance');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'theme' => ['required', Rule::in(array_keys(User::THEMES))],
        ]);

        $request->user()->update($data);

        // back(): works for the Appearance form and the quick toggle alike.
        // The plain cookie keeps the choice visible on the login screen
        // after logout; httpOnly off so the guest toggle can overwrite it.
        return back()->with('status', 'Theme updated')
            ->withCookie(cookie()->forever('theme', $data['theme'], httpOnly: false));
    }
}
