<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sku' => strtoupper($this->faker->unique()->bothify('SM-###-???')),
            'name' => $this->faker->randomElement([
                'Minyak Goreng Sawit 2L',
                'Beras Setra Ramos 25kg',
                'Gula Pasir Putih 1kg x 20',
                'Mie Instan Goreng (Karton)',
                'Tepung Terigu Segitiga (Sack)'
            ]),
            'brand' => $this->faker->randomElement(['Bimoli', 'Fortune', 'Indofood', 'Gulaku', 'Bogasari']),
            'category_id' => Category::factory(),
            'unit' => $this->faker->randomElement(['Dus', 'Karton', 'Sack', 'Ball']),
            'size_info' => $this->faker->randomElement(['Isi 12', 'Isi 24', '25 KG', '50 KG']),
            'min_stock' => 50,
            'is_active' => true,
        ];
    }
}
