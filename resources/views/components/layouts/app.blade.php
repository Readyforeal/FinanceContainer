<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'StewardAI' }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
</head>

<body class="min-h-screen bg-gradient-to-br from-white to-zinc-100 dark:from-zinc-900 dark:to-zinc-950">
    <flux:sidebar sticky class="max-lg:hidden! bg-transparent! border-r-0!">
        <flux:sidebar.header>
            <flux:sidebar.brand name="Better With 90" href="/dashboard" wire:navigate>
                <x-slot name="logo">
                    <flux:icon.hand-coins class="size-6" />
                </x-slot>
            </flux:sidebar.brand>
        </flux:sidebar.header>

        <flux:sidebar.nav>
            <flux:sidebar.item icon="layout-dashboard" href="/dashboard" wire:navigate>Dashboard</flux:sidebar.item>
            <flux:sidebar.item icon="arrow-left-right" href="/transactions" wire:navigate>Transactions
            </flux:sidebar.item>
            <flux:sidebar.item icon="wallet" href="/budgets" wire:navigate>Budgets</flux:sidebar.item>
            <flux:sidebar.item icon="tags" href="/categories" wire:navigate>Categories</flux:sidebar.item>
            <flux:sidebar.item icon="landmark" href="/accounts" wire:navigate>Accounts</flux:sidebar.item>
            <flux:sidebar.item icon="file-text" href="/summaries" wire:navigate>Summaries</flux:sidebar.item>
            <flux:sidebar.item icon="target" href="/goals" wire:navigate>Goals</flux:sidebar.item>
            <flux:sidebar.item icon="message-square" href="/chat" wire:navigate>Chat</flux:sidebar.item>
        </flux:sidebar.nav>

        <flux:spacer />

        <flux:sidebar.nav>
            <flux:sidebar.item icon="user" href="/profile" wire:navigate>Profile</flux:sidebar.item>
            <flux:sidebar.item icon="settings" href="/settings" wire:navigate>Settings</flux:sidebar.item>
        </flux:sidebar.nav>

        <div class="py-2">
            <flux:dropdown x-data align="start" position="top">
                <flux:button variant="subtle" size="sm" class="w-full justify-start" icon="sun">Appearance
                </flux:button>
                <flux:menu>
                    <flux:menu.item icon="sun" x-on:click="$flux.appearance = 'light'">Light</flux:menu.item>
                    <flux:menu.item icon="moon" x-on:click="$flux.appearance = 'dark'">Dark</flux:menu.item>
                    <flux:menu.item icon="monitor" x-on:click="$flux.appearance = 'system'">System</flux:menu.item>
                </flux:menu>
            </flux:dropdown>
        </div>

        <flux:separator />

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <flux:sidebar.item icon="log-out" type="submit">Sign Out</flux:sidebar.item>
        </form>
    </flux:sidebar>

    <flux:header
        class="lg:hidden bg-gradient-to-b py-3 dark:from-zinc-950/80 from-zinc-200/80 to-transparent fixed top-0 w-full">
        <flux:icon.hand-coins class="size-5 mr-2" />
        <flux:heading size="sm ml-2">Better With 90</flux:heading>
        <flux:spacer />
        <flux:dropdown x-data align="end">
            <flux:button variant="filled" square icon="sun" aria-label="Appearance" />
            <flux:menu>
                <flux:menu.item icon="sun" x-on:click="$flux.appearance = 'light'">Light</flux:menu.item>
                <flux:menu.item icon="moon" x-on:click="$flux.appearance = 'dark'">Dark</flux:menu.item>
                <flux:menu.item icon="monitor" x-on:click="$flux.appearance = 'system'">System</flux:menu.item>
            </flux:menu>
        </flux:dropdown>
    </flux:header>

    <flux:main class="pb-24 lg:pb-0 lg:pt-1.5 pt-16 lg:pr-1.5 lg:pb-1.5 lg:pl-0">
        <div
            class="lg:bg-white lg:dark:bg-zinc-800 lg:rounded-2xl lg:border lg:border-zinc-200 lg:dark:border-zinc-700 lg:h-[calc(100vh-0.75rem)] lg:overflow-y-auto lg:p-8 lg:shadow-sm">
            {{ $slot }}
        </div>
    </flux:main>

    <livewire:layout.mobile-dock />

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('dockAction', {
                icon: null
            });
        });
    </script>
    @fluxScripts
</body>

</html>
