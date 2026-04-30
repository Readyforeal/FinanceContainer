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
        <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">Settings</h1>
        <p class="text-zinc-500 dark:text-zinc-400 mt-1">Configure your StewardAI preferences.</p>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">
        <div>
            <livewire:settings.income-sources />
        </div>

        <div>
            <livewire:settings.sync-schedule />
        </div>
    </div>
</div>
