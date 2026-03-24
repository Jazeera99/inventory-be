<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rack extends Model
{
    protected $fillable = [
        'rack_code',
        'warehouse_name'
    ];

    public function productLocations()
    {
        return $this->hasMany(ProductLocation::class);
    }

    public function stockLedgers()
    {
        return $this->hasMany(StockLedger::class);
    }
}
