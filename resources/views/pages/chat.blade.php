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

<div class="lg:-m-8 lg:h-[calc(100vh-1.5rem)] flex flex-col">
    <livewire:chat.chat-page />
</div>
