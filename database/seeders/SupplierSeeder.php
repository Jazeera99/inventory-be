<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $realSuppliers = [
            [
                'supplier_name' => 'PT Indofood Sukses Makmur Tbk - Cabang SIER',
                'phone' => '0318431234',
                'address' => 'Kawasan Industri SIER, Jl. Rungkut Industri II No. 23, Surabaya',
                'is_active' => true,
            ],
            [
                'supplier_name' => 'CV Margomulyo Jaya Logistic',
                'phone' => '0317495566',
                'address' => 'Komplek Pergudangan Margomulyo Perdana Blok B-12, Surabaya',
                'is_active' => true,
            ],
            [
                'supplier_name' => 'UD Pabean Groseria',
                'phone' => '0313524411',
                'address' => 'Jl. Pabean No. 45, Kawasan Wisata Ampel, Surabaya',
                'is_active' => true,
            ],
            [
                'supplier_name' => 'PT Wings Surya',
                'phone' => '0315318888',
                'address' => 'Jl. Embong Malang No. 61-65, Surabaya',
                'is_active' => true,
            ],
            [
                'supplier_name' => 'PT Surya Madistrindo Depo Surabaya',
                'phone' => '0318419900',
                'address' => 'Jl. Raya Panjang Jiwo No. 48, Tenggilis Mejoyo, Surabaya',
                'is_active' => true,
            ],
        ];

        foreach ($realSuppliers as $data) {
            Supplier::updateOrCreate(['supplier_name' => $data['supplier_name']], $data);
        }

        // Tambah 10 dummy pakai factory
        Supplier::factory(10)->create();
    }
}
