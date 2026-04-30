<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('components.layouts.app')]
#[Title('Chat')]
class extends Component {
};
?>

<div>
    <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">Chat</h1>
    <p class="text-zinc-500 dark:text-zinc-400 mt-2">Coming in Phase 2.</p>
</div>
