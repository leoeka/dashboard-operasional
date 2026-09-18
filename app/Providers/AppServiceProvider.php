<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The billing engine decides WHICH reminders are due; this decides how
        // they travel. Binding it keeps Mail, SMTP and queue internals out of
        // BillingRenewalService entirely — and lets tests swap in a recorder
        // so the schedule can be exercised without a mail host.
        $this->app->bind(
            \App\Services\Billing\ReminderDispatcher::class,
            \App\Services\Billing\MailReminderDispatcher::class
        );

        // One clock for the whole request. It is stateless — every call reads
        // the current instant afresh, so Carbon's test clock still works — but
        // Invoice::isOverdue() now asks it per row, and rebuilding it for each
        // cell of a paginated table is waste for no gain.
        $this->app->singleton(\App\Services\Billing\BillingClock::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
