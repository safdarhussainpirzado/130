<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Add next month's audit_log partition on the 25th of each month at 02:00
// Ensures partitions are ready before the month begins (no runtime ALTER needed)
Schedule::command('audit:add-partition')->monthlyOn(25, '02:00');

// Process analytics outbox continuously every minute to update call_hourly_rollups
Schedule::command('analytics:process-outbox --limit=1000')->everyMinute()->withoutOverlapping();

// Reconcile and guarantee zero drift between operational DB and rollup analytics nightly at 03:00
Schedule::command('analytics:reconcile --days=7 --heal')->dailyAt('03:00');

// Hourly tamper/fork detection over the most recent audit records; failures surface in the app log (see command).
Schedule::command('audit:verify-integrity --limit=200')->hourly()->withoutOverlapping();
