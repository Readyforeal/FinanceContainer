<?php
use Livewire\Component;
new class extends Component {};
?>

<div x-data="{ expanded: false, path: window.location.pathname, hasAction: false, actionLabel: null }" @popstate.window="path = window.location.pathname"
    @set-dock-action.window="actionLabel = $event.detail.label || 'Add'; hasAction = true;" x-init="document.addEventListener('livewire:navigated', () => {
        path = window.location.pathname;
        expanded = false;
    });
    document.addEventListener('livewire:navigating', () => {
        hasAction = false;
        actionLabel = null;
    });"
    class="lg:hidden">

    {{-- Expanded grid overlay --}}
    <div x-show="expanded" x-cloak x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0" class="fixed inset-0 z-40 bg-black/30 dark:bg-black/50 backdrop-blur-sm"
        @click="expanded = false">
    </div>

    <div x-show="expanded" x-cloak x-transition:enter="transition-all duration-500 ease-[cubic-bezier(0.16,1,0.3,1)]"
        x-transition:enter-start="translate-y-full opacity-80" x-transition:enter-end="translate-y-0 opacity-100"
        x-transition:leave="transition-all duration-200 ease-in" x-transition:leave-start="translate-y-0 opacity-100"
        x-transition:leave-end="translate-y-full opacity-0"
        class="fixed bottom-26 left-4 right-4 z-50 rounded-3xl bg-white/50 dark:bg-zinc-900/50 backdrop-blur-xl border border-zinc-200/50 dark:border-zinc-700/50 shadow-xl overflow-hidden">

        {{-- Header --}}
        <div class="px-5 py-3 border-b border-zinc-200 dark:border-zinc-700 flex items-center justify-between">
            <flux:text class="font-semibold">All Pages</flux:text>
            <flux:button variant="ghost" size="xs" icon="x" @click="expanded = false" />
        </div>

        {{-- Nav grid --}}
        <div class="grid grid-cols-4 gap-1 p-3">
            @php
                $allItems = [
                    ['path' => '/dashboard', 'label' => 'Dashboard', 'icon' => 'layout-dashboard'],
                    ['path' => '/transactions', 'label' => 'Transactions', 'icon' => 'arrow-left-right'],
                    ['path' => '/budgets', 'label' => 'Budgets', 'icon' => 'wallet'],
                    ['path' => '/categories', 'label' => 'Categories', 'icon' => 'tags'],
                    ['path' => '/accounts', 'label' => 'Accounts', 'icon' => 'landmark'],
                    ['path' => '/summaries', 'label' => 'Summaries', 'icon' => 'file-text'],
                    ['path' => '/goals', 'label' => 'Goals', 'icon' => 'target'],
                    ['path' => '/chat', 'label' => 'Chat', 'icon' => 'message-square'],
                    ['path' => '/profile', 'label' => 'Profile', 'icon' => 'user'],
                    ['path' => '/settings', 'label' => 'Settings', 'icon' => 'settings'],
                ];
            @endphp

            @foreach ($allItems as $item)
                <a href="{{ $item['path'] }}" wire:navigate
                    :class="path === '{{ $item['path'] }}'
                        ?
                        'bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100' :
                        'text-zinc-500 dark:text-zinc-400'"
                    class="flex flex-col items-center gap-1.5 rounded-xl px-2 py-3 text-center transition-colors">
                    <flux:icon :icon="$item['icon']" variant="mini" />
                    <span class="text-[10px] font-medium leading-tight">{{ $item['label'] }}</span>
                </a>
            @endforeach
        </div>
    </div>

    {{-- Action button (fixed above dock) --}}
    <div x-show="hasAction" x-cloak x-transition:enter="transition-all duration-300 ease-[cubic-bezier(0.16,1,0.3,1)]"
        x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition-all duration-150 ease-in" x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-4" class="fixed bottom-26 right-5 z-30">
        <button @click="Livewire.dispatch('dock-action')"
            class="flex items-center gap-2 px-4 py-2.5 rounded-2xl bg-accent/80 text-accent-foreground backdrop-blur-xl shadow-lg text-sm font-medium">
            <flux:icon.plus variant="mini" />
            <span x-text="actionLabel"></span>
        </button>
    </div>

    {{-- Bottom dock bar --}}
    <div class="fixed bottom-0 left-0 right-0 z-40 px-4"
        style="padding-bottom: max(1rem, env(safe-area-inset-bottom));">
        <div class="flex items-center justify-around bg-white/50 dark:bg-zinc-950/60 backdrop-blur-xl shadow-xl border border-zinc-200/50 dark:border-zinc-700/50 px-4 py-4"
            style="border-radius: 2.75rem;">
            @php
                $dockItems = [
                    ['path' => '/dashboard', 'icon' => 'layout-dashboard'],
                    ['path' => '/transactions', 'icon' => 'arrow-left-right'],
                    ['path' => '/budgets', 'icon' => 'wallet'],
                    ['path' => '/chat', 'icon' => 'message-square'],
                ];
            @endphp

            @foreach ($dockItems as $item)
                <a href="{{ $item['path'] }}" wire:navigate
                    :class="path === '{{ $item['path'] }}'
                        ?
                        'text-zinc-900 dark:text-zinc-100' :
                        'text-zinc-400 dark:text-zinc-500'"
                    class="flex items-center justify-center p-2.5 rounded-xl transition-colors">
                    <flux:icon :icon="$item['icon']" variant="mini" />
                </a>
            @endforeach

            <button @click="expanded = !expanded"
                :class="expanded ? 'text-zinc-900 dark:text-zinc-100' : 'text-zinc-400 dark:text-zinc-500'"
                class="flex items-center justify-center p-2.5 rounded-xl transition-colors">
                <flux:icon.grip variant="mini" />
            </button>
        </div>
    </div>
</div>
