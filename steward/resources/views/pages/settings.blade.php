<x-layouts.app title="Settings">
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-100">Settings</h1>
        <p class="text-gray-400 mt-1">Configure your StewardAI preferences.</p>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">
        <div>
            <livewire:settings.income-sources />
        </div>

        <div>
            <livewire:settings.sync-schedule />
        </div>
    </div>
</x-layouts.app>
