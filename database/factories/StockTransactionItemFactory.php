<?php

namespace Database\Factories;

use App\Models\StockTransactionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockTransactionItem>
 */
class StockTransactionItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $qty = $this->faker->numberBetween(1, 20);

        return [
            'transaction_no' => null,
            'product_sku' => null,
            'rack_id' => null,
            'target_rack_id' => null,
            'qty' => $qty,
            'qty_before' => 0,
            'qty_after' => $qty,
            'expired_at' => now()->addYear()->format('Y-m-d'),
            'notes' => $this->faker->randomElement([
                'Barang baru masuk',
                'Kondisi baik',
                'Stok tambahan',
                'Re-stock bulanan',
                null,
            ]),
        ];
    }
}
