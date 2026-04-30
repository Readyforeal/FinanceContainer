<?php
use App\Jobs\PlaidSyncJob;
use App\Models\AppSetting;
use App\Models\PlaidConnection;
use Illuminate\Support\Facades\Schedule;

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
