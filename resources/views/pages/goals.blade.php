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
        <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">Goals</h1>
        <p class="text-zinc-500 dark:text-zinc-400 mt-1">Track your savings goals and progress.</p>
    </div>

    <livewire:goals.goal-manager />
</div>
