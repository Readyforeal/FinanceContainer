<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'StewardAI' }}</title>
    <script>
        (function() {
            const theme = localStorage.getItem('theme') || 'system';
            const isDark = theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', isDark);
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="text-zinc-900 dark:text-zinc-100 antialiased bg-gradient-to-br from-zinc-50 to-zinc-200 dark:from-zinc-900 dark:to-zinc-950 min-h-screen">
    <div class="flex min-h-screen">
        @persist('sidebar')
            <livewire:layout.sidebar />
        @endpersist
        <main class="flex-1 p-4 pb-24 lg:p-8 lg:pb-8 overflow-auto">
            {{ $slot }}
        </main>
    </div>

    @persist('mobile-dock')
        <livewire:layout.mobile-dock />
    @endpersist
</body>
</html>
