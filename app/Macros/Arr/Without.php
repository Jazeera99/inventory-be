<?php

namespace App\Macros\Arr;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * Wrap each item in single quotes.
 */
Arr::macro('without', function (Collection|array $items, ?array $excepts): Collection|array {
    $closure = fn ($value) => ! in_array($value, $excepts ?? []);

    return is_array($items) ? Arr::where($items, $closure) : $items->filter($closure);
});
