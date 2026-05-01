<?php

use App\Models\AppSetting;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component {
    #[Validate('required|integer|min:0|max:23')]
    public int $hour = 2;

    #[Validate('required|integer|min:0|max:59')]
    public int $minute = 0;

    #[Validate('required|numeric|min:0.5|max:1.0')]
    public float $confidenceThreshold = 0.75;

    public bool $saved = false;

    public function mount(): void
    {
        $schedule = AppSetting::getValue('sync_schedule', ['hour' => 2, 'minute' => 0]);
        $this->hour = (int) ($schedule['hour'] ?? 2);
        $this->minute = (int) ($schedule['minute'] ?? 0);

        $this->confidenceThreshold = (float) AppSetting::getValue(
            'categorization_confidence_threshold',
            0.75
        );
    }

    public function save(): void
    {
        $this->validate();

        AppSetting::setValue('sync_schedule', [
            'hour' => $this->hour,
            'minute' => $this->minute,
        ]);

        AppSetting::setValue('categorization_confidence_threshold', $this->confidenceThreshold);

        $this->saved = true;
    }
};
?>

<div>
    <flux:heading size="lg" class="mb-4">Sync Schedule</flux:heading>

    <form wire:submit="save" class="space-y-6">
        {{-- Time of Day --}}
        <flux:card class="p-5">
            <div class="flex items-center gap-2 mb-4">
                <flux:icon.clock class="size-5 text-indigo-400" />
                <flux:heading size="sm">Daily Sync Time</flux:heading>
            </div>
            <flux:text size="xs" class="mb-4">Transactions will be automatically synced at this time each day.</flux:text>

            <div class="flex items-center gap-3">
                <div>
                    <flux:select wire:model="hour" label="Hour">
                        @for ($h = 0; $h < 24; $h++)
                            <flux:select.option value="{{ $h }}">{{ str_pad($h, 2, '0', STR_PAD_LEFT) }}</flux:select.option>
                        @endfor
                    </flux:select>
                    @error('hour') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
                </div>

                <span class="text-zinc-500 dark:text-zinc-400 mt-5">:</span>

                <div>
                    <flux:select wire:model="minute" label="Minute">
                        @foreach ([0, 15, 30, 45] as $m)
                            <flux:select.option value="{{ $m }}">{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}</flux:select.option>
                        @endfor
                    </flux:select>
                    @error('minute') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
                </div>

                <flux:text size="sm" class="mt-5">
                    ({{ str_pad($hour, 2, '0', STR_PAD_LEFT) }}:{{ str_pad($minute, 2, '0', STR_PAD_LEFT) }} UTC)
                </flux:text>
            </div>
        </flux:card>

        {{-- Confidence Threshold --}}
        <flux:card class="p-5">
            <div class="flex items-center gap-2 mb-4">
                <flux:icon.sliders-horizontal class="size-5 text-indigo-400" />
                <flux:heading size="sm">Categorization Confidence Threshold</flux:heading>
            </div>
            <flux:text size="xs" class="mb-4">
                Transactions with confidence below this threshold will be flagged for manual review.
            </flux:text>

            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <flux:text size="xs">50%</flux:text>
                    <span class="text-sm font-semibold text-indigo-400">
                        {{ number_format($confidenceThreshold * 100, 0) }}%
                    </span>
                    <flux:text size="xs">100%</flux:text>
                </div>

                <input
                    wire:model.live="confidenceThreshold"
                    type="range"
                    min="0.5"
                    max="1.0"
                    step="0.05"
                    class="w-full h-2 bg-zinc-200 dark:bg-zinc-700 rounded-lg appearance-none cursor-pointer accent-indigo-500"
                />

                @error('confidenceThreshold') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
            </div>
        </flux:card>

        <div class="flex items-center gap-4">
            <flux:button type="submit" variant="primary">
                Save Settings
            </flux:button>

            @if ($saved)
                <span class="flex items-center gap-1 text-sm text-green-400">
                    <flux:icon.circle-check class="size-4" />
                    Saved!
                </span>
            @endif
        </div>
    </form>
</div>
