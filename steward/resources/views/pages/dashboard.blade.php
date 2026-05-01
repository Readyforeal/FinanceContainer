<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('components.layouts.app')]
#[Title('Dashboard')]
class extends Component {
};
?>

<div>
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">Dashboard</h1>
        <p class="text-zinc-500 dark:text-zinc-400 mt-1">Your financial overview.</p>
    </div>

    <div class="space-y-6">
        <livewire:dashboard.balance-cards />
        <livewire:dashboard.budget-progress />

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <livewire:dashboard.spending-chart />
            <livewire:dashboard.summary-snippet />
        </div>

        <livewire:dashboard.flagged-transactions />
        <livewire:dashboard.goals-summary />
    </div>
</div>
