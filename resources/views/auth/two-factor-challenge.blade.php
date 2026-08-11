<x-layouts.guest title="Two-factor code">
    <h1 class="mb-1 text-lg font-semibold">Two-factor code</h1>
    <p class="mb-6 text-sm text-ink-soft">Enter the 6-digit code from your authenticator app.</p>

    <form method="POST" action="{{ route('two-factor.challenge.store') }}" class="space-y-4" x-data="{ recovery: false }">
        @csrf

        <div x-show="!recovery">
            <x-ui.label for="code">Authenticator code</x-ui.label>
            <x-ui.input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" autofocus x-bind:disabled="recovery" class="tracking-widest" />
        </div>

        <div x-show="recovery" x-cloak>
            <x-ui.label for="recovery_code">Recovery code</x-ui.label>
            <x-ui.input id="recovery_code" name="recovery_code" type="text" autocomplete="off" x-bind:disabled="!recovery" class="uppercase" />
            <p class="mt-1.5 text-sm text-ink-soft">Each recovery code works once.</p>
        </div>

        <x-ui.error for="code" />

        <x-ui.button type="submit" variant="primary" class="w-full">Verify</x-ui.button>

        <button
            type="button"
            class="w-full text-center text-sm font-medium text-ink-soft hover:text-ink"
            x-on:click="recovery = !recovery"
            x-text="recovery ? 'Use an authenticator code' : 'Use a recovery code'"
        ></button>
    </form>
</x-layouts.guest>
