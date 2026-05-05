<?php

use App\Models\Bill;
use Livewire\Component;

new class extends Component {
    public function with(): array
    {
        $now = now();
        $bills = Bill::where('is_active', true)->orderBy('due_day')->get();

        $billsWithStatus = $bills->map(function ($bill) use ($now) {
            $bill->status = $bill->statusForMonth($now);
            $bill->payment = $bill->matchingTransaction(
                $now->copy()->startOfMonth(),
                $now->copy()->endOfMonth()
            );
            return $bill;
        })->filter(fn ($b) => $b->status !== 'paid')
          ->sortBy('due_day')
          ->take(5);

        $totalUpcoming = $billsWithStatus->sum(fn ($b) => (float) ($b->amount ?? 0));

        return compact('billsWithStatus', 'totalUpcoming');
    }
};
?>

<flux:card>
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2">
            <flux:icon.calendar-days variant="mini" class="text-zinc-400 dark:text-zinc-500" />
            <flux:heading size="sm">Upcoming Bills</flux:heading>
        </div>
        <flux:link href="/bills" wire:navigate size="sm">View all</flux:link>
    </div>

    @if ($billsWithStatus->isEmpty())
        <div class="text-center py-4">
            <flux:text size="sm">All bills paid this month</flux:text>
        </div>
    @else
        <div class="space-y-3">
            @foreach ($billsWithStatus as $bill)
                <div class="flex items-center justify-between">
                    <div class="min-w-0">
                        <flux:text size="sm" class="font-medium truncate">{{ $bill->name }}</flux:text>
                        <flux:text size="xs">Due {{ $bill->dueDateForMonth(now())->format('M j') }}</flux:text>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <flux:text size="sm" class="font-semibold">
                            {{ $bill->is_fixed ? '' : '~' }}${{ number_format($bill->amount ?? 0, 2) }}
                        </flux:text>
                        @if ($bill->status === 'overdue')
                            <flux:badge color="red" size="sm">Overdue</flux:badge>
                        @elseif ($bill->status === 'due_soon')
                            <flux:badge color="amber" size="sm">Due Soon</flux:badge>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        @if ($totalUpcoming > 0)
            <flux:separator class="my-3" />
            <div class="flex items-center justify-between">
                <flux:text size="sm">Total upcoming</flux:text>
                <flux:text size="sm" class="font-semibold">${{ number_format($totalUpcoming, 2) }}</flux:text>
            </div>
        @endif
    @endif
</flux:card>
