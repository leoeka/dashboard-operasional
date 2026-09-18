<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// One scheduled billing entry, on purpose. It covers both recurring renewals
// and the legacy project-invoice reminders; scheduling those separately would
// let two runs reach the same invoice and send a client two reminders for it.
//
// The timezone is stated explicitly so 09:00 means 09:00 where the business
// actually is. The application itself stays on UTC — see config/billing.php.
Schedule::command('billing:process-renewals')
    ->dailyAt('09:00')
    ->timezone(config('billing.timezone'));
