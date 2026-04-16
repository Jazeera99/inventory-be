<?php

use Illuminate\Testing\Fluent\AssertableJson;

AssertableJson::macro('paginated', function (int $count = 1) {
    $defaultPerPage = 50;

    /** @var AssertableJson $this */
    return $this
        ->has('meta', fn (AssertableJson $json) => $json
            ->where('per_page', $defaultPerPage)
            ->where('total', $count)
            ->where('current_page', 1)
            ->where('last_page', 1)
            ->where('from', 1)
            ->where('to', $count)
            ->has('links')
            ->has('path')
        )
        ->has('links');
});

AssertableJson::macro('cursorPaginated', function () {
    $defaultPerPage = 48;

    /** @var AssertableJson $this */
    return $this
        ->has('meta', fn (AssertableJson $json) => $json
            ->where('per_page', $defaultPerPage)
            ->hasAll([
                'path',
                'next_cursor',
                'prev_cursor',
            ])
        )
        ->has('links');
});
