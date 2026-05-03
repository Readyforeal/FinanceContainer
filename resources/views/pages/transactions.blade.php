<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('components.layouts.app')]
#[Title('Transactions')]
class extends Component {
};
?>

<div>
    <div class="mb-6">
        <flux:heading size="xl" level="1" class="!text-3xl">Transactions</flux:heading>
        <flux:text class="mt-1">Review and categorize your spending.</flux:text>
    </div>

    <livewire:transactions.transaction-list />
</div>
