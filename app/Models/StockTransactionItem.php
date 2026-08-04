<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $transaction_no
 * @property string $product_sku
 * @property int|null $rack_id
 * @property int|null $target_rack_id
 * @property int $qty
 * @property int|null $qty_before
 * @property int|null $qty_after
 * @property string|null $expired_at
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read \App\Models\Product $product
 * @property-read \App\Models\Rack|null $rack
 * @property-read \App\Models\StockTransaction|null $stockTransaction
 * @property-read \App\Models\Rack|null $targetRack
 * @method static \Database\Factories\StockTransactionItemFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransactionItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransactionItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransactionItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransactionItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransactionItem whereExpiredAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransactionItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransactionItem whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransactionItem whereProductSku($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransactionItem whereQty($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransactionItem whereQtyAfter($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransactionItem whereQtyBefore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransactionItem whereRackId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransactionItem whereTargetRackId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransactionItem whereTransactionNo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransactionItem whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class StockTransactionItem extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $fillable = [
        'transaction_no',
        'product_sku',
        'rack_id',
        'target_rack_id',
        'qty',
        'qty_before',
        'qty_after',
        'expired_at',
        'notes',
    ];

    public function stockTransaction(): BelongsTo
    {
        return $this->belongsTo(StockTransaction::class, 'transaction_no', 'transaction_no');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_sku', 'sku');
    }

    public function rack(): BelongsTo
    {
        return $this->belongsTo(Rack::class, 'rack_id');
    }

    public function targetRack()
    {
        return $this->belongsTo(Rack::class, 'target_rack_id');
    }
}
