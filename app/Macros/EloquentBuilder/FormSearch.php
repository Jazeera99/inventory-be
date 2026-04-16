<?php

namespace App\Macros\EloquentBuilder;

use App\Utils\ProductCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Usage:
 *
 * Purchase::query()
 *   ->formSearch(
 *       productVariantPath: 'purchaseItems.productVariant',
 *       callback: fn ($query, $search) => $query->orWhere('reference_number', 'like', "%{$search}%")
 *   )
 */
Builder::macro('formSearch', function (string $productVariantPath, ?\Closure $callback = null, ?string $keyword = null) {
    // get search text from request
    $search = $keyword ?: request()->query('search', '');

    /** @var Builder<Model> $this */

    // early return when search text is empty
    if (empty($search)) {
        return $this;
    }

    $code = new ProductCode($search);
    $size = $code->getSize();
    $color = $code->getColor();
    $productCode = $code->getProductCode();

    $this->where(fn ($query) => $query
        ->where('form_number', 'like', "%{$search}%")
        ->orWhereHas($productVariantPath, fn ($query) => $query
            ->whereHas('product', fn ($query) => $query->where('code', $productCode))
            ->when($size, fn ($query) => $query->where('size', $size))
            ->when($color, fn ($query) => $query->where('color', $color))
        )
    );

    // callback after form_number and product_variant search
    if ($callback) {
        $callback($this, $search);
    }

    return $this;
});
