<?php

namespace App\Utils;

use Carbon\Carbon;
use Closure;
use Error;
use Illuminate\Support\Collection;

class ReportUtils
{
    /**
     * Maps a key to a value.
     *
     * @var Closure(int|string, mixed): array<int|string, mixed>|null
     */
    public static ?Closure $mapper = null;

    /**
     * @param  Collection<int,int>  $collections
     * @return Collection<int|string,mixed>
     */
    private static function callback(Collection $collections): Collection
    {
        if (is_null(self::$mapper)) {
            throw new Error('Mapper closure is not set');
        }

        return $collections->mapWithKeys(self::$mapper);
    }

    /**
     * @return Collection<int|string,mixed>
     */
    public static function fillDataDaily(int $year, int $month): Collection
    {
        // Build a deterministic, ordered map from 1 into end days in month,
        $daysInMonth = Carbon::create($year, $month)->daysInMonth ?? 0;
        $days = collect(range(1, $daysInMonth));

        return self::callback($days);
    }

    /**
     * @return Collection<int|string,mixed>
     */
    public static function fillDataMonthly(): Collection
    {
        // Build a deterministic, ordered map from 1..12,
        $months = collect(range(1, 12));

        return self::callback($months);
    }

    /**
     * @return Collection<int|string,mixed>
     */
    public static function fillDataYearly(int $yearStart, int $yearEnd): Collection
    {
        // Build a deterministic, ordered map from year start to end,
        $year = collect(range($yearStart, $yearEnd));

        return self::callback($year);
    }
}
