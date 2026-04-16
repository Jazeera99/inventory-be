<?php

namespace App\Macros\EloquentBuilder;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

Builder::macro('customPaginate', function () {
    $defaultPerPage = 50;
    $maxPerPage = 1_000;

    // not numeric will become 0, and then reverted back to DEFAULT_PER_PAGE.
    $perPage = (int) request()->query('per_page') ?: $defaultPerPage;

    // cap the max user can request with query params to 1,000 items per page.
    $perPage = min($perPage, $maxPerPage);

    /** @var Builder<Model> $this */
    return $this->paginate($perPage);
});

Builder::macro('customCursorPaginate', function () {
    $defaultPerPage = 48;
    $maxPerPage = 1_000;

    $columns = ['*'];
    $cursorName = 'cursor';
    $cursor = null;

    $perPage = (int) request()->query('per_page') ?: $defaultPerPage;

    $perPage = min($perPage, $maxPerPage);

    /** @var Builder<Model> $this */
    return $this->cursorPaginate($perPage, $columns, $cursorName, $cursor);
});
