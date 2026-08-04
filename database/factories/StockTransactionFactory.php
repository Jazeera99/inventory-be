<?php

namespace Database\Factories;

use App\Models\StockTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockTransaction>
 */
class StockTransactionFactory extends Factory
{
    protected $model = StockTransaction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement(['IN', 'OUT', 'MOVE', 'ADJUSTMENT']);
        $typeCode = match ($type) {
            'IN' => 'IN',
            'OUT' => 'OUT',
            'MOVE' => 'MOVE',
            'ADJUSTMENT' => 'ADJ',
            default => 'GEN'
        };
        $randomDateTime = $this->faker->dateTimeBetween('2026-05-01 00:00:00', '2026-08-2 23:59:59');
        $date = $randomDateTime->format('Ymd');
        $sequence = str_pad($this->faker->unique()->numberBetween(1, 999), 4, '0', STR_PAD_LEFT);

        return [
            'transaction_no' => "TRX-{$typeCode}-{$date}-{$sequence}",
            'type' => $type,
            'date' => $randomDateTime->format('Y-m-d'),
            'user_id' => User::first()?->id ?? User::factory(),
            'deleted_by' => null,
            'created_at' => $randomDateTime,
            'updated_at' => $randomDateTime,
        ];
    }

    /**
     * State for make transaction cancelled (soft deleted)
     */
    public function cancelled(?string $type = null): static
    {
        return $this->state(function (array $attributes) use ($type) {
            // Jika tidak mendefinisikan tipe saat dipanggil, acak dari tipe yang valid untuk cancel
            $allowedTypes = ['IN', 'OUT', 'MOVE'];
            $finalType = in_array($type, $allowedTypes) ? $type : $this->faker->randomElement($allowedTypes);

            $typeCode = match ($finalType) {
                'IN' => 'IN',
                'OUT' => 'OUT',
                'MOVE' => 'MOVE',
                default => 'GEN'
            };

            $randomDateTime = $this->faker->dateTimeBetween('2026-05-01 00:00:00', '2026-07-14 23:59:59');
            $date = $randomDateTime->format('Ymd');
            $sequence = str_pad((string) $this->faker->unique()->numberBetween(1, 999), 4, '0', STR_PAD_LEFT);

            return [
                'type' => $finalType,
                'transaction_no' => "TRX-{$typeCode}-{$date}-{$sequence}",
                'deleted_by' => User::first()?->id ?? User::factory(),
                'deleted_at' => $randomDateTime,
                'created_at' => $randomDateTime,
                'updated_at' => $randomDateTime,
            ];
        });
    }
}
