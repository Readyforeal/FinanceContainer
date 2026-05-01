<x-guest-layout>
    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-6">
        @csrf

        <div>
            <flux:heading size="lg">Confirm your password</flux:heading>
            <flux:text class="mt-2">This is a secure area of the application. Please confirm your password before continuing.</flux:text>
        </div>

        <flux:input label="Password" type="password" name="password" required autocomplete="current-password" />

        <flux:button variant="primary" type="submit" class="w-full">Confirm</flux:button>
    </form>
</x-guest-layout>
