<div>
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-gray-100">Income Sources</h2>
        @if ($sources->isNotEmpty())
            <div class="text-sm text-gray-400">
                Total Monthly:
                <span class="text-green-400 font-semibold ml-1">${{ number_format($totalMonthly, 2) }}</span>
            </div>
        @endif
    </div>

    {{-- Existing sources --}}
    @if ($sources->isNotEmpty())
        <div class="space-y-3 mb-6">
            @foreach ($sources as $source)
                <div class="bg-gray-800 rounded-xl border border-gray-700 p-4 flex items-center justify-between">
                    <div>
                        <p class="font-medium text-gray-100">{{ $source->name }}</p>
                        <div class="flex items-center gap-3 mt-1 text-sm text-gray-400">
                            <span>${{ number_format($source->amount, 2) }} / {{ $source->frequency }}</span>
                            <span class="text-gray-600">&bull;</span>
                            <span class="text-green-400">${{ number_format($source->monthlyAmount(), 2) }}/mo</span>
                            @if ($source->next_pay_date)
                                <span class="text-gray-600">&bull;</span>
                                <span>Next: {{ $source->next_pay_date->format('M j, Y') }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        @if ($confirmDeleteId === $source->id)
                            <span class="text-sm text-red-400 mr-2">Confirm delete?</span>
                            <button
                                wire:click="delete({{ $source->id }})"
                                class="text-xs bg-red-700 hover:bg-red-600 text-white px-3 py-1 rounded"
                            >
                                Yes, Delete
                            </button>
                            <button
                                wire:click="cancelDelete"
                                class="text-xs bg-gray-700 hover:bg-gray-600 text-gray-300 px-3 py-1 rounded"
                            >
                                Cancel
                            </button>
                        @else
                            <button
                                wire:click="edit({{ $source->id }})"
                                class="p-1.5 text-gray-400 hover:text-indigo-400 transition-colors"
                                title="Edit"
                            >
                                <x-lucide-pencil class="w-4 h-4" />
                            </button>
                            <button
                                wire:click="confirmDelete({{ $source->id }})"
                                class="p-1.5 text-gray-400 hover:text-red-400 transition-colors"
                                title="Delete"
                            >
                                <x-lucide-trash-2 class="w-4 h-4" />
                            </button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Total summary --}}
        <div class="bg-gray-800/50 rounded-xl border border-gray-700 p-4 mb-6 text-center">
            <p class="text-sm text-gray-400">Total Monthly Income</p>
            <p class="text-3xl font-bold text-green-400 mt-1">${{ number_format($totalMonthly, 2) }}</p>
        </div>
    @else
        <div class="text-center py-8 text-gray-500 mb-6">
            <x-lucide-wallet class="w-10 h-10 mx-auto mb-3 text-gray-600" />
            <p>No income sources added yet.</p>
        </div>
    @endif

    {{-- Add/Edit form --}}
    <div class="bg-gray-800 rounded-xl border border-gray-700 p-5">
        <h3 class="text-sm font-semibold text-gray-300 mb-4">
            {{ $editingId ? 'Edit Income Source' : 'Add Income Source' }}
        </h3>

        <form wire:submit="save" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Name</label>
                    <input
                        wire:model="name"
                        type="text"
                        placeholder="e.g. Main Job"
                        class="w-full bg-gray-700 border border-gray-600 text-gray-100 text-sm rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500 placeholder-gray-500"
                    />
                    @error('name') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs text-gray-400 mb-1">Amount</label>
                    <input
                        wire:model="amount"
                        type="number"
                        step="0.01"
                        min="0"
                        placeholder="0.00"
                        class="w-full bg-gray-700 border border-gray-600 text-gray-100 text-sm rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"
                    />
                    @error('amount') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs text-gray-400 mb-1">Frequency</label>
                    <select
                        wire:model="frequency"
                        class="w-full bg-gray-700 border border-gray-600 text-gray-100 text-sm rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"
                    >
                        <option value="weekly">Weekly</option>
                        <option value="biweekly">Biweekly</option>
                        <option value="monthly">Monthly</option>
                    </select>
                    @error('frequency') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs text-gray-400 mb-1">Next Pay Date</label>
                    <input
                        wire:model="nextPayDate"
                        type="date"
                        class="w-full bg-gray-700 border border-gray-600 text-gray-100 text-sm rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"
                    />
                    @error('nextPayDate') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button
                    type="submit"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors"
                >
                    {{ $editingId ? 'Update Source' : 'Add Source' }}
                </button>

                @if ($editingId)
                    <button
                        type="button"
                        wire:click="cancelEdit"
                        class="bg-gray-700 hover:bg-gray-600 text-gray-300 text-sm px-4 py-2 rounded-lg transition-colors"
                    >
                        Cancel
                    </button>
                @endif
            </div>
        </form>
    </div>
</div>
