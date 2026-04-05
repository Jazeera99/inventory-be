<?php

namespace App\Traits;

trait EnumValues
{
    /**
     * @return string[]
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
