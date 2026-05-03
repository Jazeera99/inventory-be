<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $location_code
 * @property string $rack_name
 * @property int $column_number
 * @property int $level_number
 * @property int $is_active
 * @property int $is_maintenance
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, \App\Models\ProductLocation> $productLocations
 * @property-read int|null $product_locations_count
 * @property-read Collection<int, \App\Models\StockLedger> $stockLedgers
 * @property-read int|null $stock_ledgers_count
 * @method static \Database\Factories\RackFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rack newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rack newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rack query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rack whereColumnNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rack whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rack whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rack whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rack whereIsMaintenance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rack whereLevelNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rack whereLocationCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rack whereRackName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rack whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Rack extends Model
{
    use HasFactory;

    protected $fillable = [
        'location_code',
        'rack_name',
        'column_number',
        'level_number',
        'is_active',
        'is_maintenance',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($rack): void {
            if (empty($rack->location_code)) {
                $rack->location_code = strtoupper($rack->rack_name).$rack->column_number.'-'.$rack->level_number;
            }
        });
    }

    public function productLocations()
    {
        return $this->hasMany(ProductLocation::class);
    }

    public function stockLedgers()
    {
        return $this->hasMany(StockLedger::class);
    }
}
