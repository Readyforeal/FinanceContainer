<x-guest-layout>
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-6">
        @csrf

        <div>
            <flux:heading size="lg">Log in to your account</flux:heading>
            <flux:text class="mt-2">Welcome back!</flux:text>
        </div>

        <flux:input label="Email" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />

        <flux:field>
            <div class="mb-1 flex justify-between">
                <flux:label>Password</flux:label>
                @if (Route::has('password.request'))
                    <flux:link href="{{ route('password.request') }}" variant="subtle" size="sm">Forgot password?</flux:link>
                @endif
            </div>
            <flux:input type="password" name="password" required autocomplete="current-password" />
            <flux:error name="password" />
        </flux:field>

        <flux:checkbox name="remember" label="Remember me" />

        <flux:button variant="primary" type="submit" class="w-full">Log in</flux:button>
    </form>
</x-guest-layout>
