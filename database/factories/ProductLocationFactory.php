<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductLocation;
use App\Models\Rack;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductLocation>
 */
class ProductLocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $product = Product::inRandomOrder()->first() ?? Product::factory()->create();
        $rack = Rack::inRandomOrder()->first() ?? Rack::factory()->create();
        $expiredAt = $this->faker->dateTimeBetween('+1 year', '+2 years')->format('Y-m-d');

        return [
            'product_sku' => $product->sku,
            'rack_id' => $rack->id,
            'qty' => $this->faker->numberBetween(5, 30),
            'batch_code' => $product->sku.'-'.date('Ymd', strtotime($expiredAt)).'-'.$this->faker->unique()->numerify('###'),
            'expired_at' => $expiredAt,
        ];
    }
}
