<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $product_sku
 * @property string $transaction_no
 * @property string $type
 * @property int $rack_id
 * @property string $expired_at
 * @property int $qty
 * @property int $balance_before
 * @property int $balance_after
 * @property int $user_id
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Product $product
 * @property-read Rack $rack
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockLedger newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockLedger newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockLedger query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockLedger whereBalanceAfter($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockLedger whereBalanceBefore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockLedger whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockLedger whereExpiredAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockLedger whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockLedger whereNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockLedger whereProductSku($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockLedger whereQty($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockLedger whereRackId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockLedger whereTransactionNo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockLedger whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockLedger whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockLedger whereUserId($value)
 *
 * @mixin \Eloquent
 */
class StockLedger extends Model
{
    protected $fillable = [
        'product_sku',
        'transaction_no',
        'type',
        'rack_id',
        'expired_at',
        'qty',
        'balance_before',
        'balance_after',
        'user_id',
        'note',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_sku', 'sku');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rack(): BelongsTo
    {
        return $this->belongsTo(Rack::class);
    }
}
