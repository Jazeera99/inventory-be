<?php

namespace App\Models;

use Database\Factories\StockOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read \App\Models\Customer|null $customer
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\StockOrderItem> $items
 * @property-read int|null $items_count
 * @property-read \App\Models\Supplier|null $supplier
 * @method static \Database\Factories\StockOrderFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockOrder newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockOrder newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockOrder query()
 * @mixin \Eloquent
 */
class StockOrder extends Model
{
    /** @use HasFactory<StockOrderFactory> */
    use HasFactory;

    protected $fillable = [
        'order_no',
        'type',
        'supplier_id',
        'customer_id',
        'status',
        'order_date',
        'expected_date',
        'parent_id',
        'cancel_reason',
        'notes',
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_date' => 'date',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(StockOrderItem::class, 'stock_order_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(StockOrder::class, 'parent_id');
    }

    public function backorders(): HasMany
    {
        return $this->hasMany(StockOrder::class, 'parent_id');
    }

    public function stockTransactions(): HasMany
    {
        return $this->hasMany(StockTransaction::class, 'stock_order_id');
    }
}
