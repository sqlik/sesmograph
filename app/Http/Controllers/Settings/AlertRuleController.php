<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AlertChannel;
use App\Models\AlertRule;
use App\Models\Topic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AlertRuleController extends Controller
{
    public function create(): View
    {
        return $this->form(null);
    }

    public function store(Request $request): RedirectResponse
    {
        [$data, $channelIds] = $this->validated($request);

        $rule = AlertRule::create($data);
        $rule->channels()->sync($channelIds);

        return redirect()->route('settings.alerts')->with('status', 'Rule created');
    }

    public function edit(AlertRule $rule): View
    {
        return $this->form($rule);
    }

    public function update(Request $request, AlertRule $rule): RedirectResponse
    {
        [$data, $channelIds] = $this->validated($request);

        $rule->update($data + ['enabled' => $request->boolean('enabled')]);
        $rule->channels()->sync($channelIds);

        return redirect()->route('settings.alerts')->with('status', 'Rule updated');
    }

    public function destroy(AlertRule $rule): RedirectResponse
    {
        $rule->delete();

        return redirect()->route('settings.alerts')->with('status', 'Rule deleted');
    }

    private function form(?AlertRule $rule): View
    {
        return view('settings.alerts.rule-form', [
            'rule' => $rule,
            'topics' => Topic::query()->orderBy('name')->get(),
            'channels' => AlertChannel::query()->orderBy('name')->get(),
        ]);
    }

    /** @return array{array, list<int>} */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'topic_id' => ['nullable', 'integer', 'exists:topics,id'],
            'type' => ['required', Rule::in(['immediate', 'threshold', 'silence'])],
            'cooldown_minutes' => ['required', 'integer', 'between:0,1440'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['integer', 'exists:alert_channels,id'],
            'triggers' => ['required_if:type,immediate', 'array'],
            'triggers.*' => [Rule::in(AlertRule::IMMEDIATE_TRIGGERS)],
            'metric' => ['required_if:type,threshold', Rule::in(AlertRule::METRICS)],
            'threshold' => ['required_if:type,threshold', 'numeric', 'between:0.01,100'],
            'window_minutes' => ['required_if:type,threshold', 'integer', 'between:5,1440'],
            'min_sends' => ['required_if:type,threshold', 'integer', 'between:1,100000'],
            'hours' => ['required_if:type,silence', 'integer', 'between:1,720'],
        ]);

        $config = match ($data['type']) {
            'immediate' => ['triggers' => array_values($data['triggers'] ?? [])],
            'silence' => ['hours' => (int) $data['hours']],
            default => [
                'metric' => $data['metric'],
                'threshold' => (float) $data['threshold'],
                'window_minutes' => (int) $data['window_minutes'],
                'min_sends' => (int) $data['min_sends'],
            ],
        };

        return [
            [
                'topic_id' => $data['topic_id'] ?? null,
                'type' => $data['type'],
                'config' => $config,
                'cooldown_minutes' => (int) $data['cooldown_minutes'],
            ],
            array_map('intval', $data['channels']),
        ];
    }
}
