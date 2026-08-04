<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductLocation;
use App\Models\StockTransaction;
use App\Policies\CategoryPolicy;
use App\Policies\ProductLocationPolicy;
use App\Policies\ProductPolicy;
use App\Policies\StockTransactionPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class GateProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Gate::policy(Category::class, CategoryPolicy::class);
        // Gate::policy(Product::class, ProductPolicy::class);
        // Gate::policy(ProductLocation::class, ProductLocationPolicy::class);
        // Gate::policy(StockTransaction::class, StockTransactionPolicy::class);

        Gate::before(function ($user, $ability) {
            $permissions = $user->role->permissions ?? [];

            // Kalau dia Superadmin (*), kasih ijin apapun tanpa tapi
            if (in_array('*', $permissions)) {
                return true;
            }

            // Kalau dia punya permission yang diminta, kasih ijin
            if (in_array($ability, $permissions)) {
                return true;
            }

            // if (in_array('*', $user->role->permissions ?? [])) {
            //     return true;
            // }
        });

        // Gate::define('Manajemen Rak', function ($user) {
        //     $permissions = $user->role->permissions ?? [];

        //     return is_array($permissions) && in_array('Manajemen Rak', $permissions);
        // });
    }
}
