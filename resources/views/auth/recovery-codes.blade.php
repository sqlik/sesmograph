<x-layouts.app title="Recovery codes">
    <div>
        <h1 class="mb-1 text-xl font-semibold">Recovery codes</h1>
        <p class="mb-4 text-sm text-ink-soft">
            Each code signs you in once if you lose access to your authenticator. Store them outside this browser.
        </p>

        <x-settings-nav />

        <x-ui.card class="max-w-xl">
            @if (count($codes) === 0)
                <p class="text-sm text-ink-soft">No recovery codes left. Generate new ones now.</p>
            @else
                <ul class="mx-auto grid w-fit grid-cols-2 gap-x-16 gap-y-2 font-mono text-sm">
                    @foreach ($codes as $code)
                        <li>{{ $code }}</li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>

        <div class="mt-4 flex items-center gap-3">
            <a href="{{ route('two-factor.recovery-codes.download') }}" class="inline-flex items-center justify-center rounded-full border border-edge bg-surface px-4 py-2 text-sm font-medium text-ink hover:bg-edge/50 focus:outline-2 focus:outline-offset-2 focus:outline-focus">
                Download
            </a>
            <form method="POST" action="{{ route('two-factor.recovery-codes.regenerate') }}">
                @csrf
                <x-ui.button type="submit" variant="secondary">Generate new codes</x-ui.button>
            </form>
        </div>
        <p class="mt-3 text-sm text-ink-faint">Generating new codes invalidates the current set.</p>
    </div>
</x-layouts.app>
