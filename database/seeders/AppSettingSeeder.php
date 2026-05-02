<?php
namespace Database\Seeders;

use App\Models\AppSetting;
use Illuminate\Database\Seeder;

class AppSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            'sync_schedule' => '0 4 * * *',
            'categorization_confidence_threshold' => 0.9,
            'budget_ratios' => ['needs' => 50, 'wants' => 30, 'savings' => 20],
            'email_recipients' => [],
            'ollama_model' => 'llama3.1:70b-instruct-q4_K_M',
        ];

        foreach ($settings as $key => $value) {
            AppSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
