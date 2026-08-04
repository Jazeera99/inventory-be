<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $companyTypes = ['PT', 'CV', 'UD'];
        $surabayaSuppliers = [
            'Jawa Utama Logistik', 'Surabaya Jaya Makmur', 'Bumi Surabaya Perdana',
            'Rungkut Industri Megah', 'Margomulyo Distributor', 'Gresik Kemas Nusantara',
            'Sidoarjo Pangan Utama', 'Pabean Trading Co', 'Kembang Jepun Express'
        ];

        $surabayaRoads = [
            'Jl. Margomulyo No. ', 'Jl. Raya Rungkut Industri No. ', 'Jl. Kembang Jepun No. ',
            'Jl. Kalianak Barat No. ', 'Jl. Tanjungsari No. ', 'Jl. Raya Berbek No. '
        ];

        return [
            'supplier_name' => $this->faker->randomElement($companyTypes) . ' ' . $this->faker->randomElement($surabayaSuppliers),
            'phone' => '031' . $this->faker->numerify('#######'), // Kode Area Surabaya (031)
            'address' => $this->faker->randomElement($surabayaRoads) . $this->faker->numberBetween(1, 150) . ', Surabaya, Jawa Timur',
            'is_active' => true,
        ];
    }
}
