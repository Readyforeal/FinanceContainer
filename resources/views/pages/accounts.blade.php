<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('components.layouts.app')]
#[Title('Accounts')]
class extends Component {
};
?>

<div>
    <div class="mb-6">
        <flux:heading size="xl" level="1" class="!text-3xl">Accounts</flux:heading>
        <flux:text class="mt-1">Manage your connected bank accounts.</flux:text>
    </div>

    <livewire:accounts.account-list />
</div>
