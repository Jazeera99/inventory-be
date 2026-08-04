<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $realCustomers = [
            [
                'customer_name' => 'Toko Sembako Berkah Rungkut',
                'phone' => '081234567890',
                'address' => 'Jl. Rungkut Asri Timur No. 88, Surabaya',
                'is_active' => true,
            ],
            [
                'customer_name' => 'CV Sentosa West Surabaya',
                'phone' => '081987654321',
                'address' => 'Ruko Bukit Darmo Boulevard No. 10A, Surabaya Barat',
                'is_active' => true,
            ],
            [
                'customer_name' => 'UD Grosir Mulyosari',
                'phone' => '085711223344',
                'address' => 'Jl. Raya Mulyosari No. 120, Mulyorejo, Surabaya',
                'is_active' => true,
            ],
            [
                'customer_name' => 'Minimarket Baratajaya',
                'phone' => '082199887766',
                'address' => 'Jl. Barata Jaya XIX No. 5, Gubeng, Surabaya',
                'is_active' => true,
            ],
        ];

        foreach ($realCustomers as $data) {
            Customer::updateOrCreate(['customer_name' => $data['customer_name']], $data);
        }

        // Tambah 15 dummy pakai factory
        Customer::factory(15)->create();
    }
}
