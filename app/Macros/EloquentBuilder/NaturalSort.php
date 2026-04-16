<?php

namespace App\Macros\EloquentBuilder;

use Illuminate\Database\Eloquent\Builder;

/**
 * MySQL order by but natural sort, e.g: file1, file2, file10
 */
Builder::macro('naturalSort', function (string $column) {
    return $this->orderByRaw("
        REGEXP_REPLACE($column, '[0-9]+', '') ASC,
        CAST(REGEXP_REPLACE($column, '[^0-9]+', '') AS UNSIGNED) ASC,
        $column ASC
    ");
});
