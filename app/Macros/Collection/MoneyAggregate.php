<?php

use Cknow\Money\Money;
use Illuminate\Support\Collection;

Collection::macro('sumMoney', function (string|callable|null $p1 = null): Money {
    /** @var Collection<int, Money> */
    $mapped = $this->map(function ($item) use ($p1) {
        if (is_null($p1)) {
            /** @var Money|int|float|string|null $item */
            return $item instanceof Money ? $item : money_int($item);
        }

        if (is_string($p1) && $p1) {
            $value = data_get($item, $p1);

            return $value instanceof Money ? $value : money_int($value);
        }

        $result = $p1($item);

        return $result instanceof Money ? $result : money_int($result);
    });

    if ($mapped->isEmpty()) {
        return money_int(0);
    }

    return money_sum(...$mapped);
});

Collection::macro('avgMoney', function (string|callable|null $p1 = null): Money {
    /** @var Collection<int, Money> */
    $mapped = $this->map(function ($item) use ($p1) {
        if (is_null($p1)) {
            /** @var Money|int|float|string|null $item */
            return $item instanceof Money ? $item : money_int($item);
        }

        if (is_string($p1) && $p1) {
            $value = data_get($item, $p1);

            return $value instanceof Money ? $value : money_int($value);
        }

        $result = $p1($item);

        return $result instanceof Money ? $result : money_int($result);
    });

    if ($mapped->isEmpty()) {
        return money_int(0);
    }

    return money_avg(...$mapped);
});
