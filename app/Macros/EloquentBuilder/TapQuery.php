<?php

namespace App\Macros\EloquentBuilder;

use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tap into the query builder instance to perform additional operations.
 *
 * This closure receives an instance of \Illuminate\Database\Eloquent\Builder
 * to allow further customization or manipulation of the query.
 *
 * @param  Closure(Builder): Builder|void  $callback
 */
Builder::macro('tapQuery', function (Closure $callback): Builder {
    // return the query builder instance if the callback doesn't return anything.
    return $callback($this) ?? $this;
});
