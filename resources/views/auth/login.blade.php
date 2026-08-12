<x-layouts.guest title="Sign in">
    <h1 class="mb-6 text-lg font-semibold">Sign in</h1>

    <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
        @csrf

        <div>
            <x-ui.label for="email">Email</x-ui.label>
            <x-ui.input id="email" name="email" type="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-ui.error for="email" />
        </div>

        <div>
            <x-ui.label for="password">Password</x-ui.label>
            <x-ui.input id="password" name="password" type="password" required autocomplete="current-password" />
            <x-ui.error for="password" />
        </div>

        <x-ui.switch name="remember" value="1">Remember this device</x-ui.switch>

        <x-ui.button type="submit" variant="primary" class="w-full">Sign in</x-ui.button>
    </form>
</x-layouts.guest>
