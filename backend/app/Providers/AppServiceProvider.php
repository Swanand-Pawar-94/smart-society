<?php

namespace App\Providers;

use App\Contracts\Payments\PaymentGateway;
use App\Services\Payments\UnconfiguredPaymentGateway;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind a real, signature-verifying gateway implementation only after a
        // provider and its production credentials have been selected.
        $this->app->singleton(PaymentGateway::class, UnconfiguredPaymentGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register model observers
        \App\Models\MaintenanceBill::observe(\App\Observers\MaintenanceBillObserver::class);
        \App\Models\MaintenancePayment::observe(\App\Observers\MaintenancePaymentObserver::class);
    }
}
