<?php

namespace App\Providers;

use App\Api\GoogleDocs\GoogleDocsApi;
use App\Services\GoogleDocs\GoogleDocsFileService;
use Illuminate\Support\ServiceProvider;

class GoogleDocsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(GoogleDocsFileService::class);
        $this->app->singleton(GoogleDocsApi::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
