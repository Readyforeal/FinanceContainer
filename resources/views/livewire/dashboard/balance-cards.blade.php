<?php

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\IncomeSource;
use Livewire\Component;

new class extends Component {
    public function with(): array
    {
        $accounts = Account::all();

        $nextPaySource = IncomeSource::where('is_active', true)->whereNotNull('next_pay_date')->orderBy('next_pay_date')->first();

        $nextPayday = $nextPaySource?->next_pay_date;
        $daysUntilPay = $nextPayday ? (int) now()->startOfDay()->diffInDays($nextPayday->startOfDay(), false) : null;

        return compact('accounts', 'nextPayday', 'daysUntilPay');
    }
};
?>

<div>
    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
        @forelse ($accounts as $account)
            <flux:card>
                <div class="flex items-center gap-3 mb-3">
                    @if ($account->type === AccountType::Savings)
                        <flux:icon.piggy-bank variant="mini" class="text-zinc-400 dark:text-zinc-500" />
                    @else
                        <flux:icon.wallet variant="mini" class="text-zinc-400 dark:text-zinc-500" />
                    @endif
                    <flux:text size="sm">{{ $account->name }}</flux:text>
                </div>
                <flux:heading size="xl">
                    ${{ number_format($account->current_balance, 2) }}
                </flux:heading>
                @if ($account->available_balance !== null)
                    <flux:text size="sm" class="mt-1">
                        ${{ number_format($account->available_balance, 2) }} available
                    </flux:text>
                @endif
            </flux:card>
        @empty
            <flux:card class="col-span-full text-center">
                <flux:icon.wallet class="size-10 text-zinc-400 dark:text-zinc-600 mx-auto mb-3" />
                <flux:text class="mb-3">No accounts connected</flux:text>
                <flux:link href="/accounts" wire:navigate size="sm">Connect an account</flux:link>
            </flux:card>
        @endforelse

        @if ($nextPayday)
            <flux:card>
                <div class="flex items-center gap-3 mb-3">
                    <flux:icon.calendar variant="mini" class="text-zinc-400 dark:text-zinc-500" />
                    <flux:text size="sm">Next Payday</flux:text>
                </div>
                <flux:heading size="xl">
                    {{ $daysUntilPay }} days
                </flux:heading>
                <flux:text size="sm" class="mt-1">
                    {{ $nextPayday->format('M j, Y') }}
                </flux:text>
            </flux:card>
        @endif
    </div>
</div>
