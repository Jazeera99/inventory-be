<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockLedger extends Model
{
    protected $fillable = [
        'product_sku',
        'transaction_ref',
        'type',
        'rack_id',
        'quantity',
        'balance_before',
        'balance_after',
        'user_id',
        'user_name_snapshot',
        'note',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_sku', 'sku');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function rack()
    {
        return $this->belongsTo(Rack::class);
    }
}
