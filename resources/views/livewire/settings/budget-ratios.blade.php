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
        <flux:heading size="lg">Budget Ratios</flux:heading>
        <flux:icon.chart-pie class="size-5 text-zinc-400 dark:text-zinc-500" />
    </div>

    <flux:card class="p-5">
        <flux:text size="sm" class="mb-5">
            Set your target allocation percentages. They must add up to 100%.
        </flux:text>

        <div class="grid grid-cols-3 gap-4 mb-5">
            <flux:input
                wire:model.live="needs"
                type="number"
                label="Needs (%)"
                min="0"
                max="100"
            />

            <flux:input
                wire:model.live="wants"
                type="number"
                label="Wants (%)"
                min="0"
                max="100"
            />

            <flux:input
                wire:model.live="savings"
                type="number"
                label="Savings (%)"
                min="0"
                max="100"
            />
        </div>

        {{-- Live sum display --}}
        <div class="flex items-center gap-2 mb-4">
            <flux:text size="sm">Total:</flux:text>
            <span class="text-sm font-semibold {{ ($needs + $wants + $savings) === 100 ? 'text-green-500' : 'text-red-500' }}">
                {{ $needs + $wants + $savings }}%
            </span>
            @if (($needs + $wants + $savings) === 100)
                <flux:icon.circle-check class="size-4 text-green-500" />
            @else
                <flux:icon.circle-alert class="size-4 text-red-500" />
            @endif
        </div>

        @error('ratios')
            <flux:text size="sm" class="text-red-500 mb-3">{{ $message }}</flux:text>
        @enderror

        <div class="flex items-center gap-3">
            <flux:button wire:click="save" variant="primary">
                Save Ratios
            </flux:button>

            @if ($saved)
                <span class="flex items-center gap-1.5 text-sm text-green-500">
                    <flux:icon.check class="size-4" />
                    Saved
                </span>
            @endif
        </div>
    </flux:card>
</div>
