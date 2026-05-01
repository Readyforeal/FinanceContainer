<x-guest-layout>
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">Verify your email</flux:heading>
            <flux:text class="mt-2">Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn't receive the email, we will gladly send you another.</flux:text>
        </div>

        @if (session('status') == 'verification-link-sent')
            <flux:callout variant="success">
                <flux:callout.text>A new verification link has been sent to the email address you provided during registration.</flux:callout.text>
            </flux:callout>
        @endif

        <div class="flex items-center justify-between">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <flux:button variant="primary" type="submit">Resend Verification Email</flux:button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <flux:link variant="subtle" size="sm" type="submit" as="button">Log Out</flux:link>
            </form>
        </div>
    </div>
</x-guest-layout>
