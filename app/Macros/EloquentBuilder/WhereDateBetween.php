<?php

namespace App\Macros\EloquentBuilder;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * WhereBetween from request date_start and date_end for given column name.
 */
Builder::macro('whereDateBetween', function (string $columnName): Builder {
    $timezone = request()->header('Timezone', 'Asia/Jakarta');

    $dateStart = Carbon::make(request()->query('date_start', '0001-01-01'), $timezone);
    $dateEnd = Carbon::make(request()->query('date_end', '9999-12-31'), $timezone);

    if ($dateStart !== null && $dateEnd !== null && $dateStart->greaterThan($dateEnd)) {
        [$dateStart, $dateEnd] = [$dateEnd, $dateStart];
    }

    return $this->whereBetween($columnName, [$dateStart, $dateEnd]);
});
