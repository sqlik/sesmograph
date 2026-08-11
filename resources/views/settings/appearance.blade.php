<x-layouts.app title="Appearance">
    <h1 class="mb-1 text-xl font-semibold">Appearance</h1>
    <p class="mb-4 text-sm text-ink-soft">Pick how the panel looks. The choice is saved on your account.</p>

    <x-settings-nav />

    <form method="POST" action="{{ route('settings.appearance.update') }}" class="max-w-2xl">
        @csrf
        @method('PUT')

        <div class="grid gap-4 sm:grid-cols-2">
            {{-- Hum: cream paper, mint accent, top navigation. --}}
            <label class="cursor-pointer">
                <input type="radio" name="theme" value="hum" class="peer sr-only" @checked(auth()->user()->theme === 'hum')>
                <span class="flex h-full flex-col rounded-card border border-edge bg-panel p-4 peer-checked:outline-2 peer-checked:outline-offset-2 peer-checked:outline-ink peer-focus-visible:ring-2 peer-focus-visible:ring-focus peer-focus-visible:ring-offset-2">
                    <span class="mb-3 flex h-24 flex-col overflow-hidden rounded-lg border border-edge" aria-hidden="true" style="background: oklch(97% 0.012 95)">
                        <span class="block h-6 shrink-0 border-b" style="border-color: oklch(86% 0.014 90)"></span>
                        <span class="block flex-1 space-y-1.5 p-2.5">
                            <span class="block h-2 w-1/2 rounded-full" style="background: oklch(80% 0.16 150)"></span>
                            <span class="block h-9 rounded-md" style="background: oklch(94% 0.016 95)"></span>
                        </span>
                    </span>
                    <span class="block text-sm font-medium">Hum</span>
                    <span class="mt-0.5 block text-xs text-ink-soft">Cream paper, mint accent, top navigation. The original look.</span>
                </span>
            </label>

            {{-- Mono: black ink on white, sidebar. --}}
            <label class="cursor-pointer">
                <input type="radio" name="theme" value="mono" class="peer sr-only" @checked(auth()->user()->theme === 'mono')>
                <span class="flex h-full flex-col rounded-card border border-edge bg-panel p-4 peer-checked:outline-2 peer-checked:outline-offset-2 peer-checked:outline-ink peer-focus-visible:ring-2 peer-focus-visible:ring-focus peer-focus-visible:ring-offset-2">
                    <span class="mb-3 flex h-24 overflow-hidden rounded-lg border border-edge" aria-hidden="true" style="background: #f3f2f0">
                        <span class="block w-1/4 shrink-0 space-y-1.5 p-2">
                            <span class="block h-2 w-full rounded-sm" style="background: #101010"></span>
                            <span class="block h-1.5 w-3/4 rounded-sm" style="background: #b9b7b2"></span>
                            <span class="block h-1.5 w-3/4 rounded-sm" style="background: #b9b7b2"></span>
                        </span>
                        <span class="m-1.5 ml-0 block flex-1 space-y-1.5 rounded-md bg-white p-2">
                            <span class="block h-2 w-1/2 rounded-sm" style="background: #101010"></span>
                            <span class="block h-9 rounded-md border-2" style="border-color: #101010"></span>
                        </span>
                    </span>
                    <span class="block text-sm font-medium">Mono</span>
                    <span class="mt-0.5 block text-xs text-ink-soft">Black ink on white, bold outlines, sidebar navigation.</span>
                </span>
            </label>
        </div>

        <div class="mt-4">
            <x-ui.button type="submit" variant="primary">Save</x-ui.button>
        </div>
    </form>
</x-layouts.app>
