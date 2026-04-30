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
    <livewire:chat.chat-page />
</div>
