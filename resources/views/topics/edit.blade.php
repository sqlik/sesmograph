<x-layouts.app :title="'Edit · '.$topic->name">
    <div class="max-w-xl">
        <h1 class="mb-6 text-xl font-semibold">Edit topic</h1>

        <x-ui.card>
            <form method="POST" action="{{ route('topics.update', $topic) }}" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <x-ui.label for="name">Name</x-ui.label>
                    <x-ui.input id="name" name="name" :value="old('name', $topic->name)" required />
                    <x-ui.error for="name" />
                </div>

                <div>
                    <x-ui.label for="description">Description <span class="font-normal text-ink-faint">(optional)</span></x-ui.label>
                    <x-ui.input id="description" name="description" :value="old('description', $topic->description)" />
                    <x-ui.error for="description" />
                </div>

                @include('topics._color-picker', ['current' => old('color', $topic->color)])

                <div>
                    <x-ui.label for="retention_days">Retention, days <span class="font-normal text-ink-faint">(empty = default, {{ config('sesmograph.event_retention_days') }})</span></x-ui.label>
                    <x-ui.input id="retention_days" name="retention_days" type="number" min="1" max="3650" :value="old('retention_days', $topic->retention_days)" class="max-w-32" />
                    <x-ui.error for="retention_days" />
                </div>

                <div>
                    <x-ui.label for="sns_topic_arn">Expected SNS topic ARN <span class="font-normal text-ink-faint">(optional)</span></x-ui.label>
                    <x-ui.input id="sns_topic_arn" name="sns_topic_arn" :value="old('sns_topic_arn', $topic->sns_topic_arn)" placeholder="arn:aws:sns:eu-west-1:123456789012:my-topic" />
                    <p class="mt-1.5 text-xs text-ink-faint">Pinned automatically on the first delivery; messages from any other SNS topic are rejected. Clear to re-pin after recreating the SNS topic.</p>
                    <x-ui.error for="sns_topic_arn" />
                </div>

                <div>
                    <x-ui.switch name="active" value="1" :checked="(bool) old('active', $topic->active)">
                        Active <span class="text-ink-faint">- when off, the webhook rejects incoming events</span>
                    </x-ui.switch>
                </div>

                <div class="flex items-center gap-3">
                    <x-ui.button type="submit" variant="primary">Save</x-ui.button>
                    <a href="{{ route('topics.show', $topic) }}" class="text-sm font-medium text-ink-soft hover:text-ink">Cancel</a>
                </div>
            </form>
        </x-ui.card>

        <x-ui.card class="mt-6" x-data="{ confirming: false }">
            <h2 class="mb-1 font-medium text-danger">Delete topic</h2>
            <p class="mb-3 text-sm text-ink-soft">Removes the topic with all its messages and events. The webhook URL stops working.</p>

            <x-ui.button variant="danger" x-show="!confirming" x-on:click="confirming = true">Delete topic</x-ui.button>

            <form method="POST" action="{{ route('topics.destroy', $topic) }}" x-show="confirming" x-cloak class="flex items-center gap-3">
                @csrf
                @method('DELETE')
                <x-ui.button type="submit" variant="danger">Delete "{{ $topic->name }}" permanently</x-ui.button>
                <button type="button" class="text-sm font-medium text-ink-soft hover:text-ink" x-on:click="confirming = false">Keep it</button>
            </form>
        </x-ui.card>
    </div>
</x-layouts.app>
