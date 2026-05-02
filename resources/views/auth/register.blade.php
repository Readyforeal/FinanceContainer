<x-guest-layout>
    <form method="POST" action="{{ route('register') }}" class="space-y-6">
        @csrf

        <div>
            <flux:heading size="lg">Create your account</flux:heading>
            <flux:text class="mt-2">Get started with Better With 90.</flux:text>
        </div>

        <flux:input label="Name" name="name" :value="old('name')" required autofocus autocomplete="name" />
        <flux:input label="Email" type="email" name="email" :value="old('email')" required autocomplete="username" />
        <flux:input label="Password" type="password" name="password" required autocomplete="new-password" />
        <flux:input label="Confirm Password" type="password" name="password_confirmation" required autocomplete="new-password" />

        <div class="flex items-center justify-between">
            <flux:link href="{{ route('login') }}" variant="subtle" size="sm">Already have an account?</flux:link>
            <flux:button variant="primary" type="submit">Register</flux:button>
        </div>
    </form>
</x-guest-layout>
