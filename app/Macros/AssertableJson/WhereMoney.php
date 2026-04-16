<?php

namespace App\Macros\AssertableJson;

use Cknow\Money\Money;
use Illuminate\Testing\Fluent\AssertableJson;

use function PHPUnit\Framework\assertEquals;

// Convert integer to decimal string format for Money comparison
// DecimalMoneySerializer serializes Money as "150000.00" string
AssertableJson::macro('whereMoney', function (string $key, int|float|string|Money|null $expected): static {
    /** @var AssertableJson $this */
    if (is_null($expected)) {
        return $this->where($key, null);
    }

    if ($expected instanceof Money === false) {
        $expected = money_int($expected);
    }

    // $expectedValue = (float) $value->formatByDecimal();

    return $this->where($key, function ($actual) use ($expected) {
        assertEquals($expected->format(), money_int($actual)->format());

        return true;
    });
});
