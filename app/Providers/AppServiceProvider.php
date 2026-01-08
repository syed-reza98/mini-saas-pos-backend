<?php

namespace App\Providers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Policies\CustomerPolicy;
use App\Policies\OrderPolicy;
use App\Policies\ProductPolicy;
use App\Policies\ReportPolicy;
use App\Services\CurrentTenant;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register CurrentTenant as a singleton
        $this->app->singleton(CurrentTenant::class, function () {
            return new CurrentTenant;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register policies
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);

        // Register report permissions as gates
        Gate::define('view-daily-sales', [ReportPolicy::class, 'viewDailySales']);
        Gate::define('view-top-selling-products', [ReportPolicy::class, 'viewTopSellingProducts']);
        Gate::define('view-low-stock', [ReportPolicy::class, 'viewLowStock']);
        Gate::define('view-reports', [ReportPolicy::class, 'viewAny']);
    }
}
