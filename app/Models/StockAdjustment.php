<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockAdjustment extends Model
{
    protected $fillable = [
        'adjustment_no',
        'product_sku',
        'rack_id',
        'qty_before',
        'qty_after',
        'total_qty',
        'date',
        'user_id',
        'reason'
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
}
