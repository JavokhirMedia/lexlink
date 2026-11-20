<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// auth-team: daily licence re-check against the advocate registry
Schedule::command('lexlink:recheck-licenses')->dailyAt('03:00')->withoutOverlapping();

// requests: purge full response payloads + PDFs after response_retention_days (summary + audit remain)
Schedule::command('lexlink:purge-responses')->hourly()->withoutOverlapping();
