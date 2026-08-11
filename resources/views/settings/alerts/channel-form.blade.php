<x-layouts.app :title="$channel ? 'Edit channel' : 'Add channel'">
    @php
        $config = $channel->config ?? [];
        $editing = $channel !== null;
    @endphp

    <div>
        <h1 class="mb-1 text-xl font-semibold">{{ $editing ? 'Edit channel' : 'Add channel' }}</h1>
        <p class="mb-4 text-sm text-ink-soft">
            @if ($editing)
                Leave secret fields blank to keep their current values.
            @else
                A channel is one place alerts get delivered to.
            @endif
        </p>

        <x-settings-nav />

        {{-- Js::from - old() lands in a JS expression, where entity-escaping alone does not prevent injection --}}
        <x-ui.card x-data="{ type: {{ \Illuminate\Support\Js::from(old('type', $channel->type ?? 'smtp')) }} }">
            <form
                method="POST"
                action="{{ $editing ? route('settings.alert-channels.update', $channel) : route('settings.alert-channels.store') }}"
                class="space-y-4"
            >
                @csrf
                @if ($editing)
                    @method('PUT')
                @endif

                <div>
                    <x-ui.label for="name">Name</x-ui.label>
                    <x-ui.input id="name" name="name" :value="old('name', $channel->name ?? '')" placeholder="Ops alerts" required />
                    <x-ui.error for="name" />
                </div>

                <div>
                    <x-ui.label for="type">Type</x-ui.label>
                    @if ($editing)
                        <x-ui.input id="type" :value="$channel->typeLabel()" disabled />
                    @else
                        <x-ui.select id="type" name="type" x-model="type">
                            <option value="smtp">Email (SMTP)</option>
                            <option value="telegram">Telegram</option>
                            <option value="pushover">Pushover</option>
                            <option value="webhook">Webhook</option>
                        </x-ui.select>
                    @endif
                    <x-ui.error for="type" />
                </div>

                <div x-show="type === 'smtp'" x-cloak class="space-y-4">
                    <div class="grid grid-cols-3 gap-3">
                        <div class="col-span-2">
                            <x-ui.label for="config_host">SMTP host</x-ui.label>
                            <x-ui.input id="config_host" name="config[host]" :value="old('config.host', $config['host'] ?? '')" placeholder="smtp.eu.mailgun.org" />
                            <x-ui.error for="config.host" />
                        </div>
                        <div>
                            <x-ui.label for="config_port">Port</x-ui.label>
                            <x-ui.input id="config_port" name="config[port]" type="number" :value="old('config.port', $config['port'] ?? 587)" />
                            <x-ui.error for="config.port" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <x-ui.label for="config_username">Username</x-ui.label>
                            <x-ui.input id="config_username" name="config[username]" :value="old('config.username', $config['username'] ?? '')" />
                            <x-ui.error for="config.username" />
                        </div>
                        <div>
                            <x-ui.label for="config_password">Password</x-ui.label>
                            <x-ui.input id="config_password" name="config[password]" type="password" autocomplete="new-password" />
                            <x-ui.error for="config.password" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <x-ui.label for="config_from_address">From address</x-ui.label>
                            <x-ui.input id="config_from_address" name="config[from_address]" :value="old('config.from_address', $config['from_address'] ?? '')" placeholder="alerts@example.com" />
                            <x-ui.error for="config.from_address" />
                        </div>
                        <div>
                            <x-ui.label for="config_to_address">To address</x-ui.label>
                            <x-ui.input id="config_to_address" name="config[to_address]" :value="old('config.to_address', $config['to_address'] ?? '')" placeholder="you@example.com" />
                            <x-ui.error for="config.to_address" />
                        </div>
                    </div>
                    <p class="text-xs text-ink-faint">Use an SMTP provider other than SES, so alerts arrive even when SES has problems.</p>
                </div>

                <div x-show="type === 'telegram'" x-cloak class="space-y-4">
                    <div>
                        <x-ui.label for="config_bot_token">Bot token</x-ui.label>
                        <x-ui.input id="config_bot_token" name="config[bot_token]" type="password" autocomplete="off" placeholder="123456:ABC..." />
                        <x-ui.error for="config.bot_token" />
                    </div>
                    <div>
                        <x-ui.label for="config_chat_id">Chat ID</x-ui.label>
                        <x-ui.input id="config_chat_id" name="config[chat_id]" :value="old('config.chat_id', $config['chat_id'] ?? '')" placeholder="-1001234567890" />
                        <x-ui.error for="config.chat_id" />
                    </div>
                </div>

                <div x-show="type === 'pushover'" x-cloak class="space-y-4">
                    <div>
                        <x-ui.label for="config_app_token">Application token</x-ui.label>
                        <x-ui.input id="config_app_token" name="config[app_token]" type="password" autocomplete="off" />
                        <x-ui.error for="config.app_token" />
                    </div>
                    <div>
                        <x-ui.label for="config_user_key">User key</x-ui.label>
                        <x-ui.input id="config_user_key" name="config[user_key]" :value="old('config.user_key', $config['user_key'] ?? '')" />
                        <x-ui.error for="config.user_key" />
                    </div>
                </div>

                <div x-show="type === 'webhook'" x-cloak class="space-y-4">
                    <div>
                        <x-ui.label for="config_url">URL</x-ui.label>
                        <x-ui.input id="config_url" name="config[url]" :value="old('config.url', $config['url'] ?? '')" placeholder="https://n8n.example.com/webhook/..." />
                        <x-ui.error for="config.url" />
                    </div>
                    <div>
                        <x-ui.label for="config_secret">Signing secret</x-ui.label>
                        <x-ui.input id="config_secret" name="config[secret]" type="password" autocomplete="off" />
                        <p class="mt-1.5 text-xs text-ink-faint">Optional. Requests carry an X-Sesmograph-Signature header: sha256 HMAC of the JSON body.</p>
                        <x-ui.error for="config.secret" />
                    </div>
                </div>

                @if ($editing)
                    <div><x-ui.switch name="enabled" value="1" :checked="(bool) old('enabled', $channel->enabled)">Enabled</x-ui.switch></div>
                @endif

                <div class="flex items-center gap-3">
                    <x-ui.button type="submit" variant="primary">{{ $editing ? 'Save changes' : 'Add channel' }}</x-ui.button>
                    <a href="{{ route('settings.alerts') }}" class="text-sm font-medium text-ink-soft hover:text-ink hover:underline">Cancel</a>
                </div>
            </form>
        </x-ui.card>
    </div>
</x-layouts.app>
