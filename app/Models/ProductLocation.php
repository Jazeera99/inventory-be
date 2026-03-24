<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductLocation extends Model
{
    protected $fillable = [
        'product_sku',
        'rack_id',
        'current_quantity',
        'expired_date'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_sku', 'sku');
    }

    public function rack()
    {
        return $this->belongsTo(Rack::class);
    }
}
