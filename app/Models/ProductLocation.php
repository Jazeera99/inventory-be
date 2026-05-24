<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $product_sku
 * @property int $rack_id
 * @property int $qty
 * @property string $batch_code
 * @property Carbon|null $expired_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read \App\Models\Product $product
 * @property-read \App\Models\Rack $rack
 * @method static \Database\Factories\ProductLocationFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductLocation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductLocation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductLocation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductLocation whereBatchCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductLocation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductLocation whereExpiredAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductLocation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductLocation whereProductSku($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductLocation whereQty($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductLocation whereRackId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductLocation whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class ProductLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_sku',
        'rack_id',
        'qty',
        'batch_code',
        'expired_at',
    ];

    protected $casts = [
        'qty' => 'integer',
        'expired_at' => 'date',
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
