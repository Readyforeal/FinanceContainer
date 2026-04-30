<div class="flex flex-shrink-0">
<div class="fixed left-0 top-0 flex h-screen w-64 flex-col bg-gray-950 border-r border-gray-800">

    {{-- Logo --}}
    <div class="flex items-center px-6 py-5 border-b border-gray-800">
        <span class="text-lg font-semibold tracking-tight text-blue-400">StewardAI</span>
    </div>

    {{-- Primary Navigation --}}
    <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-0.5">

        @php
            $navItems = [
                ['route' => 'dashboard',     'label' => 'Dashboard',     'icon' => 'layout-dashboard'],
                ['route' => 'transactions',  'label' => 'Transactions',  'icon' => 'arrow-left-right'],
                ['route' => 'budgets',       'label' => 'Budgets',       'icon' => 'wallet'],
                ['route' => 'categories',    'label' => 'Categories',    'icon' => 'tags'],
                ['route' => 'accounts',      'label' => 'Accounts',      'icon' => 'landmark'],
                ['route' => 'summaries',     'label' => 'Summaries',     'icon' => 'file-text'],
                ['route' => 'chat',          'label' => 'Chat',          'icon' => 'message-square'],
            ];
        @endphp

        @foreach ($navItems as $item)
            @php
                $isActive = request()->routeIs($item['route']);
                $baseClasses = 'group flex items-center gap-3 rounded-md px-3 py-2.5 text-sm font-medium transition-colors duration-150';
                $activeClasses = 'bg-gray-800/50 text-white border-l-2 border-blue-400 pl-[10px]';
                $inactiveClasses = 'text-gray-400 hover:text-white hover:bg-gray-800/30 border-l-2 border-transparent pl-[10px]';
                $linkClasses = $baseClasses . ' ' . ($isActive ? $activeClasses : $inactiveClasses);
            @endphp

            <a href="{{ route($item['route']) }}" class="{{ $linkClasses }}">
                <x-dynamic-component
                    :component="'lucide-' . $item['icon']"
                    class="w-5 h-5 flex-shrink-0"
                />
                <span>{{ $item['label'] }}</span>
            </a>
        @endforeach

    </nav>

    {{-- Bottom Actions --}}
    <div class="px-3 py-4 border-t border-gray-800 space-y-0.5">

        @php
            $settingsActive = request()->routeIs('settings');
            $settingsClasses = 'group flex items-center gap-3 rounded-md px-3 py-2.5 text-sm font-medium transition-colors duration-150 border-l-2 pl-[10px] '
                . ($settingsActive
                    ? 'bg-gray-800/50 text-white border-blue-400'
                    : 'text-gray-400 hover:text-white hover:bg-gray-800/30 border-transparent');
        @endphp

        <a href="{{ route('settings') }}" class="{{ $settingsClasses }}">
            <x-lucide-settings class="w-5 h-5 flex-shrink-0" />
            <span>Settings</span>
        </a>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button
                type="submit"
                class="group flex w-full items-center gap-3 rounded-md border-l-2 border-transparent px-3 py-2.5 pl-[10px] text-sm font-medium text-gray-400 transition-colors duration-150 hover:bg-gray-800/30 hover:text-white"
            >
                <x-lucide-log-out class="w-5 h-5 flex-shrink-0" />
                <span>Sign Out</span>
            </button>
        </form>

    </div>

</div>

{{-- Spacer to offset fixed sidebar --}}
<div class="w-64 flex-shrink-0"></div>
</div>
