<x-guest-layout>
    <form method="POST" action="{{ route('password.store') }}" class="space-y-6">
        @csrf

        <div>
            <flux:heading size="lg">Reset your password</flux:heading>
            <flux:text class="mt-2">Enter your new password below.</flux:text>
        </div>

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <flux:input label="Email" type="email" name="email" :value="old('email', $request->email)" required autofocus autocomplete="username" />
        <flux:input label="New Password" type="password" name="password" required autocomplete="new-password" />
        <flux:input label="Confirm Password" type="password" name="password_confirmation" required autocomplete="new-password" />

        <flux:button variant="primary" type="submit" class="w-full">Reset Password</flux:button>
    </form>
</x-guest-layout>
