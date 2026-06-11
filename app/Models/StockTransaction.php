<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $transaction_no
 * @property string $type
 * @property string $date
 * @property int $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $deletedByUser
 * @property-read Collection<int, StockTransactionItem> $items
 * @property-read int|null $items_count
 * @property-read Product|null $product
 * @property-read Rack|null $rack
 * @property-read User $user
 *
 * @method static \Database\Factories\StockTransactionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransaction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransaction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransaction onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransaction query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransaction whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransaction whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransaction whereTransactionNo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransaction whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransaction whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransaction whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransaction withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransaction withoutTrashed()
 *
 * @mixin \Eloquent
 */
class StockTransaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $primaryKey = 'transaction_no';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $fillable = [
        'transaction_no',
        'type',
        'date',
        'user_id',
        'deleted_by',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_sku', 'sku');
    }

    public function rack()
    {
        return $this->belongsTo(Rack::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(StockTransactionItem::class, 'transaction_no', 'transaction_no');
    }

    public function deletedByUser()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
