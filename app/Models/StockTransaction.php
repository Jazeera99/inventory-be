<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockTransaction extends Model
{
    protected $fillable = [
        'transaktion_no',
        'type',
        'date',
        'user_id',
        'notes'
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
        return $this->hasMany(StockTransactionItem::class, 'transaktion_no', 'transaktion_no');
    }
}
