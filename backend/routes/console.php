<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('campaigns:dispatch')->everyMinute();
Schedule::command('metrics:aggregate-daily')->dailyAt('23:55');
Schedule::command('automations:check-no-reply')->everyFifteenMinutes();
