<?php

namespace App\Macros\AssertableJson;

use Illuminate\Support\Carbon;
use Illuminate\Testing\Fluent\AssertableJson;

AssertableJson::macro('whereTs', function (string $key, Carbon $value): static {
    /** @var AssertableJson $this */
    return $this->where($key, $value->startOfSecond()->toJSON());
});
