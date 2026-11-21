<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    public function processCheckout(Request $request)
    {
        // Validasi input alamat pengiriman dan items dari frontend
        $request->validate([
            'shipping_address' => 'required|string|min:5',
            'items' => 'required|array|min:1',
        ]);

        $user = Auth::user();
        $cartItems = $request->items; // Mengambil data item dari localStorage via AJAX

        if (empty($cartItems)) {
            return response()->json([
                'success' => false,
                'message' => 'Keranjang belanja Anda kosong!'
            ], 400);
        }

        // Hitung total harga
        $totalPrice = 0;
        foreach ($cartItems as $item) {
            $price = $item['price'];
            $qty = $item['qty'];
            $totalPrice += $price * $qty;
        }

        DB::beginTransaction();
        try {
            // 1. Simpan ke tabel orders
            $order = Order::create([
                'user_id' => $user ? $user->id : null,
                'customer_name' => $user ? $user->name : 'Tamu',
                'total_price' => $totalPrice,
                'shipping_address' => $request->shipping_address,
                'status' => 'pending',
            ]);

            // 2. String untuk pesan WhatsApp
            $waText = "Halo *Sport Mania Distro*! \nSaya ingin memproses pesanan saya.\n\n";
            $waText .= "*No. Invoice:* #ORD-" . str_pad($order->id, 4, '0', STR_PAD_LEFT) . "\n";
            $waText .= "*Nama Pemesan:* " . ($user ? $user->name : 'Tamu') . "\n";
            $waText .= "*Alamat Pengiriman:* " . $request->shipping_address . "\n\n";
            $waText .= "*Detail Pesanan:*\n";

            // 3. Simpan ke tabel order_items & kurangi stok produk
            foreach ($cartItems as $index => $item) {
                $price = $item['price'];
                $qty = $item['qty'];
                $productName = $item['name'] ?? 'Produk';
                $size = $item['size'] ?? '-';
                $color = $item['color'] ?? '-';
                
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['id'] ?? null,
                    'quantity' => $qty,
                    'price' => $price,
                ]);

                // Opsional: Kurangi stok produk langsung (jika tabel produk punya relasi/kolom varian/stok)
                // if(isset($item['id'])) {
                //     Product::where('id', $item['id'])->decrement('stock', $qty);
                // }

                // Tambahkan detail ke format pesan WA
                $waText .= ($index + 1) . ". " . $productName . " (" . $size . " - " . $color . ")\n";
                $waText .= "   " . $qty . " x Rp " . number_format($price, 0, ',', '.') . " = *Rp " . number_format($price * $qty, 0, ',', '.') . "*\n\n";
            }

            $waText .= "\n*Total Tagihan:* *Rp " . number_format($totalPrice, 0, ',', '.') . "*\n\n";
            $waText .= "Mohon informasi untuk proses pembayaran. Terima kasih!";

            DB::commit();

            // 4. Return response JSON ke frontend agar bisa membuka WhatsApp otomatis
            return response()->json([
                'success' => true,
                'message' => 'Pesanan berhasil disimpan ke database!',
                'invoice' => '#ORD-' . str_pad($order->id, 4, '0', STR_PAD_LEFT),
                'wa_text' => $waText
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()
            ], 500);
        }
    }
    public function checkoutPage()
    {
        return view('customer.checkout');
    }
}