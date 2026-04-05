<?php

namespace App\Utils\Currency\Provider;

use App\Utils\Currency\AbstractCurrencyConverter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * https://openexchangerates.org
 */
class OpenExchangeRates extends AbstractCurrencyConverter
{
    private const TO_CURRENCY = 'IDR';

    private function cacheTtl(): Carbon
    {
        $ttlHours = config('currency.open_exchange_rates.ttl_hours');

        return now()->addHours($ttlHours);
    }

    /**
     * Fetches the latest exchange rates from Open Exchange Rates API.
     *
     * @return array<string,string> An associative array of currency codes to their rates against IDR.
     *
     * @throws \Exception If the API request fails.
     */
    private function fetchData(): array
    {
        $apiKey = config('currency.open_exchange_rates.app_id');
        $baseUrl = config('currency.open_exchange_rates.api_url');

        // PromiseInterface only happen on faked Http in testing
        // it is safe to just typehint as Response here
        /** @var \Illuminate\Http\Client\Response $response */
        $response = Http::get($baseUrl, empty($apiKey) ? [] : [
            'app_id' => $apiKey,
            // 'base' => $this->fromCurrency, // free plan only supports USD
        ]);

        if ($response->failed()) {
            throw new \Exception("Failed to fetch exchange rates. Error {$response->status()}");
        }

        $rates = $response->json('rates');

        $ratesToIDR = [];
        /** @var string */
        $IDRRates = $rates[self::TO_CURRENCY];
        foreach ($rates as $key => $rate) {
            if ($key === 'BTC') {
                $ratesToIDR[$key] = 0;
            } else {
                $ratesToIDR[$key] = money($IDRRates)->divide($rate)->formatByDecimal();
            }
        }

        return $ratesToIDR;
    }

    /**
     * Get the IDR rate for a given currency.
     *
     * @return numeric-string
     */
    public function getIDRRate(string $fromCurrency): string
    {
        if ($fromCurrency === 'IDR') {
            return '1.00';
        }

        $rates = Cache::remember(
            'open_exchange_rates',
            $this->cacheTtl(),
            fn () => $this->fetchData()
        );

        return $rates[$fromCurrency];
    }
}
