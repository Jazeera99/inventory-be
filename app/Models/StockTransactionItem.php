<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockTransactionItem extends Model
{
    protected $fillable = [
        'transaction_no',
        'product_sku',
        'rack_from_id',
        'rack_to_id',
        'quantity',
    ];

    public function stockTransaction()
    {
        return $this->belongsTo(StockTransaction::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_sku', 'sku');
    }
}
