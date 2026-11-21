<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Akun Admin & Customer Uji Coba
        $admin = User::updateOrCreate(
            ['email' => 'admin@sportmania.id'],
            [
                'name' => 'Admin Sport Mania',
                'password' => Hash::make('password123'),
                'role' => 'admin',
            ]
        );

        $customer = User::updateOrCreate(
            ['email' => 'customer@sportmania.id'],
            [
                'name' => 'Pelanggan Setia',
                'password' => Hash::make('password123'),
                'role' => 'customer',
            ]
        );

        // 2. Kategori Produk Olahraga
        $categories = [
            'Pakaian' => Category::firstOrCreate(['name' => 'Pakaian'], ['slug' => 'pakaian']),
            'Tas' => Category::firstOrCreate(['name' => 'Tas'], ['slug' => 'tas']),
            'Sepatu' => Category::firstOrCreate(['name' => 'Sepatu'], ['slug' => 'sepatu']),
            'Aksesoris' => Category::firstOrCreate(['name' => 'Aksesoris'], ['slug' => 'aksesoris']),
        ];

        // 3. Katalog Produk & Varian Stok
        $productsData = [
            [
                'name' => 'Jersey Adidas Aeroready Pro',
                'category_id' => $categories['Pakaian']->id,
                'price' => 250000,
                'discount_price' => 199000,
                'description' => 'Jersey olahraga bahan dry-fit ringan dan menyerap keringat maksimal untuk futsal maupun lari.',
                'variants' => [
                    ['size' => 'S', 'color' => 'Hijau', 'stock' => 0],
                    ['size' => 'M', 'color' => 'Hijau', 'stock' => 12],
                    ['size' => 'L', 'color' => 'Hitam', 'stock' => 8],
                    ['size' => 'XL', 'color' => 'Hijau', 'stock' => 0],
                ],
            ],
            [
                'name' => 'Ransel Adidas APWR Training',
                'category_id' => $categories['Tas']->id,
                'price' => 350000,
                'discount_price' => null,
                'description' => 'Tas punggung sport berkapasitas besar dengan kompartemen khusus sepatu dan botol minum.',
                'variants' => [
                    ['size' => 'All Size', 'color' => 'Hitam', 'stock' => 4],
                    ['size' => 'All Size', 'color' => 'Navy', 'stock' => 3],
                ],
            ],
            [
                'name' => 'Sepatu Lari Nike Zoom Pegasus',
                'category_id' => $categories['Sepatu']->id,
                'price' => 750000,
                'discount_price' => 680000,
                'description' => 'Sepatu lari responsif dengan bantalan empuk untuk jarak pendek hingga half-marathon.',
                'variants' => [
                    ['size' => '40', 'color' => 'Putih/Merah', 'stock' => 6],
                    ['size' => '41', 'color' => 'Hitam', 'stock' => 10],
                    ['size' => '42', 'color' => 'Hitam', 'stock' => 9],
                    ['size' => '43', 'color' => 'Putih/Merah', 'stock' => 2],
                ],
            ],
            [
                'name' => 'Celana Pendek Training Dry-Fit',
                'category_id' => $categories['Pakaian']->id,
                'price' => 145000,
                'discount_price' => 120000,
                'description' => 'Celana pendek olahraga lentur dengan saku resleting aman di sisi kanan dan kiri.',
                'variants' => [
                    ['size' => 'M', 'color' => 'Hitam', 'stock' => 15],
                    ['size' => 'L', 'color' => 'Abu-abu', 'stock' => 14],
                    ['size' => 'XL', 'color' => 'Hitam', 'stock' => 7],
                ],
            ],
            [
                'name' => 'Kaos Kaki Sport Anti-Slip Pro',
                'category_id' => $categories['Aksesoris']->id,
                'price' => 55000,
                'discount_price' => null,
                'description' => 'Kaos kaki grip anti-selip di bagian telapak untuk stabilitas akselerasi di lapangan.',
                'variants' => [
                    ['size' => 'All Size', 'color' => 'Putih', 'stock' => 25],
                    ['size' => 'All Size', 'color' => 'Hitam', 'stock' => 20],
                ],
            ],
        ];

        $createdProducts = [];
        foreach ($productsData as $item) {
            $totalStock = collect($item['variants'])->sum('stock');
            $product = Product::create([
                'name' => $item['name'],
                'slug' => Str::slug($item['name']) . '-' . Str::random(4),
                'category_id' => $item['category_id'],
                'price' => $item['price'],
                'discount_price' => $item['discount_price'],
                'stock' => $totalStock,
                'description' => $item['description'],
            ]);

            foreach ($item['variants'] as $v) {
                ProductVariant::create([
                    'product_id' => $product->id,
                    'size' => $v['size'],
                    'color' => $v['color'],
                    'stock' => $v['stock'],
                ]);
            }

            $createdProducts[] = $product;
        }

        // 4. Dummy Pesanan (Bulan Ini & Bulan Lalu) untuk Uji Dashboard & Rekap Bulanan
        $sampleOrders = [
            [
                'code' => 'ORD-' . strtoupper(Str::random(12)),
                'status' => 'SELESAI',
                'payment_method' => 'Transfer Bank',
                'courier' => 'JNE Reguler',
                'shipping_cost' => 0,
                'total_price' => 700000,
                'tracking_number' => 'JNE8829104421',
                'created_at' => Carbon::now()->subDays(2),
            ],
            [
                'code' => 'ORD-' . strtoupper(Str::random(12)),
                'status' => 'DIPROSES',
                'payment_method' => 'QRIS',
                'courier' => 'SiCepat BEST',
                'shipping_cost' => 20000,
                'total_price' => 418000,
                'tracking_number' => null,
                'created_at' => Carbon::now()->subDay(),
            ],
            [
                'code' => 'ORD-' . strtoupper(Str::random(12)),
                'status' => 'PENDING',
                'payment_method' => 'Transfer Bank',
                'courier' => 'JNE Reguler',
                'shipping_cost' => 0,
                'total_price' => 680000,
                'tracking_number' => null,
                'created_at' => Carbon::now()->subHours(3),
            ],
            [
                'code' => 'ORD-' . strtoupper(Str::random(12)),
                'status' => 'SELESAI',
                'payment_method' => 'COD',
                'courier' => 'Ambil di Store',
                'shipping_cost' => 0,
                'total_price' => 549000,
                'tracking_number' => 'STORE-PICKUP',
                'created_at' => Carbon::now()->subMonth()->startOfMonth()->addDays(10),
            ],
        ];

        foreach ($sampleOrders as $idx => $ord) {
            $order = Order::create([
                'user_id' => $customer->id,
                'order_code' => $ord['code'],
                'status' => $ord['status'],
                'payment_method' => $ord['payment_method'],
                'courier' => $ord['courier'],
                'shipping_cost' => $ord['shipping_cost'],
                'total_price' => $ord['total_price'],
                'shipping_address' => 'Jl. Veteran No. 12, Lowokwaru, Kota Malang, Jawa Timur',
                'tracking_number' => $ord['tracking_number'],
                'created_at' => $ord['created_at'],
                'updated_at' => $ord['created_at'],
            ]);

            $prod = $createdProducts[$idx % count($createdProducts)];
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $prod->id,
                'size' => 'L',
                'color' => 'Hitam',
                'qty' => 2,
                'price' => $prod->discount_price ?? $prod->price,
            ]);
        }
    }
}