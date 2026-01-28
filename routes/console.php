<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('payments:generate')->monthlyOn(1, '00:00');
Schedule::command('students:check-payment-status')->daily();
Schedule::command('guests:check-payment-status')->daily();
