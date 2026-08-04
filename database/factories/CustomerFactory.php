<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $customerTypes = ['Toko', 'UD', 'CV', 'Minimarket'];
        $surabayaCustomers = [
            'Rezeki Rungkut', 'Sinar Darmo', 'Maju Gubeng', 'Lancar Basuki Rahmat',
            'Berkah Wonokromo', 'Abadi Kenjeran', 'Sentosa Wiyung', 'Jaya Jemursari'
        ];

        $areas = [
            'Jl. Raya Darmo', 'Jl. Gubeng Masjid', 'Jl. Raya Jemursari',
            'Jl. HR Muhammad', 'Jl. Mayjen Sungkono', 'Jl. Ngagel Jaya Selatan'
        ];

        return [
            'customer_name' => $this->faker->randomElement($customerTypes) . ' ' . $this->faker->randomElement($surabayaCustomers),
            'phone' => '08' . $this->faker->randomElement(['12', '13', '57', '21']) . $this->faker->numerify('########'),
            'address' => $this->faker->randomElement($areas) . ' No. ' . $this->faker->numberBetween(1, 200) . ', Surabaya',
            'is_active' => true,
        ];
    }
}
