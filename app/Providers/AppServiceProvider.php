<?php

namespace App\Providers;
use Illuminate\Pagination\Paginator;

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
   public function boot()
{
    Paginator::useBootstrap();
    Paginator::defaultSimpleView('pagination::simple-default');
    // Или для полной пагинации с текстом
    \Illuminate\Pagination\AbstractPaginator::defaultSimpleView('pagination::simple-bootstrap-5');
}

    
}
