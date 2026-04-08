<?php

namespace App\Providers;

use App\Repositories\Contracts\FormFieldMappingRepositoryInterface;
use App\Repositories\Contracts\WebhookLogRepositoryInterface;
use App\Repositories\FormFieldMappingRepository;
use App\Repositories\WebhookLogRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(WebhookLogRepositoryInterface::class, WebhookLogRepository::class);
        $this->app->bind(FormFieldMappingRepositoryInterface::class, FormFieldMappingRepository::class);
    }

    public function boot(): void
    {
        //
    }
}
