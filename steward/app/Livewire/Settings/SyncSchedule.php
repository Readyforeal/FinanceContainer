<?php

namespace App\Livewire\Settings;

use App\Models\AppSetting;
use Livewire\Component;

class SyncSchedule extends Component
{
    public int $hour = 2;
    public int $minute = 0;
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

    protected function rules(): array
    {
        return [
            'hour' => 'required|integer|min:0|max:23',
            'minute' => 'required|integer|min:0|max:59',
            'confidenceThreshold' => 'required|numeric|min:0.5|max:1.0',
        ];
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

    public function render()
    {
        return view('livewire.settings.sync-schedule');
    }
}
