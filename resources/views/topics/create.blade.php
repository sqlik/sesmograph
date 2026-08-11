<x-layouts.app title="Add topic">
    <div class="max-w-xl">
        <h1 class="mb-1 text-xl font-semibold">Add topic</h1>
        <p class="mb-6 text-sm text-ink-soft">A topic receives SES events for one of your services.</p>

        <x-ui.card>
            <form method="POST" action="{{ route('topics.store') }}" class="space-y-4">
                @csrf

                <div>
                    <x-ui.label for="name">Name</x-ui.label>
                    <x-ui.input id="name" name="name" :value="old('name')" required autofocus placeholder="acme-app" />
                    <x-ui.error for="name" />
                </div>

                <div>
                    <x-ui.label for="description">Description <span class="font-normal text-ink-faint">(optional)</span></x-ui.label>
                    <x-ui.input id="description" name="description" :value="old('description')" />
                    <x-ui.error for="description" />
                </div>

                @include('topics._color-picker', ['current' => old('color')])

                <div class="flex items-center gap-3">
                    <x-ui.button type="submit" variant="primary">Create topic</x-ui.button>
                    <a href="{{ route('topics.index') }}" class="text-sm font-medium text-ink-soft hover:text-ink">Cancel</a>
                </div>
            </form>
        </x-ui.card>
    </div>
</x-layouts.app>
