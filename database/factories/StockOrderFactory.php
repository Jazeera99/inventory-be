<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\StockOrder;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockOrder>
 */
class StockOrderFactory extends Factory
{
    protected $model = StockOrder::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement(['INBOUND', 'OUTBOUND']);
        $prefix = $type === 'INBOUND' ? 'PO' : 'SO';
        $orderDate = Carbon::parse($this->faker->dateTimeBetween('2026-05-01', '2026-07-20'));
    
    // 2. Format order_no Sesuai order_date!
    $dateStr = $orderDate->format('Ymd');
    $seq = str_pad((string) $this->faker->numberBetween(1, 999), 4, '0', STR_PAD_LEFT);

    // 3. Tanggal Estimasi H+2 sampai H+7 hari dari Order Date
    $expectedDate = (clone $orderDate)->addDays(rand(2, 7));

        return [
            'order_no' => "{$prefix}-{$dateStr}-{$seq}",
            'type' => $type,
            'supplier_id' => $type === 'INBOUND' ? Supplier::factory() : null,
            'customer_id' => $type === 'OUTBOUND' ? Customer::factory() : null,
            'status' => $this->faker->randomElement(['DRAFT', 'PENDING', 'PARTIAL', 'COMPLETED']),
            'order_date' => $orderDate->format('Y-m-d'),
            'expected_date' => $expectedDate->format('Y-m-d'),
            'parent_id' => null,
            'cancel_reason' => null,
            'notes' => $this->faker->sentence(),
        ];
    }
}
