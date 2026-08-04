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
        $dataset = [
            'Beras' => [
                ['brand' => 'ROY', 'name' => 'BERAS ROJO LELE', 'type' => 'PRE', 'packaging' => 'KAR', 'size' => '25 KG'],
                ['brand' => 'PAN', 'name' => 'BERAS PANDAN WANGI', 'type' => 'SUP', 'packaging' => 'KAR', 'size' => '10 KG'],
                ['brand' => 'SPW', 'name' => 'BERAS SPHP BULOG', 'type' => 'MED', 'packaging' => 'KMS', 'size' => '5 KG'],
            ],
            'Minyak dan Lemak' => [
                ['brand' => 'BIM', 'name' => 'BIMOLI MINYAK GORENG', 'type' => 'GOR', 'packaging' => 'POU', 'size' => '2 LT'],
                ['brand' => 'SAN', 'name' => 'SANIA MINYAK GORENG', 'type' => 'GOR', 'packaging' => 'POU', 'size' => '1 LT'],
                ['brand' => 'FIL', 'name' => 'FILMA MINYAK GORENG', 'type' => 'GOR', 'packaging' => 'JER', 'size' => '5 LT'],
                ['brand' => 'BLB', 'name' => 'BLUE BAND SERBAGUNA', 'type' => 'MAR', 'packaging' => 'SCT', 'size' => '200 GR'],
            ],
            'Gula dan Pemanis' => [
                ['brand' => 'GUL', 'name' => 'GULAKU GULA PASIR PUTIH', 'type' => 'TEB', 'packaging' => 'KMS', 'size' => '1 KG'],
                ['brand' => 'GMP', 'name' => 'GULA PASIR LOKAL GMP', 'type' => 'CUR', 'packaging' => 'KAR', 'size' => '50 KG'],
            ],
            'Mie dan Instan' => [
                ['brand' => 'IND', 'name' => 'INDOMIE GORENG SPESIAL', 'type' => 'MIE', 'packaging' => 'DUS', 'size' => '40 PCS'],
                ['brand' => 'IND', 'name' => 'INDOMIE SOTO LAMONGAN', 'type' => 'MIE', 'packaging' => 'DUS', 'size' => '40 PCS'],
                ['brand' => 'SED', 'name' => 'MIE SEDAAP GORENG', 'type' => 'MIE', 'packaging' => 'DUS', 'size' => '40 PCS'],
                ['brand' => 'SKS', 'name' => 'SARIMI ISI 2 AYAM KECAP', 'type' => 'MIE', 'packaging' => 'DUS', 'size' => '24 PCS'],
            ],
            'Tepung dan Adonan' => [
                ['brand' => 'SGB', 'name' => 'TEPUNG SEGITIGA BIRU', 'type' => 'TRI', 'packaging' => 'KMS', 'size' => '1 KG'],
                ['brand' => 'CRA', 'name' => 'TEPUNG CAKRA KEMBAR', 'type' => 'TRI', 'packaging' => 'KAR', 'size' => '25 KG'],
                ['brand' => 'SGH', 'name' => 'TEPUNG TAPIOKA ROSE BRAND', 'type' => 'TAP', 'packaging' => 'KMS', 'size' => '500 GR'],
            ],
            'Susu dan Olahan' => [
                ['brand' => 'CRM', 'name' => 'SUSU KENTAL MANIS FRISIAN FLAG', 'type' => 'SKM', 'packaging' => 'KALENG', 'size' => '370 GR'],
                ['brand' => 'IND', 'name' => 'INDOMILK COKELAT', 'type' => 'SKM', 'packaging' => 'POUCH', 'size' => '545 GR'],
            ],
            'Bumbu dan Rempah' => [
                ['brand' => 'ABC', 'name' => 'SAORI SAUS TERIYAKI', 'type' => 'SAU', 'packaging' => 'BOTOL', 'size' => '200 ML'],
                ['brand' => 'ABC', 'name' => 'SAORI SAUS TIRAM', 'type' => 'SAU', 'packaging' => 'BOTOL', 'size' => '200 ML'],
                ['brand' => 'ABC', 'name' => 'SAORI SAUS TOMAT', 'type' => 'SAU', 'packaging' => 'BOTOL', 'size' => '300 ML'],
            ],
            'Minuman Ringan' => [
                ['brand' => 'COK', 'name' => 'COKE MINUMAN RINGAN', 'type' => 'SDA', 'packaging' => 'BOTOL', 'size' => '500 ML'],
                ['brand' => 'PEP', 'name' => 'PEPSI MINUMAN RINGAN', 'type' => 'SDA', 'packaging' => 'BOTOL', 'size' => '500 ML'],
                ['brand' => 'FAN', 'name' => 'FANTA MINUMAN RINGAN', 'type' => 'SDA', 'packaging' => 'BOTOL', 'size' => '500 ML'],
            ],
            'Snack dan Kue' => [
                ['brand' => 'LDS', 'name' => 'LAYS KENTANG GORENG', 'type' => 'KER', 'packaging' => 'SCT', 'size' => '150 GR'],
                ['brand' => 'LDS', 'name' => 'LAYS KENTANG GORENG', 'type' => 'KER', 'packaging' => 'SCT', 'size' => '300 GR'],
                ['brand' => 'LDS', 'name' => 'LAYS KENTANG GORENG', 'type' => 'KER', 'packaging' => 'SCT', 'size' => '500 GR'],
                ['brand' => 'LDS', 'name' => 'LAYS KENTANG GORENG', 'type' => 'KER', 'packaging' => 'DUS', 'size' => '12 PCS'],
            ],
            'Makanan Kaleng' => [
                ['brand' => 'SDE', 'name' => 'SARDEN SEDAP', 'type' => 'SDE', 'packaging' => 'KALENG', 'size' => '425 GR'],
                ['brand' => 'SDE', 'name' => 'SARDEN SEDAP', 'type' => 'SDE', 'packaging' => 'KALENG', 'size' => '155 GR'],
                ['brand' => 'TUN', 'name' => 'TUNA TONGKAT ALI', 'type' => 'TUN', 'packaging' => 'KALENG', 'size' => '185 GR'],
            ],
        ];

        // Ambil kategori secara acak atau buat baru jika kosong
        $category = Category::inRandomOrder()->first() ?? Category::factory()->create();
        $categoryName = $category->category_name;

        // Ambil template barang berdasarkan kategori, fallback ke mie instan jika nama kategori tidak match
        $templates = $dataset[$categoryName] ?? $dataset['Mie & Instan'];
        $template = $this->faker->randomElement($templates);

        // Helper function pemotong 3 huruf (sama dengan logika ProductController kamu)
        $getShort = function ($value) {
            return strtoupper(substr(str_replace(' ', '', $value), 0, 3));
        };

        $prefixCodes = [
            $getShort($categoryName),
            $getShort($template['brand']),
            $getShort($template['type']),
            $getShort($template['packaging']),
            strtoupper(substr(str_replace(' ', '', $template['size']), 0, 8)),
        ];

        $prefix = implode('-', $prefixCodes);
        // Generate angka urut acak untuk menghindari duplikasi saat seeding massal
        $sequence = str_pad((string) $this->faker->unique()->numberBetween(1, 900), 3, '0', STR_PAD_LEFT);

        $purchasePrice = $this->faker->numberBetween(65000, 150000);
        $sellingPrice = $purchasePrice + $this->faker->numberBetween(80000, 18000);

        return [
            'sku' => "{$prefix}-{$sequence}",
            'product_name' => $template['name'].' '.$template['size'],
            'category_id' => $category->id,
            'brand' => $template['brand'],
            'type' => $template['type'],
            'packaging' => $template['packaging'],
            'size' => $template['size'],
            'purchase_price' => $purchasePrice,
            'selling_price' => $sellingPrice,
            'holding_cost_per_day' => $this->faker->randomElement([50, 100, 150, 200]),
            'stock' => 0, // Sesuai aturan kartu stok: barang baru wajib dimulai dari 0
            'min_stock' => $this->faker->randomElement([5, 7, 10]),
            'exp_warning_days' => $this->faker->randomElement([30, 60, 90]),
            'is_active' => true,
        ];
    }
}
