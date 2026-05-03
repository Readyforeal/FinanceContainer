<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('components.layouts.app')]
#[Title('Settings')]
class extends Component {
};
?>

<div>
    <div class="mb-6">
        <flux:heading size="xl" level="1" class="!text-3xl">Settings</flux:heading>
        <flux:text class="mt-1">Configure your StewardAI preferences.</flux:text>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">
        <div>
            <livewire:settings.income-sources />
        </div>

        <div>
            <livewire:settings.sync-schedule />
        </div>

        <div>
            <livewire:settings.budget-ratios />
        </div>

        <div>
            <livewire:settings.email-recipients />
        </div>

        <div>
            <livewire:settings.appearance />
        </div>
    </div>
</div>
