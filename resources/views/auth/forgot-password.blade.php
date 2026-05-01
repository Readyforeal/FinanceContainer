<x-guest-layout>
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="space-y-6">
        @csrf

        <div>
            <flux:heading size="lg">Forgot your password?</flux:heading>
            <flux:text class="mt-2">No problem. Enter your email and we'll send you a reset link.</flux:text>
        </div>

        <flux:input label="Email" type="email" name="email" :value="old('email')" required autofocus />

        <flux:button variant="primary" type="submit" class="w-full">Email Password Reset Link</flux:button>
    </form>
</x-guest-layout>
