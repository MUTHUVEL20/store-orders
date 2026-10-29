<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
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
        // Fail loudly (outside production) if any code path lazy-loads a relation,
        // so N+1 queries are caught by the tests instead of in production.
        Model::preventLazyLoading(! $this->app->isProduction());
    }
}
