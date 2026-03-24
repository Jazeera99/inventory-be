<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $primaryKey = 'sku';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'sku',
        'name',
        'brand',
        'category_id',
        'unit',
        'size_info',
        'purchase_price',
        'min_stock',
        'image_url',
        'is_active',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function stockAdjustments()
    {
        return $this->hasMany(StockAdjustment::class, 'product_sku', 'sku');
    }

    public function productLocations()
    {
        return $this->hasMany(ProductLocation::class, 'product_sku', 'sku');
    }

    public function stockTransactionItems()
    {
        return $this->hasMany(StockTransactionItem::class, 'product_sku', 'sku');
    }

    public function stockTransactions()
    {
        return $this->hasManyThrough(StockTransaction::class, StockTransactionItem::class, 'product_sku', 'transaktion_no', 'sku', 'transaktion_no');
    }
}
