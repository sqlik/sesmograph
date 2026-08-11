<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AlertChannel;
use App\Services\Alerts\AlertSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AlertChannelController extends Controller
{
    /** Config keys kept from the stored channel when the form field is left blank. */
    private const SECRET_KEYS = ['password', 'bot_token', 'app_token', 'secret'];

    public function create(): View
    {
        return view('settings.alerts.channel-form', ['channel' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        AlertChannel::create($data);

        return redirect()->route('settings.alerts')->with('status', 'Channel created - send a test to verify it');
    }

    public function edit(AlertChannel $channel): View
    {
        return view('settings.alerts.channel-form', ['channel' => $channel]);
    }

    public function update(Request $request, AlertChannel $channel): RedirectResponse
    {
        $data = $this->validated($request, $channel);

        foreach (self::SECRET_KEYS as $key) {
            if (array_key_exists($key, $data['config']) && blank($data['config'][$key])) {
                $data['config'][$key] = $channel->config[$key] ?? null;
            }
        }

        $channel->update($data + ['enabled' => $request->boolean('enabled')]);

        return redirect()->route('settings.alerts')->with('status', 'Channel updated');
    }

    public function destroy(AlertChannel $channel): RedirectResponse
    {
        $channel->delete();

        return redirect()->route('settings.alerts')->with('status', "Channel \"{$channel->name}\" deleted");
    }

    public function test(AlertChannel $channel, AlertSender $sender): RedirectResponse
    {
        try {
            $sender->send(
                $channel,
                'Test alert from sesmograph',
                "This is a test message for the \"{$channel->name}\" channel. Delivery works.",
                ['test' => true],
            );
        } catch (\Throwable $e) {
            return redirect()->route('settings.alerts')
                ->with('status', "Test failed for \"{$channel->name}\": ".AlertSender::redact($e->getMessage()));
        }

        return redirect()->route('settings.alerts')->with('status', "Test sent through \"{$channel->name}\"");
    }

    private function validated(Request $request, ?AlertChannel $channel = null): array
    {
        // Type is fixed after creation; edits only change the fields.
        $type = $channel->type ?? $request->string('type')->toString();

        $secretRule = $channel === null ? 'required' : 'nullable';

        $configRules = match ($type) {
            'smtp' => [
                'config.host' => ['required', 'string', 'max:255'],
                'config.port' => ['required', 'integer', 'between:1,65535'],
                'config.username' => ['nullable', 'string', 'max:255'],
                'config.password' => ['nullable', 'string', 'max:255'],
                'config.from_address' => ['required', 'email'],
                'config.to_address' => ['required', 'email'],
            ],
            'telegram' => [
                'config.bot_token' => [$secretRule, 'string', 'max:255'],
                'config.chat_id' => ['required', 'string', 'max:64'],
            ],
            'pushover' => [
                'config.app_token' => [$secretRule, 'string', 'max:64'],
                'config.user_key' => ['required', 'string', 'max:64'],
            ],
            'webhook' => [
                'config.url' => ['required', 'url:http,https', 'max:2048'],
                'config.secret' => ['nullable', 'string', 'max:255'],
            ],
            default => throw ValidationException::withMessages(['type' => 'Unknown channel type']),
        };

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => [Rule::when($channel === null, ['required', Rule::in(AlertChannel::TYPES)], ['nullable'])],
            ...$configRules,
        ]);

        // Assign, never array-union: a submitted type would win the union
        // and let an edit switch the channel type past config validation.
        $data['type'] = $type;

        return $data;
    }
}
