<?php

namespace App\Providers;

use App\Policies\CateringCultureMealPolicy;
use Illuminate\Support\Facades\Gate;
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
        Gate::define('viewCateringCultureMeal', [CateringCultureMealPolicy::class, 'viewAny']);
        Gate::define('createCateringCultureMeal', [CateringCultureMealPolicy::class, 'create']);
        Gate::define('updateCateringCultureMeal', [CateringCultureMealPolicy::class, 'update']);
        Gate::define('deleteCateringCultureMeal', [CateringCultureMealPolicy::class, 'delete']);
    }
}
