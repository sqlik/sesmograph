@props(['title' => null])

<x-layouts.base :title="$title">
    <main class="relative flex min-h-screen flex-col items-center justify-center px-4 py-12">
        <x-theme-toggle-guest class="absolute right-4 top-4" />
        <x-logo size="lg" class="mb-8" />
        <div class="w-full max-w-sm rounded-card border border-edge bg-panel p-8">
            {{ $slot }}
        </div>
        @isset($footer)
            <div class="mt-6 w-full max-w-sm text-center text-sm text-ink-soft">
                {{ $footer }}
            </div>
        @endisset
    </main>
</x-layouts.base>
