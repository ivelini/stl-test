<?php

namespace App\Providers;

use App\Http\Resources\HoldResource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Контракт API — плоский JSON без обёртки "data" (как у /slots/availability).
        HoldResource::withoutWrapping();
    }
}
