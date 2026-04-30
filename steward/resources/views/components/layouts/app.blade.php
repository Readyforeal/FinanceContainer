<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'StewardAI' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-900 text-gray-100 antialiased">
    <div class="flex min-h-screen">
        <livewire:layout.sidebar />
        <main class="flex-1 p-8 overflow-auto">
            {{ $slot }}
        </main>
    </div>
</body>
</html>
