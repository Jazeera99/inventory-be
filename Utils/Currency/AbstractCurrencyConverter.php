<?php

namespace App\Utils\Currency;

abstract class AbstractCurrencyConverter
{
    public function __construct() {}

    /**
     * @return numeric-string
     */
    abstract public function getIDRRate(string $currency): string;
}
