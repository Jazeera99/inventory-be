<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\StockOrder;
use App\Models\StockOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockOrderItem>
 */
class StockOrderItemFactory extends Factory
{
    protected $model = StockOrderItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ordered = $this->faker->numberBetween(10, 500);
        $product = Product::query()->inRandomOrder()->first() ?? Product::factory()->create();

        return [
            'stock_order_id' => StockOrder::factory(),
            'product_sku' => $product->sku,
            'qty_ordered' => $ordered,
            'qty_fulfilled' => $this->faker->numberBetween(0, $ordered),
            'unit_price' => $product->purchase_price ?? 10000,
        ];
    }
}
