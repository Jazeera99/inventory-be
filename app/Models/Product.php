<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $sku
 * @property string $product_name
 * @property int $category_id
 * @property string|null $brand
 * @property string|null $type
 * @property string|null $packaging
 * @property string|null $size
 * @property int $stock
 * @property int $min_stock
 * @property int $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read \App\Models\Category $category
 * @property-read Collection<int, \App\Models\ProductLocation> $locations
 * @property-read int|null $locations_count
 * @property-read Collection<int, \App\Models\Rack> $racks
 * @property-read int|null $racks_count
 * @property-read Collection<int, \App\Models\StockTransactionItem> $stockTransactionItems
 * @property-read int|null $stock_transaction_items_count
 * @property-read Collection<int, \App\Models\StockTransaction> $stockTransactions
 * @property-read int|null $stock_transactions_count
 * @method static \Database\Factories\ProductFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereBrand($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereCategoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereMinStock($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product wherePackaging($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereProductName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereSku($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereStock($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Product extends Model
{
    use HasFactory;

    protected $primaryKey = 'sku';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'sku',
        'product_name',
        'category_id',
        'brand',
        'type',
        'packaging',
        'size',
        'purchase_price',
        'selling_price',
        'holding_cost_per_day',
        'stock',
        'min_stock',
        'exp_warning_days',
        'is_active',
    ];

    public function getRouteKeyName(): string
    {
        return 'sku';
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function locations()
    {
        return $this->hasMany(ProductLocation::class, 'product_sku', 'sku');
    }

    public function racks()
    {
        return $this->belongsToMany(Rack::class, 'product_locations', 'product_sku', 'rack_id');
    }

    public function stockTransactionItems()
    {
        return $this->hasMany(StockTransactionItem::class, 'product_sku', 'sku');
    }

    public function stockTransactions()
    {
        return $this->hasManyThrough(StockTransaction::class, StockTransactionItem::class, 'product_sku', 'transaction_no', 'sku', 'transaction_no');
    }

    public function nearExpiredLocations()
    {
        $threshold = now()->addDays($this->exp_warning_days);
        return $this->locations()
            ->whereNotNull('expired_at')
            ->where('expired_at', '<=', $threshold)
            ->where('qty', '>', 0);
    }
}
