<?php

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\IncomeSource;
use Livewire\Component;

new class extends Component {
    public function with(): array
    {
        $accounts = Account::all();

        $nextPaySource = IncomeSource::where('is_active', true)
            ->whereNotNull('next_pay_date')
            ->orderBy('next_pay_date')
            ->first();

        $nextPayday = $nextPaySource?->next_pay_date;
        $daysUntilPay = $nextPayday ? (int) now()->startOfDay()->diffInDays($nextPayday->startOfDay(), false) : null;

        return compact('accounts', 'nextPayday', 'daysUntilPay');
    }
};
?>

<div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        @forelse ($accounts as $account)
            <div class="bubble-assistant rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5">
                <div class="flex items-center gap-3 mb-3">
                    @if ($account->type === AccountType::Savings)
                        <x-lucide-piggy-bank class="w-5 h-5 text-zinc-400 dark:text-zinc-500" />
                    @else
                        <x-lucide-wallet class="w-5 h-5 text-zinc-400 dark:text-zinc-500" />
                    @endif
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $account->name }}</p>
                </div>
                <p class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">
                    ${{ number_format($account->current_balance, 2) }}
                </p>
                @if ($account->available_balance !== null)
                    <p class="text-sm text-zinc-400 dark:text-zinc-500 mt-1">
                        ${{ number_format($account->available_balance, 2) }} available
                    </p>
                @endif
            </div>
        @empty
            <div class="col-span-full rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 text-center">
                <x-lucide-wallet class="w-10 h-10 text-zinc-400 dark:text-zinc-600 mx-auto mb-3" />
                <p class="text-zinc-500 dark:text-zinc-400 mb-3">No accounts connected</p>
                <a href="/accounts" wire:navigate class="text-sm text-blue-500 hover:underline">Connect an account</a>
            </div>
        @endforelse

        @if ($nextPayday)
            <div class="bubble-assistant rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5">
                <div class="flex items-center gap-3 mb-3">
                    <x-lucide-calendar class="w-5 h-5 text-zinc-400 dark:text-zinc-500" />
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">Next Payday</p>
                </div>
                <p class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">
                    {{ $daysUntilPay }} days
                </p>
                <p class="text-sm text-zinc-400 dark:text-zinc-500 mt-1">
                    {{ $nextPayday->format('M j, Y') }}
                </p>
            </div>
        @endif
    </div>
</div>
