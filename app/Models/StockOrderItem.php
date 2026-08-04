<?php

namespace App\Models;

use Database\Factories\StockOrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read \App\Models\Product|null $product
 * @property-read \App\Models\StockOrder|null $stockOrder
 * @method static \Database\Factories\StockOrderItemFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockOrderItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockOrderItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockOrderItem query()
 * @mixin \Eloquent
 */
class StockOrderItem extends Model
{
    /** @use HasFactory<StockOrderItemFactory> */
    use HasFactory;

    protected $fillable = [
        'stock_order_id',
        'product_sku',
        'qty_ordered',
        'qty_fulfilled',
        'unit_price',
    ];

    protected $casts = [
        'qty_ordered' => 'integer',
        'qty_fulfilled' => 'integer',
        'unit_price' => 'float',
    ];

    public function stockOrder(): BelongsTo
    {
        return $this->belongsTo(StockOrder::class, 'stock_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_sku', 'sku');
    }
}
