<div>
    @if ($connections->isEmpty())
        <div class="text-center py-16">
            <x-lucide-banknote class="w-12 h-12 text-gray-600 mx-auto mb-4" />
            <h2 class="text-xl font-semibold text-gray-300 mb-2">No Bank Accounts Connected</h2>
            <p class="text-gray-500 mb-6">Connect your bank account to get started.</p>
            <livewire:plaid.plaid-link />
        </div>
    @else
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-lg font-semibold text-gray-100">Connected Accounts</h2>
            <livewire:plaid.plaid-link />
        </div>

        @foreach ($connections as $connection)
            <div class="mb-6">
                <div class="flex items-center gap-3 mb-3">
                    <h3 class="text-base font-medium text-gray-200">{{ $connection->institution_name }}</h3>
                    @php
                        $statusClasses = match ($connection->status->value) {
                            'active' => 'bg-green-900/50 text-green-400 border border-green-700',
                            'error' => 'bg-red-900/50 text-red-400 border border-red-700',
                            'needs_reauth' => 'bg-yellow-900/50 text-yellow-400 border border-yellow-700',
                            default => 'bg-gray-700 text-gray-400',
                        };
                    @endphp
                    <span class="text-xs px-2 py-0.5 rounded-full {{ $statusClasses }}">
                        {{ str_replace('_', ' ', $connection->status->value) }}
                    </span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach ($connection->accounts as $account)
                        <div class="bg-gray-800 rounded-xl p-5 border border-gray-700">
                            <div class="flex items-start justify-between mb-4">
                                <div>
                                    <p class="text-sm text-gray-400">{{ $account->name }}</p>
                                    <p class="text-2xl font-bold text-gray-100 mt-1">
                                        ${{ number_format($account->current_balance, 2) }}
                                    </p>
                                </div>
                                <div class="p-2 bg-gray-700 rounded-lg">
                                    @if ($account->type->value === 'savings')
                                        <x-lucide-piggy-bank class="w-5 h-5 text-indigo-400" />
                                    @else
                                        <x-lucide-credit-card class="w-5 h-5 text-indigo-400" />
                                    @endif
                                </div>
                            </div>

                            <div class="text-xs text-gray-500 space-y-1">
                                <div class="flex justify-between">
                                    <span>Type</span>
                                    <span class="capitalize text-gray-400">{{ $account->type->value }}</span>
                                </div>
                                @if ($account->available_balance !== null)
                                    <div class="flex justify-between">
                                        <span>Available</span>
                                        <span class="text-gray-400">${{ number_format($account->available_balance, 2) }}</span>
                                    </div>
                                @endif
                                @if ($account->last_synced_at)
                                    <div class="flex justify-between">
                                        <span>Last synced</span>
                                        <span class="text-gray-400">{{ $account->last_synced_at->diffForHumans() }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    @endif
</div>
