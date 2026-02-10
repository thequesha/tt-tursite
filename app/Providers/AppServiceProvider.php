<?php

namespace App\Providers;

use App\Repositories\Contracts\SettingRepositoryInterface;
use App\Repositories\SettingRepository;
use App\Services\Contracts\ReviewServiceInterface;
use App\Services\YandexReviewService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ReviewServiceInterface::class, YandexReviewService::class);
        $this->app->bind(SettingRepositoryInterface::class, SettingRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
