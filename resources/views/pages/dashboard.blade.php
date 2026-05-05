<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.layouts.app')] #[Title('Dashboard')] class extends Component {};
?>

<div>
    <div class="mb-6">
        <flux:heading size="xl" level="1" class="!text-3xl">Dashboard</flux:heading>
        <flux:text class="mt-1">Your financial overview.</flux:text>
    </div>

    <div class="mb-8">
        <livewire:dashboard.balance-sparkline />
    </div>

    <div class="space-y-4">
        <livewire:dashboard.balance-cards />
        <livewire:dashboard.budget-progress />
        <livewire:dashboard.balance-history />

        <livewire:dashboard.category-spending />

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <livewire:dashboard.spending-chart />
            <livewire:dashboard.summary-snippet />
        </div>

        <livewire:dashboard.flagged-transactions />
        <livewire:dashboard.upcoming-bills />
        <livewire:dashboard.goals-summary />
    </div>
</div>
