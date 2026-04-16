<?php

namespace App\Utils;

use App\Models\SystemSetting;
use Cknow\Money\Money;

class VAT
{
    public static function percentage(): int
    {
        $vatSetting = SystemSetting::key(SystemSetting::KEY_VAT_PERCENTAGE);

        return (int) ($vatSetting->value ?? 0);
    }

    /**
     * if VAT is 11%
     * VAT::included(111_000); // returns 11_000
     */
    public static function included(Money $amount): Money
    {
        return $amount
            ->multiply(self::percentage())
            ->divide(100 + self::percentage());
    }

    /**
     * if VAT is 11%
     * VAT::included(111_000); // returns 12_210
     */
    public static function excluded(Money $amount): Money
    {
        // if VAT = 11%, then amount * 11 / 100;
        // if VAT = 12%, then amount * 12 / 100;
        return $amount
            ->multiply(self::percentage())
            ->divide(100);
    }
}
