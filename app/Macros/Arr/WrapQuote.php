<?php

namespace App\Macros\Arr;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * Wrap each item in single quotes.
 */
Arr::macro('wrapQuote', function (Collection|array $items): Collection|array {
    $wrap = fn ($item): string => "'$item'";

    return is_array($items) ? Arr::map($items, $wrap) : $items->map($wrap);
});
