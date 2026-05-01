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
        <flux:heading size="xl" level="1">Dashboard</flux:heading>
        <flux:text class="mt-1">Your financial overview.</flux:text>
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
