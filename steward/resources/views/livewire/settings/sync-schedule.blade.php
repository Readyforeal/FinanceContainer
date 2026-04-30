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
    <h2 class="text-lg font-semibold text-gray-100 mb-4">Sync Schedule</h2>

    <form wire:submit="save" class="space-y-6">
        {{-- Time of Day --}}
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-5">
            <div class="flex items-center gap-2 mb-4">
                <x-lucide-clock class="w-5 h-5 text-indigo-400" />
                <h3 class="text-sm font-semibold text-gray-300">Daily Sync Time</h3>
            </div>
            <p class="text-xs text-gray-500 mb-4">Transactions will be automatically synced at this time each day.</p>

            <div class="flex items-center gap-3">
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Hour</label>
                    <select
                        wire:model="hour"
                        class="bg-gray-700 border border-gray-600 text-gray-100 text-sm rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"
                    >
                        @for ($h = 0; $h < 24; $h++)
                            <option value="{{ $h }}">{{ str_pad($h, 2, '0', STR_PAD_LEFT) }}</option>
                        @endfor
                    </select>
                    @error('hour') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <span class="text-gray-400 mt-5">:</span>

                <div>
                    <label class="block text-xs text-gray-400 mb-1">Minute</label>
                    <select
                        wire:model="minute"
                        class="bg-gray-700 border border-gray-600 text-gray-100 text-sm rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"
                    >
                        @foreach ([0, 15, 30, 45] as $m)
                            <option value="{{ $m }}">{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}</option>
                        @endforeach
                    </select>
                    @error('minute') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="mt-5 text-sm text-gray-400">
                    ({{ str_pad($hour, 2, '0', STR_PAD_LEFT) }}:{{ str_pad($minute, 2, '0', STR_PAD_LEFT) }} UTC)
                </div>
            </div>
        </div>

        {{-- Confidence Threshold --}}
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-5">
            <div class="flex items-center gap-2 mb-4">
                <x-lucide-sliders-horizontal class="w-5 h-5 text-indigo-400" />
                <h3 class="text-sm font-semibold text-gray-300">Categorization Confidence Threshold</h3>
            </div>
            <p class="text-xs text-gray-500 mb-4">
                Transactions with confidence below this threshold will be flagged for manual review.
            </p>

            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-gray-400">50%</span>
                    <span class="text-sm font-semibold text-indigo-400">
                        {{ number_format($confidenceThreshold * 100, 0) }}%
                    </span>
                    <span class="text-xs text-gray-400">100%</span>
                </div>

                <input
                    wire:model.live="confidenceThreshold"
                    type="range"
                    min="0.5"
                    max="1.0"
                    step="0.05"
                    class="w-full h-2 bg-gray-700 rounded-lg appearance-none cursor-pointer accent-indigo-500"
                />

                @error('confidenceThreshold') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex items-center gap-4">
            <button
                type="submit"
                class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-6 py-2 rounded-lg transition-colors"
            >
                Save Settings
            </button>

            @if ($saved)
                <span class="flex items-center gap-1 text-sm text-green-400">
                    <x-lucide-check-circle class="w-4 h-4" />
                    Saved!
                </span>
            @endif
        </div>
    </form>
</div>
