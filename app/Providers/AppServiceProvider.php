<?php

namespace App\Providers;

use App\Services\Billing\MockStripePaymentService;
use App\Services\Billing\StripePaymentService;
use App\Services\Billing\StripePaymentServiceInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind Stripe payment service
        $this->app->singleton(StripePaymentServiceInterface::class, function ($app) {
            // Use mock service if STRIPE_MOCK_MODE is true or no API key is configured
            if (config('services.stripe.mock_mode') || !config('services.stripe.secret')) {
                return new MockStripePaymentService();
            }
            
            return new StripePaymentService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
