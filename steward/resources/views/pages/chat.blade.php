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

<div class="-mt-8 -mr-8 -mb-8">
    <livewire:chat.chat-page />
</div>
