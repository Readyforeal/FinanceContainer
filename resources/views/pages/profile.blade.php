<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('components.layouts.app')]
#[Title('Financial Profile')]
class extends Component {
};
?>

<div>
    <div class="mb-6">
        <flux:heading size="xl">Financial Profile</flux:heading>
        <flux:text class="mt-1">Your complete financial picture.</flux:text>
    </div>

    <livewire:profile.financial-profile />
</div>
