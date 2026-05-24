<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $brand = $this->faker->regexify('[A-Z]{3}');
        $type = $this->faker->regexify('[A-Z]{3}');

        return [
            'sku' => $brand.'-GEN-'.$this->faker->unique()->numberBetween(100, 999),
            'product_name' => $this->faker->words(3, true),
            'category_id' => Category::factory(),
            'brand' => $brand,
            'type' => $type,
            'packaging' => 'Refil',
            'size' => '1000 ml',
            'stock' => 0,
            'min_stock' => 5,
            'is_active' => true,
        ];
    }
}
