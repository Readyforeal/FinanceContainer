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
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">Chat</h1>
        <p class="text-zinc-500 dark:text-zinc-400 mt-1">Talk with your financial advisor.</p>
    </div>

    <livewire:chat.chat-page />
</div>
