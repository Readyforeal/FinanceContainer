<?php

use Livewire\Component;

new class extends Component {};
?>

<div x-data="{ path: window.location.pathname, drawerOpen: false }"
     @popstate.window="path = window.location.pathname"
     x-init="document.addEventListener('livewire:navigated', () => { path = window.location.pathname; drawerOpen = false })"
     class="lg:hidden">

    {{-- Drawer overlay + panel --}}
    <div x-show="drawerOpen"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-40"
         x-cloak>

        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-black/30 dark:bg-black/50 backdrop-blur-sm" @click="drawerOpen = false"></div>

        {{-- Drawer panel --}}
        <div x-show="drawerOpen"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="translate-y-full"
             x-transition:enter-end="translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="translate-y-0"
             x-transition:leave-end="translate-y-full"
             class="absolute bottom-20 left-3 right-3 rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 shadow-xl overflow-hidden">

            {{-- Drawer header --}}
            <div class="px-5 py-3 border-b border-zinc-200 dark:border-zinc-800 flex items-center justify-between">
                <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Better With 90</span>
                <button @click="drawerOpen = false" class="p-1 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300">
                    <x-lucide-x class="w-4 h-4" />
                </button>
            </div>

            {{-- Nav grid --}}
            <div class="grid grid-cols-4 gap-1 p-3">
                @php
                    $allItems = [
                        ['path' => '/dashboard',    'label' => 'Dashboard',    'icon' => 'layout-dashboard'],
                        ['path' => '/transactions', 'label' => 'Transactions', 'icon' => 'arrow-left-right'],
                        ['path' => '/budgets',      'label' => 'Budgets',      'icon' => 'wallet'],
                        ['path' => '/categories',   'label' => 'Categories',   'icon' => 'tags'],
                        ['path' => '/accounts',     'label' => 'Accounts',     'icon' => 'landmark'],
                        ['path' => '/summaries',    'label' => 'Summaries',    'icon' => 'file-text'],
                        ['path' => '/goals',        'label' => 'Goals',        'icon' => 'target'],
                        ['path' => '/chat',         'label' => 'Chat',         'icon' => 'message-square'],
                        ['path' => '/profile',      'label' => 'Profile',      'icon' => 'user'],
                        ['path' => '/settings',     'label' => 'Settings',     'icon' => 'settings'],
                    ];
                @endphp

                @foreach ($allItems as $item)
                    <a href="{{ $item['path'] }}" wire:navigate
                       :class="path === '{{ $item['path'] }}'
                           ? 'bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100'
                           : 'text-zinc-500 dark:text-zinc-400'"
                       class="flex flex-col items-center gap-1.5 rounded-xl px-2 py-3 text-center transition-colors">
                        <x-dynamic-component :component="'lucide-' . $item['icon']" class="w-5 h-5" />
                        <span class="text-[10px] font-medium leading-tight">{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </div>

            {{-- Theme toggle + sign out --}}
            <div class="px-3 pb-3 pt-1 border-t border-zinc-200 dark:border-zinc-800 flex items-center gap-2">
                <div x-data class="flex items-center gap-1 rounded-lg bg-zinc-100 dark:bg-zinc-800/50 p-1 flex-1">
                    <button @click="$store.theme.set('light')"
                        :class="$store.theme.mode === 'light' ? 'bg-white dark:bg-zinc-700 shadow-sm text-zinc-900 dark:text-zinc-100' : 'text-zinc-400 dark:text-zinc-500'"
                        class="flex-1 flex items-center justify-center rounded-md px-2 py-1.5 text-xs font-medium transition-all">
                        <x-lucide-sun class="w-3.5 h-3.5" />
                    </button>
                    <button @click="$store.theme.set('system')"
                        :class="$store.theme.mode === 'system' ? 'bg-white dark:bg-zinc-700 shadow-sm text-zinc-900 dark:text-zinc-100' : 'text-zinc-400 dark:text-zinc-500'"
                        class="flex-1 flex items-center justify-center rounded-md px-2 py-1.5 text-xs font-medium transition-all">
                        <x-lucide-monitor class="w-3.5 h-3.5" />
                    </button>
                    <button @click="$store.theme.set('dark')"
                        :class="$store.theme.mode === 'dark' ? 'bg-white dark:bg-zinc-700 shadow-sm text-zinc-900 dark:text-zinc-100' : 'text-zinc-400 dark:text-zinc-500'"
                        class="flex-1 flex items-center justify-center rounded-md px-2 py-1.5 text-xs font-medium transition-all">
                        <x-lucide-moon class="w-3.5 h-3.5" />
                    </button>
                </div>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="p-2 rounded-lg text-zinc-400 hover:text-red-500 dark:hover:text-red-400 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors">
                        <x-lucide-log-out class="w-4 h-4" />
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Bottom dock bar --}}
    <div class="fixed bottom-3 left-3 right-3 z-30">
        <div class="flex items-center justify-around rounded-full bg-white/70 dark:bg-zinc-900/70 backdrop-blur-xl border border-white/40 dark:border-white/[0.08] shadow-lg shadow-zinc-300/30 dark:shadow-zinc-950/40 px-2 py-1.5 bubble-assistant">

            {{-- Dashboard --}}
            <a href="/dashboard" wire:navigate
               :class="path === '/dashboard' ? 'text-blue-600 dark:text-blue-400' : 'text-zinc-400 dark:text-zinc-500'"
               class="flex flex-col items-center gap-0.5 px-4 py-1.5 rounded-full transition-colors">
                <x-lucide-layout-dashboard class="w-5 h-5" />
                <span class="text-[10px] font-medium">Home</span>
            </a>

            {{-- Chat --}}
            <a href="/chat" wire:navigate
               :class="path === '/chat' ? 'text-blue-600 dark:text-blue-400' : 'text-zinc-400 dark:text-zinc-500'"
               class="flex flex-col items-center gap-0.5 px-4 py-1.5 rounded-full transition-colors">
                <x-lucide-message-square class="w-5 h-5" />
                <span class="text-[10px] font-medium">Chat</span>
            </a>

            {{-- Goals --}}
            <a href="/goals" wire:navigate
               :class="path === '/goals' ? 'text-blue-600 dark:text-blue-400' : 'text-zinc-400 dark:text-zinc-500'"
               class="flex flex-col items-center gap-0.5 px-4 py-1.5 rounded-full transition-colors">
                <x-lucide-target class="w-5 h-5" />
                <span class="text-[10px] font-medium">Goals</span>
            </a>

            {{-- More (grid menu) --}}
            <button @click="drawerOpen = !drawerOpen"
                    :class="drawerOpen ? 'text-blue-600 dark:text-blue-400' : 'text-zinc-400 dark:text-zinc-500'"
                    class="flex flex-col items-center gap-0.5 px-4 py-1.5 rounded-full transition-colors">
                <x-lucide-grid-3x3 class="w-5 h-5" />
                <span class="text-[10px] font-medium">More</span>
            </button>
        </div>
    </div>
</div>
