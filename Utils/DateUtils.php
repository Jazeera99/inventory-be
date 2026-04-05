<?php

namespace App\Utils;

use Illuminate\Support\Collection;

class DateUtils
{
    /**
     * Generate a collection of random dates.
     *
     * @return Collection<int, \Carbon\CarbonImmutable>
     */
    public static function generateDates(int $count): Collection
    {
        $transactionDates = collect([]);

        for ($i = 0; $i < $count; $i++) {
            $date = now()
                ->subDays(random_int(1, 365))
                ->day(random_int(0, 6))
                ->hour(random_int(8, 17))
                ->minute(random_int(0, 59))
                ->second(random_int(0, 59))
                ->toImmutable();

            $transactionDates->push($date);
        }

        return $transactionDates->sort()->values();
    }
}
