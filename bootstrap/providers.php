<?php

use App\Providers\AppServiceProvider;
use App\Providers\GateProvider;
use App\Providers\TelescopeServiceProvider;

return [
    AppServiceProvider::class,
    GateProvider::class,
    TelescopeServiceProvider::class,
];
