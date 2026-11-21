<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\ProductVariant;

class ProductSeeder extends Seeder
{
    public function run()
    {
        // 1. Kategori: Pakaian
        $jersey = Product::create([
            'category_id' => 1, 
            'name' => 'Jersey Home 2026',
            'description' => 'Bahan dri-fit premium yang menyerap keringat dengan cepat. Sangat cocok untuk olahraga intensitas tinggi.',
            'price' => 250000,
            'is_featured' => true,
        ]);
        // Varian Jersey
        ProductVariant::create(['product_id' => $jersey->id, 'size' => 'M', 'color' => 'Merah', 'stock' => 10]);
        ProductVariant::create(['product_id' => $jersey->id, 'size' => 'L', 'color' => 'Merah', 'stock' => 14]);

        // 2. Kategori: Sepatu
        $sepatu = Product::create([
            'category_id' => 2, 
            'name' => 'SpeedRunner Max',
            'description' => 'Sepatu lari super ringan dengan bantalan empuk untuk melindungi lutut saat berlari jarak jauh.',
            'price' => 850000,
        ]);
        // Varian Sepatu
        ProductVariant::create(['product_id' => $sepatu->id, 'size' => '41', 'color' => 'Hitam', 'stock' => 5]);
        ProductVariant::create(['product_id' => $sepatu->id, 'size' => '42', 'color' => 'Hitam', 'stock' => 7]);

        // 3. Kategori: Tas (Tanpa Ukuran)
        $tas = Product::create([
            'category_id' => 3, 
            'name' => 'Gym Duffle Pro',
            'description' => 'Tas olahraga kapasitas besar dengan kompartemen khusus sepatu anti bau dan kantong botol minum.',
            'price' => 320000,
        ]);
        // Varian Tas
        ProductVariant::create(['product_id' => $tas->id, 'size' => null, 'color' => 'Hitam', 'stock' => 8]);

        // 4. Kategori: Aksesoris (All Size)
        $aksesoris = Product::create([
            'category_id' => 4, 
            'name' => 'Sarung Tangan Fit',
            'description' => 'Sarung tangan anti slip untuk latihan angkat beban. Melindungi telapak tangan dari kapalan.',
            'price' => 85000,
        ]);
        // Varian Aksesoris
        ProductVariant::create(['product_id' => $aksesoris->id, 'size' => 'All Size', 'color' => 'Hitam Merah', 'stock' => 50]);
    }
}