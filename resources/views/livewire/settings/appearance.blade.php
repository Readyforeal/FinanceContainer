<?php
use Livewire\Component;
new class extends Component {};
?>

<div x-data>
    <div class="flex items-center justify-between mb-4">
        <flux:heading size="lg">Appearance</flux:heading>
        <flux:icon.sun class="size-5 text-zinc-400 dark:text-zinc-500" />
    </div>

    <flux:card class="p-5">
        <flux:text size="sm" class="mb-5">Choose your preferred color scheme.</flux:text>

        <div class="flex gap-2">
            <flux:button
                x-on:click="$flux.appearance = 'light'"
                icon="sun"
                size="sm"
                ::variant="$flux.appearance === 'light' ? 'primary' : 'subtle'"
                class="flex-1"
            >Light</flux:button>
            <flux:button
                x-on:click="$flux.appearance = 'dark'"
                icon="moon"
                size="sm"
                ::variant="$flux.appearance === 'dark' ? 'primary' : 'subtle'"
                class="flex-1"
            >Dark</flux:button>
            <flux:button
                x-on:click="$flux.appearance = 'system'"
                icon="monitor"
                size="sm"
                ::variant="$flux.appearance === 'system' ? 'primary' : 'subtle'"
                class="flex-1"
            >System</flux:button>
        </div>
    </flux:card>
</div>
