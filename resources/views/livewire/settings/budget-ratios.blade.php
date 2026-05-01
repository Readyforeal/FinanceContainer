<?php

use App\Models\AppSetting;
use Livewire\Component;

new class extends Component {
    public int $needs = 50;
    public int $wants = 30;
    public int $savings = 20;
    public bool $saved = false;

    public function mount(): void
    {
        $ratios = AppSetting::getValue('budget_ratios', ['needs' => 50, 'wants' => 30, 'savings' => 20]);
        $this->needs = (int) ($ratios['needs'] ?? 50);
        $this->wants = (int) ($ratios['wants'] ?? 30);
        $this->savings = (int) ($ratios['savings'] ?? 20);
    }

    public function updatedNeeds(): void
    {
        $this->saved = false;
    }

    public function updatedWants(): void
    {
        $this->saved = false;
    }

    public function updatedSavings(): void
    {
        $this->saved = false;
    }

    public function save(): void
    {
        if ($this->needs + $this->wants + $this->savings !== 100) {
            $this->addError('ratios', 'Needs, Wants, and Savings must sum to exactly 100%.');
            return;
        }

        $this->resetValidation('ratios');

        AppSetting::setValue('budget_ratios', [
            'needs' => $this->needs,
            'wants' => $this->wants,
            'savings' => $this->savings,
        ]);

        $this->saved = true;
    }
};
?>

<div>
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Budget Ratios</h2>
        <x-lucide-pie-chart class="w-5 h-5 text-zinc-400 dark:text-zinc-500" />
    </div>

    <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5">
        <p class="text-sm text-zinc-500 dark:text-zinc-400 mb-5">
            Set your target allocation percentages. They must add up to 100%.
        </p>

        <div class="grid grid-cols-3 gap-4 mb-5">
            <div>
                <label class="block text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-1">Needs (%)</label>
                <input
                    wire:model.live="needs"
                    type="number"
                    min="0"
                    max="100"
                    class="w-full bg-white dark:bg-zinc-800 border border-zinc-300 dark:border-zinc-700 text-zinc-900 dark:text-zinc-100 text-sm rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"
                />
            </div>

            <div>
                <label class="block text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-1">Wants (%)</label>
                <input
                    wire:model.live="wants"
                    type="number"
                    min="0"
                    max="100"
                    class="w-full bg-white dark:bg-zinc-800 border border-zinc-300 dark:border-zinc-700 text-zinc-900 dark:text-zinc-100 text-sm rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"
                />
            </div>

            <div>
                <label class="block text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-1">Savings (%)</label>
                <input
                    wire:model.live="savings"
                    type="number"
                    min="0"
                    max="100"
                    class="w-full bg-white dark:bg-zinc-800 border border-zinc-300 dark:border-zinc-700 text-zinc-900 dark:text-zinc-100 text-sm rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"
                />
            </div>
        </div>

        {{-- Live sum display --}}
        <div class="flex items-center gap-2 mb-4">
            <span class="text-sm text-zinc-500 dark:text-zinc-400">Total:</span>
            <span class="text-sm font-semibold {{ ($needs + $wants + $savings) === 100 ? 'text-green-500' : 'text-red-500' }}">
                {{ $needs + $wants + $savings }}%
            </span>
            @if (($needs + $wants + $savings) === 100)
                <x-lucide-check-circle class="w-4 h-4 text-green-500" />
            @else
                <x-lucide-alert-circle class="w-4 h-4 text-red-500" />
            @endif
        </div>

        @error('ratios')
            <p class="text-red-400 text-xs mb-3">{{ $message }}</p>
        @enderror

        <div class="flex items-center gap-3">
            <button
                wire:click="save"
                class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors"
            >
                Save Ratios
            </button>

            @if ($saved)
                <span class="flex items-center gap-1.5 text-sm text-green-500">
                    <x-lucide-check class="w-4 h-4" />
                    Saved
                </span>
            @endif
        </div>
    </div>
</div>
