<?php

use Livewire\Component;

new class extends Component {};
?>

<div class="hidden lg:flex flex-shrink-0" x-data="{ path: window.location.pathname }" @popstate.window="path = window.location.pathname"
    x-init="document.addEventListener('livewire:navigated', () => { path = window.location.pathname })">
    <div
        class="fixed left-3 top-3 bottom-3 flex w-60 flex-col rounded-2xl bg-white dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 shadow-sm dark:shadow-none">

        {{-- Logo --}}
        <div class="flex items-center px-5 py-4 border-b border-zinc-200 dark:border-zinc-800">
            <span class="text-lg font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">Better With 90</span>
        </div>

        {{-- Primary Navigation --}}
        <nav class="flex-1 overflow-y-auto px-3 py-3 space-y-0.5">

            @php
                $navItems = [
                    ['path' => '/dashboard', 'label' => 'Dashboard', 'icon' => 'layout-dashboard'],
                    ['path' => '/transactions', 'label' => 'Transactions', 'icon' => 'arrow-left-right'],
                    ['path' => '/budgets', 'label' => 'Budgets', 'icon' => 'wallet'],
                    ['path' => '/categories', 'label' => 'Categories', 'icon' => 'tags'],
                    ['path' => '/accounts', 'label' => 'Accounts', 'icon' => 'landmark'],
                    ['path' => '/summaries', 'label' => 'Summaries', 'icon' => 'file-text'],
                    ['path' => '/goals', 'label' => 'Goals', 'icon' => 'target'],
                    ['path' => '/chat', 'label' => 'Chat', 'icon' => 'message-square'],
                ];
            @endphp

            @foreach ($navItems as $item)
                <a href="{{ $item['path'] }}" wire:navigate
                    :class="path === '{{ $item['path'] }}'
                        ?
                        'bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100' :
                        'text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 hover:bg-zinc-50 dark:hover:bg-zinc-800/50'"
                    class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors duration-150">
                    <x-dynamic-component :component="'lucide-' . $item['icon']" class="w-5 h-5 flex-shrink-0" />
                    <span>{{ $item['label'] }}</span>
                </a>
            @endforeach

        </nav>

        {{-- Bottom Actions --}}
        <div class="px-3 py-3 border-t border-zinc-200 dark:border-zinc-800 space-y-0.5">

            {{-- Theme Toggle --}}
            <div x-data class="flex items-center gap-1 rounded-lg bg-zinc-100 dark:bg-zinc-800/50 p-1 mb-2">
                <button @click="$store.theme.set('light')"
                    :class="$store.theme.mode === 'light' ?
                        'bg-white dark:bg-zinc-700 shadow-sm text-zinc-900 dark:text-zinc-100' :
                        'text-zinc-400 dark:text-zinc-500 hover:text-zinc-600 dark:hover:text-zinc-300'"
                    class="flex-1 flex items-center justify-center gap-1.5 rounded-md px-2 py-1.5 text-xs font-medium transition-all">
                    <x-lucide-sun class="w-3.5 h-3.5" />
                </button>
                <button @click="$store.theme.set('system')"
                    :class="$store.theme.mode === 'system' ?
                        'bg-white dark:bg-zinc-700 shadow-sm text-zinc-900 dark:text-zinc-100' :
                        'text-zinc-400 dark:text-zinc-500 hover:text-zinc-600 dark:hover:text-zinc-300'"
                    class="flex-1 flex items-center justify-center gap-1.5 rounded-md px-2 py-1.5 text-xs font-medium transition-all">
                    <x-lucide-monitor class="w-3.5 h-3.5" />
                </button>
                <button @click="$store.theme.set('dark')"
                    :class="$store.theme.mode === 'dark' ?
                        'bg-white dark:bg-zinc-700 shadow-sm text-zinc-900 dark:text-zinc-100' :
                        'text-zinc-400 dark:text-zinc-500 hover:text-zinc-600 dark:hover:text-zinc-300'"
                    class="flex-1 flex items-center justify-center gap-1.5 rounded-md px-2 py-1.5 text-xs font-medium transition-all">
                    <x-lucide-moon class="w-3.5 h-3.5" />
                </button>
            </div>

            <a href="/profile" wire:navigate
                :class="path === '/profile'
                    ?
                    'bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100' :
                    'text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 hover:bg-zinc-50 dark:hover:bg-zinc-800/50'"
                class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors duration-150">
                <x-lucide-user class="w-5 h-5 flex-shrink-0" />
                <span>Profile</span>
            </a>

            <a href="/settings" wire:navigate
                :class="path === '/settings'
                    ?
                    'bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100' :
                    'text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 hover:bg-zinc-50 dark:hover:bg-zinc-800/50'"
                class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors duration-150">
                <x-lucide-settings class="w-5 h-5 flex-shrink-0" />
                <span>Settings</span>
            </a>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                    class="group flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-zinc-500 dark:text-zinc-400 transition-colors duration-150 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 hover:text-zinc-900 dark:hover:text-zinc-100">
                    <x-lucide-log-out
                        class="w-5 h-5 flex-shrink-0 text-zinc-400 dark:text-zinc-500 group-hover:text-zinc-600 dark:group-hover:text-zinc-300" />
                    <span>Sign Out</span>
                </button>
            </form>

        </div>

    </div>

    {{-- Spacer to offset floating sidebar --}}
    <div class="w-60 flex-shrink-0 ml-3"></div>
</div>
