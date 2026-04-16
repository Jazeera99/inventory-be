<?php

namespace App\Utils;

use App\Models\Product;
use App\Models\ProductPriceLog;
use App\Models\PurchaseDraft;
use App\Models\User;
use Cknow\Money\Money;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class PriceUpdater
{
    /**
     * Update product price by user, and log the price change.
     */
    public static function byUser(Product $product, int $newPrice, ?User $user = null): void
    {
        /** @var Money|null */
        $oldPrice = $product->getOriginal('price');
        $product->price = $newPrice;
        if (is_null($oldPrice) || ! $oldPrice->equals(money_int($newPrice))) {
            /** @var User */
            $user = $user ?? Auth::user();

            throw_if(empty($user), new RuntimeException('User must be authenticated to update price.'));

            ProductPriceLog::create([
                'product_id' => $product->id,
                'price_old' => $oldPrice,
                'price_new' => $newPrice,
                'updated_by' => "{$user->id}: {$user->name}",
            ]);
        }
    }

    /**
     * Update product price by multiplier, and log the price change.
     * Skips if product price is already set or multiplier is not set.
     *
     * Need to eager load `productPriceMultiplier.supplier` relation on product before calling this method.
     */
    public static function byMultiplier(Product $product): void
    {
        if (! is_null($product->price) || is_null($product->productPriceMultiplier)) {
            return;
        }

        $productPriceMultiplier = $product->productPriceMultiplier;

        $purchaseDraft = PurchaseDraft::query()
            ->with('purchaseDraftItems.productVariant')
            ->whereHas('purchaseDraftItems.productVariant', fn ($query) => $query
                ->where('product_id', $product->id)
            )
            ->orderByDesc('id')
            ->first();

        $highestPrice = $purchaseDraft?->purchaseDraftItems
            ->filter(fn ($item) => $item->productVariant->product_id === $product->id)
            ->sortByDesc('price')
            ->first();

        if ($purchaseDraft && $highestPrice && $productPriceMultiplier->multiplier) {
            $logCurrency = $purchaseDraft->currency_code;
            if ($logCurrency !== 'IDR') {
                $logCurrency = $logCurrency.' @'.$purchaseDraft->currency_rate_to_idr_sell;
            }

            $multiplier = $productPriceMultiplier->multiplier;

            $product->price = $highestPrice->price
                ->multiply($purchaseDraft->currency_rate_to_idr_sell)
                ->multiply($multiplier)
                ->divide(100)
                ->roundNearest(5_000);
            $product->save();

            ProductPriceLog::create([
                'product_id' => $product->id,
                'price_old' => null,
                'price_new' => $product->price,
                'updated_by' => 'SYSTEM',
                'supplier' => $productPriceMultiplier->supplier->id.': '.$productPriceMultiplier->supplier->name,
                'product_group' => $product->productGroup->id.': '.$product->productGroup->name,
                'purchase_price' => $highestPrice->price,
                'purchase_currency' => $logCurrency,
                'multiplier' => $multiplier,
            ]);
        }
    }
}
