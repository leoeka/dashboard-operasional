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
        $this->app->bind(
            \App\Services\Finance\ReminderDispatcher::class,
            \App\Services\Finance\MailReminderDispatcher::class
        );

        $this->app->singleton(\App\Services\Finance\FinanceClock::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
