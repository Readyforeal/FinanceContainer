<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.layouts.app')] #[Title('Bills')] class extends Component {};
?>

<div>
    <div class="mb-6">
        <flux:heading size="xl" level="1" class="!text-3xl">Bills</flux:heading>
        <flux:text class="mt-1">Track and manage your recurring bills.</flux:text>
    </div>

    <livewire:bills.bill-manager />
</div>
