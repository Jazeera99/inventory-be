<?php

namespace App\Macros\Str;

use Illuminate\Support\Str;

/**
 * Convert array to string with oxford comma style.
 */
Str::macro('phone', function (string $phone): string {
    // remove non numeric characters
    $phone = str($phone)->replace([' ', '-', '(', ')', '+'], '')->toString();

    // convert to international format
    $phone = str($phone)->replaceMatches('/^0/', '62')->toString();

    return $phone;
});
