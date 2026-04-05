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
        // bcscale(4); // Set default scale for BcMath to 2 decimal places
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! app()->isProduction());

        $this->registerMacros();

        $this->registerHelpers();

        $this->defineConstants();

        // DB::listen(function ($query) {
        //     Log::info(
        //         $query->sql,
        //         $query->bindings,
        //         $query->time
        //     );
        // });
    }

    private function registerMacros(): void
    {
        // require files inside immediate subfolders of app/Macros
        if ($files = glob(app_path('Macros').'/*/*.php')) {
            foreach ($files as $file) {
                require_once $file;
            }
        }
    }

    private function registerHelpers(): void
    {
        // Load PHP helper files in app/Helpers (flat directory)
        if ($files = glob(app_path('Helpers').'/*.php')) {
            foreach ($files as $helper) {
                require_once $helper;
            }
        }
    }

    private function defineConstants(): void
    {
        if (! defined('G_MAX_TINYINT')) {
            // MySQL max values
            define('G_MAX_TINYINT', 127);   // 127
            define('G_MAX_UTINYINT', 255);   // 255
            define('G_MAX_SMALLINT', 32_000);   // 32_767
            define('G_MAX_USMALLINT', 65_000);   // 65_535
            define('G_MAX_MEDIUMINT', 8_000_000); // 8_388_607
            define('G_MAX_UMEDIUMINT', 16_000_000); // 16_777_215
            define('G_MAX_INT', 2_000_000_000);   // 2_147_483_647
            define('G_MAX_UINT', 4_000_000_000); // 4_294_967_295

            define('G_MAX_MONEY_INT', 20_000_000);   // integer limit with MoneyPHP
            define('G_MAX_MONEY_UINT', 40_000_000); // unsigned integer limit with MoneyPHP
        }
    }
}
