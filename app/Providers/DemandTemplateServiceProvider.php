<?php

namespace App\Providers;

use App\Api\GoogleDocs\GoogleDocsApi;
use App\Services\DemandTemplate\DemandTemplateService;
use App\Services\GoogleDocs\GoogleDocsFileService;
use Illuminate\Support\ServiceProvider;

class DemandTemplateServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(GoogleDocsFileService::class);
        $this->app->singleton(GoogleDocsApi::class);
        
        $this->app->singleton(DemandTemplateService::class, function ($app) {
            return new DemandTemplateService(
                $app->make(GoogleDocsFileService::class),
                $app->make(GoogleDocsApi::class)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
