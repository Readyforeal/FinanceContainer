<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('components.layouts.app')]
#[Title('Budgets')]
class extends Component {
};
?>

<div>
    <flux:heading size="xl" level="1" class="mb-1">Budgets</flux:heading>
    <flux:text class="mb-6">Plan and track your monthly spending by category.</flux:text>

    <livewire:budgets.budget-manager />
</div>
