<?php

namespace App\Macros\EloquentBuilder;

use Illuminate\Database\Eloquent\Builder;

/**
 * Convert a datetime column from UTC to the timezone specified in the request header.
 */
Builder::macro('convertTz', function (string $columnName): string {
    $timezone = request()->header('Timezone', 'Asia/Jakarta');

    return "CONVERT_TZ($columnName, 'UTC', '$timezone')";
});

/**
 * Select the day of the month from a datetime column, converting it to the timezone specified in the request header.
 */
Builder::macro('selectTzDay', function (string $columnName, string $alias = ''): Builder {
    return $this->selectRaw('DAY('.$this->convertTz($columnName).')'.($alias ? " AS $alias" : ''));
});
Builder::macro('selectTzMonth', function (string $columnName, string $alias = ''): Builder {
    return $this->selectRaw('MONTH('.$this->convertTz($columnName).')'.($alias ? " AS $alias" : ''));
});
Builder::macro('selectTzYear', function (string $columnName, string $alias = ''): Builder {
    return $this->selectRaw('YEAR('.$this->convertTz($columnName).')'.($alias ? " AS $alias" : ''));
});
