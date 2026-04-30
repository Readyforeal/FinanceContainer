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
    <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">Dashboard</h1>
    <p class="text-zinc-500 dark:text-zinc-400 mt-2">Coming in Phase 3.</p>
</div>
