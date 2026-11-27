<?php

use App\Jobs\SyncCommerceCatalog;
use App\Jobs\VerifyCommercePayments;
use App\Models\CommerceSetting;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('campaigns:dispatch')->everyMinute();
Schedule::command('metrics:aggregate-daily')->dailyAt('23:55');
Schedule::command('automations:check-no-reply')->everyFifteenMinutes();
Schedule::call(function () {
    CommerceSetting::where('sync_enabled', true)->eachById(fn ($settings) => SyncCommerceCatalog::dispatch($settings->id));
})->name('commerce-catalog-sync')->everyFiveMinutes()->withoutOverlapping();
Schedule::call(function () {
    CommerceSetting::where('whatsapp_pay_enabled', true)->eachById(fn ($settings) => VerifyCommercePayments::dispatch($settings->company_id));
})->name('commerce-payment-verification')->everyFiveMinutes()->withoutOverlapping();
