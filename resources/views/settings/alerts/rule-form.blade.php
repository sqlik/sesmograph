<x-layouts.app :title="$rule ? 'Edit rule' : 'Add rule'">
    @php
        $editing = $rule !== null;
        $config = $rule->config ?? [];
        $selectedChannels = collect(old('channels', $rule?->channels->pluck('id')->all() ?? []))->map(fn ($id) => (int) $id);
        $selectedTriggers = old('triggers', $config['triggers'] ?? \App\Models\AlertRule::IMMEDIATE_TRIGGERS);
    @endphp

    <div>
        <h1 class="mb-1 text-xl font-semibold">{{ $editing ? 'Edit rule' : 'Add rule' }}</h1>
        <p class="mb-4 text-sm text-ink-soft">A rule watches one topic - or all of them - and fires the selected channels.</p>

        <x-settings-nav />

        @if ($channels->isEmpty())
            <x-ui.card class="flex flex-col items-start gap-3">
                <p class="text-sm text-ink-soft">No channels yet. Add a channel first - a rule needs somewhere to deliver.</p>
                <a href="{{ route('settings.alert-channels.create') }}" class="inline-flex items-center justify-center rounded-full bg-accent px-4 py-2 text-sm font-medium text-ink hover:bg-accent-deep focus:outline-2 focus:outline-offset-2 focus:outline-focus">
                    Add channel
                </a>
            </x-ui.card>
        @else
            {{-- Js::from - old() lands in a JS expression, where entity-escaping alone does not prevent injection --}}
            <x-ui.card x-data="{ type: {{ \Illuminate\Support\Js::from(old('type', $rule->type ?? 'immediate')) }} }">
                <form
                    method="POST"
                    action="{{ $editing ? route('settings.alert-rules.update', $rule) : route('settings.alert-rules.store') }}"
                    class="space-y-4"
                >
                    @csrf
                    @if ($editing)
                        @method('PUT')
                    @endif

                    <div>
                        <x-ui.label for="topic_id">Topic</x-ui.label>
                        <x-ui.select id="topic_id" name="topic_id">
                            <option value="">All topics</option>
                            @foreach ($topics as $topic)
                                <option value="{{ $topic->id }}" @selected((int) old('topic_id', $rule->topic_id ?? 0) === $topic->id)>{{ $topic->name }}</option>
                            @endforeach
                        </x-ui.select>
                        <x-ui.error for="topic_id" />
                    </div>

                    <div>
                        <x-ui.label for="type">Rule type</x-ui.label>
                        <x-ui.select id="type" name="type" x-model="type">
                            <option value="immediate">Immediate - fire on a single event</option>
                            <option value="threshold">Threshold - fire when a rate crosses a limit</option>
                            <option value="silence">Silence - fire when a topic stops receiving events</option>
                        </x-ui.select>
                        <x-ui.error for="type" />
                    </div>

                    <div x-show="type === 'immediate'" x-cloak>
                        <x-ui.label>Fire on</x-ui.label>
                        <div class="space-y-2.5">
                            <div><x-ui.switch name="triggers[]" value="hard_bounce" :checked="in_array('hard_bounce', $selectedTriggers, true)">Hard bounce</x-ui.switch></div>
                            <div><x-ui.switch name="triggers[]" value="complaint" :checked="in_array('complaint', $selectedTriggers, true)">Complaint</x-ui.switch></div>
                        </div>
                        <x-ui.error for="triggers" />
                    </div>

                    <div x-show="type === 'threshold'" x-cloak class="space-y-4">
                        <div>
                            <x-ui.label for="metric">Metric</x-ui.label>
                            <x-ui.select id="metric" name="metric">
                                <option value="bounce_rate" @selected(old('metric', $config['metric'] ?? '') === 'bounce_rate')>Bounce rate (AWS limit 5%)</option>
                                <option value="complaint_rate" @selected(old('metric', $config['metric'] ?? '') === 'complaint_rate')>Complaint rate (AWS limit 0.1%)</option>
                            </x-ui.select>
                            <x-ui.error for="metric" />
                        </div>
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <x-ui.label for="threshold">Threshold %</x-ui.label>
                                <x-ui.input id="threshold" name="threshold" type="number" step="0.01" min="0.01" max="100" :value="old('threshold', $config['threshold'] ?? 5)" />
                                <x-ui.error for="threshold" />
                            </div>
                            <div>
                                <x-ui.label for="window_minutes">Window, min</x-ui.label>
                                <x-ui.input id="window_minutes" name="window_minutes" type="number" min="5" max="1440" :value="old('window_minutes', $config['window_minutes'] ?? 60)" />
                                <x-ui.error for="window_minutes" />
                            </div>
                            <div>
                                <x-ui.label for="min_sends">Min sends</x-ui.label>
                                <x-ui.input id="min_sends" name="min_sends" type="number" min="1" :value="old('min_sends', $config['min_sends'] ?? 20)" />
                                <x-ui.error for="min_sends" />
                            </div>
                        </div>
                        <p class="text-xs text-ink-faint">Checked every 5 minutes. Min sends keeps a single bounce in a quiet hour from firing.</p>
                    </div>

                    <div x-show="type === 'silence'" x-cloak>
                        <x-ui.label for="hours">Quiet hours before firing</x-ui.label>
                        <x-ui.input id="hours" name="hours" type="number" min="1" max="720" :value="old('hours', $config['hours'] ?? config('sesmograph.silent_topic_hours'))" class="max-w-32" />
                        <p class="mt-1.5 text-xs text-ink-faint">Fires when an active topic that has received events before goes this long without any. Set the cooldown below to how often you want the reminder.</p>
                        <x-ui.error for="hours" />
                    </div>

                    <div>
                        <x-ui.label for="cooldown_minutes">Cooldown, minutes</x-ui.label>
                        <x-ui.input id="cooldown_minutes" name="cooldown_minutes" type="number" min="0" max="1440" :value="old('cooldown_minutes', $rule->cooldown_minutes ?? 30)" class="max-w-40" />
                        <p class="mt-1.5 text-xs text-ink-faint">One alert per topic per cooldown window; a burst of bounces will not flood the channels.</p>
                        <x-ui.error for="cooldown_minutes" />
                    </div>

                    <div>
                        <x-ui.label>Channels</x-ui.label>
                        <div class="space-y-2.5">
                            @foreach ($channels as $channel)
                                <div>
                                    <x-ui.switch name="channels[]" :value="$channel->id" :checked="$selectedChannels->contains($channel->id)">
                                        {{ $channel->name }} <span class="text-ink-faint">({{ $channel->typeLabel() }})</span>
                                    </x-ui.switch>
                                </div>
                            @endforeach
                        </div>
                        <x-ui.error for="channels" />
                    </div>

                    @if ($editing)
                        <div><x-ui.switch name="enabled" value="1" :checked="(bool) old('enabled', $rule->enabled)">Enabled</x-ui.switch></div>
                    @endif

                    <div class="flex items-center gap-3">
                        <x-ui.button type="submit" variant="primary">{{ $editing ? 'Save changes' : 'Add rule' }}</x-ui.button>
                        <a href="{{ route('settings.alerts') }}" class="text-sm font-medium text-ink-soft hover:text-ink hover:underline">Cancel</a>
                    </div>
                </form>
            </x-ui.card>
        @endif
    </div>
</x-layouts.app>
