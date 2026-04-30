<?php
use App\Jobs\BudgetCheckJob;
use App\Jobs\HealthCheckJob;
use App\Jobs\PaymentOptimizerJob;
use App\Jobs\PlaidSyncJob;
use App\Jobs\SummaryJob;
use App\Models\AppSetting;
use App\Models\PlaidConnection;
use Illuminate\Support\Facades\Schedule;

// Daily sync pipeline
Schedule::call(function () {
    $connections = PlaidConnection::where('status', 'active')->get();
    foreach ($connections as $connection) {
        PlaidSyncJob::dispatch($connection);
    }
})->cron((function () {
    try {
        return AppSetting::getValue('sync_schedule', '0 4 * * *');
    } catch (\Throwable) {
        return '0 4 * * *';
    }
})())
  ->name('plaid-sync')
  ->withoutOverlapping();

// AI analysis pipeline (runs 30 minutes after sync)
Schedule::call(function () {
    BudgetCheckJob::dispatch();
    HealthCheckJob::dispatch();
    PaymentOptimizerJob::dispatch();
    SummaryJob::dispatch('daily');
})->cron((function () {
    try {
        $syncCron = AppSetting::getValue('sync_schedule', '0 4 * * *');
        $parts = explode(' ', $syncCron);
        $minute = ((int) $parts[0] + 30) % 60;
        $hour = (int) $parts[1] + ((int) $parts[0] + 30 >= 60 ? 1 : 0);
        return "{$minute} {$hour} * * *";
    } catch (\Throwable) {
        return '30 4 * * *';
    }
})())
  ->name('ai-analysis-pipeline')
  ->withoutOverlapping();

// Weekly summary — every Monday at 6 AM
Schedule::call(fn () => SummaryJob::dispatch('weekly'))
    ->weeklyOn(1, '06:00')
    ->name('weekly-summary');

// Monthly summary — 1st of each month at 6 AM
Schedule::call(fn () => SummaryJob::dispatch('monthly'))
    ->monthlyOn(1, '06:00')
    ->name('monthly-summary');
