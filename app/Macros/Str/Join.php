<?php

namespace App\Macros\Str;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Convert array to string with oxford comma style.
 */
Str::macro('joinOxford', function (Collection|array $items, string $conjunction): string {
    $glue = ', ';
    $finalGlue = ' '.$conjunction.' ';

    $items = $items instanceof Collection ? $items->all() : $items;

    if (count($items) > 2) {
        $finalGlue = ','.$finalGlue;
    }

    $result = Arr::join($items, $glue, $finalGlue);

    return $result;
});

/**
 * Convert array to string with oxford comma style "or" conjunction.
 * Results in strings like "A or B" and "A, B, or C".
 */
Str::macro('joinOr', function (Collection|array $items): string {
    return Str::joinOxford($items, __('or'));
});

/**
 * Convert array to string with oxford comma style "and" conjunction.
 * Results in strings like "A and B" and "A, B, and C".
 */
// Str::macro('joinAnd', function (iterable $items): string {
//     return Str::joinOxford($items, __('and'));
// });
