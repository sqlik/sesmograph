<x-layouts.guest title="Set up two-factor authentication">
    <h1 class="mb-1 text-lg font-semibold">Set up two-factor authentication</h1>
    <p class="mb-6 text-sm text-ink-soft">Scan the QR code with your authenticator app, then enter the current code.</p>

    <div class="mb-6 flex justify-center rounded-lg border border-edge bg-surface p-4">
        {!! $qrCode !!}
    </div>

    <details class="group mb-6 text-sm">
        <summary class="flex cursor-pointer list-none items-center gap-1.5 font-medium text-ink-soft hover:text-ink [&::-webkit-details-marker]:hidden">
            <svg class="h-4 w-4 transition-transform group-open:rotate-90" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
            Can't scan? Enter the key manually
        </summary>
        <code class="mt-2 block break-all rounded-lg border border-edge bg-surface px-3 py-2 text-xs">{{ $secret }}</code>
    </details>

    <form method="POST" action="{{ route('two-factor.setup.confirm') }}" class="space-y-4">
        @csrf

        <div>
            <x-ui.label for="code">Authenticator code</x-ui.label>
            <x-ui.input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required autofocus class="tracking-widest" />
            <x-ui.error for="code" />
        </div>

        <x-ui.button type="submit" variant="primary" class="w-full">Turn on</x-ui.button>
    </form>

    <x-slot:footer>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="font-medium text-ink-soft hover:text-ink">Sign out</button>
        </form>
    </x-slot:footer>
</x-layouts.guest>
