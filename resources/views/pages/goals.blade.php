<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('components.layouts.app')]
#[Title('Goals')]
class extends Component {
};
?>

<div>
    <div class="mb-6">
        <flux:heading size="xl">Goals</flux:heading>
        <flux:text class="mt-1">Track your savings goals and progress.</flux:text>
    </div>

    <livewire:goals.goal-manager />
</div>
