<?php

namespace App\Utils\Currency;

use App\Utils\Currency\Provider\OpenExchangeRates;
use App\Utils\Currency\Provider\OpenExchangeRatesDev;
use Illuminate\Support\Facades\Facade;

/**
 * @mixin AbstractCurrencyConverter
 */
class CurrencyConverter extends Facade
{
    protected static function getFacadeAccessor()
    {
        // switch (config('currency.provider')) {
        //     case 'open_exchange_rates':
        return OpenExchangeRates::class;
        //     case 'open_exchange_rates_dev':
        //         return OpenExchangeRatesDev::class;
        //     default:
        //         throw new \Exception('Unsupported currency provider');
        // }
    }
}
