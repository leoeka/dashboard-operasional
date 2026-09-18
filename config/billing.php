<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Billing timezone
    |--------------------------------------------------------------------------
    |
    | The zone the business actually operates in, kept deliberately separate
    | from app.timezone.
    |
    | Billing reasons in calendar days: "H-7" has to mean the same thing to us
    | and to the client, and the daily run fires at 09:00 local. But moving the
    | whole application off UTC would change how every created_at, queue
    | timestamp and log line is written, and make rows recorded before the
    | change mean something different from rows after it.
    |
    | So the application stays on UTC and only billing's own date arithmetic
    | uses this. See App\Services\Billing\BillingClock.
    |
    */

    'timezone' => env('BILLING_TIMEZONE', 'Asia/Makassar'),

];
