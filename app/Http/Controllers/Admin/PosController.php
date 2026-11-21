<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PosController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'items_json'     => 'required|string',
            'customer_name'  => 'required|string|max:120',
            'phone'          => 'nullable|string|max:30',
            'payment_method' => 'required|string|max:50',
            'subtotal'       => 'required|numeric|min:0',
            'discount'       => 'nullable|numeric|min:0',
            'total_price'    => 'required|numeric|min:0',
            'cash_received'  => 'nullable|numeric|min:0',
            'cash_change'    => 'nullable|numeric|min:0',
        ]);

        $items = json_decode($request->items_json, true);

        if (!is_array($items) || count($items) === 0) {
            return back()->with('error', 'Keranjang kasir masih kosong.');
        }

        try {
            $order = DB::transaction(function () use ($request, $items) {
                $subtotal = 0;
                $validatedItems = [];

                foreach ($items as $item) {
                    $qty = max(1, (int) ($item['qty'] ?? 1));
                    $productId = (int) ($item['product_id'] ?? 0);
                    $variantId = !empty($item['variant_id']) ? (int) $item['variant_id'] : null;

                    $product = Product::lockForUpdate()->findOrFail($productId);

                    // Hanya gunakan harga diskon jika bernilai > 0 (aman dari nilai 0.00)
                    $unitPrice = ($product->discount_price !== null && (float) $product->discount_price > 0)
                        ? (float) $product->discount_price
                        : (float) ($product->price ?? 0);

                    // Single Source of Truth stok berada di product_variants (3NF)
                    if (!$variantId) {
                        throw new \Exception("Varian untuk produk {$product->name} wajib dipilih sebelum transaksi.");
                    }

                    $variant = ProductVariant::where('product_id', $product->id)
                        ->lockForUpdate()
                        ->findOrFail($variantId);

                    if ((int) $variant->stock < $qty) {
                        throw new \Exception("Stok {$product->name} ({$variant->size}/{$variant->color}) tidak mencukupi. Sisa: {$variant->stock}.");
                    }

                    $variant->decrement('stock', $qty);

                    $lineSubtotal = $unitPrice * $qty;
                    $subtotal += $lineSubtotal;

                    $validatedItems[] = [
                        'product'   => $product,
                        'variant'   => $variant,
                        'qty'       => $qty,
                        'price'     => $unitPrice,
                        'subtotal'  => $lineSubtotal,
                        'size'      => $item['size'] ?? ($variant->size ?? 'All Size'),
                        'color'     => $item['color'] ?? ($variant->color ?? '-'),
                    ];
                }

                $discount = min($subtotal, max(0, (float) ($request->discount ?? 0)));
                $grandTotal = max(0, $subtotal - $discount);

                if (strtoupper($request->payment_method) === 'TUNAI') {
                    $cashReceived = (float) ($request->cash_received ?? 0);
                    if ($cashReceived < $grandTotal) {
                        throw new \Exception('Nominal uang tunai yang diterima kurang dari total tagihan.');
                    }
                }

                $orderCode = 'POS-' . now()->format('Ymd') . '-' . strtoupper(Str::random(4));
                $orderCols = Schema::getColumnListing('orders');

                $orderData = [];

                // Kode Order / Invoice
                if (in_array('order_number', $orderCols, true)) {
                    $orderData['order_number'] = $orderCode;
                } elseif (in_array('invoice_number', $orderCols, true)) {
                    $orderData['invoice_number'] = $orderCode;
                }

                // Relasi User (fallback ke akun admin/kasir yang sedang bertugas)
                if (in_array('user_id', $orderCols, true)) {
                    $orderData['user_id'] = Auth::id();
                }

                // Nama Pembeli
                if (in_array('recipient_name', $orderCols, true)) {
                    $orderData['recipient_name'] = $request->customer_name;
                } elseif (in_array('customer_name', $orderCols, true)) {
                    $orderData['customer_name'] = $request->customer_name;
                } elseif (in_array('name', $orderCols, true)) {
                    $orderData['name'] = $request->customer_name;
                }

                // Kontak
                if (in_array('phone', $orderCols, true)) {
                    $orderData['phone'] = $request->phone ?: '-';
                } elseif (in_array('whatsapp', $orderCols, true)) {
                    $orderData['whatsapp'] = $request->phone ?: '-';
                }

                // Alamat & Pengiriman Offline Toko
                if (in_array('shipping_method', $orderCols, true)) {
                    $orderData['shipping_method'] = 'pos';
                }
                if (in_array('shipping_address', $orderCols, true)) {
                    $orderData['shipping_address'] = 'Transaksi Langsung di Store Malang';
                } elseif (in_array('address', $orderCols, true)) {
                    $orderData['address'] = 'Transaksi Langsung di Store Malang';
                }
                if (in_array('city', $orderCols, true)) {
                    $orderData['city'] = 'Malang';
                }

                // Nominal Harga
                if (in_array('subtotal', $orderCols, true)) {
                    $orderData['subtotal'] = $subtotal;
                }
                if (in_array('discount_amount', $orderCols, true)) {
                    $orderData['discount_amount'] = $discount;
                } elseif (in_array('discount', $orderCols, true)) {
                    $orderData['discount'] = $discount;
                }
                if (in_array('shipping_cost', $orderCols, true)) {
                    $orderData['shipping_cost'] = 0;
                }
                if (in_array('total_price', $orderCols, true)) {
                    $orderData['total_price'] = $grandTotal;
                } elseif (in_array('total_amount', $orderCols, true)) {
                    $orderData['total_amount'] = $grandTotal;
                } elseif (in_array('total', $orderCols, true)) {
                    $orderData['total'] = $grandTotal;
                }

                // Pembayaran & Status Langsung Lunas + Selesai
                if (in_array('payment_method', $orderCols, true)) {
                    $orderData['payment_method'] = strtoupper($request->payment_method);
                }
                if (in_array('payment_status', $orderCols, true)) {
                    $orderData['payment_status'] = 'paid';
                }
                if (in_array('payment_verified_at', $orderCols, true)) {
                    $orderData['payment_verified_at'] = now();
                }
                if (in_array('status', $orderCols, true)) {
                    $orderData['status'] = 'selesai';
                }

                // Catatan Kembalian Tunai
                $notes = 'Transaksi Kasir Offline (Kasir: ' . (Auth::user()->name ?? 'Admin') . ')';
                if (strtoupper($request->payment_method) === 'TUNAI') {
                    $notes .= ' | Tunai: Rp ' . number_format((float) $request->cash_received, 0, ',', '.')
                            . ' | Kembali: Rp ' . number_format((float) $request->cash_change, 0, ',', '.');
                }
                if (in_array('notes', $orderCols, true)) {
                    $orderData['notes'] = $notes;
                }

                $createdOrder = Order::create($orderData);

                // Simpan Detail Item ke order_items
                $itemCols = Schema::getColumnListing('order_items');
                foreach ($validatedItems as $vItem) {
                    $itemData = [
                        'order_id'   => $createdOrder->id,
                        'product_id' => $vItem['product']->id,
                    ];

                    if (in_array('product_variant_id', $itemCols, true) && $vItem['variant']) {
                        $itemData['product_variant_id'] = $vItem['variant']->id;
                    }
                    if (in_array('product_name', $itemCols, true)) {
                        $itemData['product_name'] = $vItem['product']->name;
                    }
                    if (in_array('variant_size', $itemCols, true)) {
                        $itemData['variant_size'] = $vItem['size'];
                    } elseif (in_array('size', $itemCols, true)) {
                        $itemData['size'] = $vItem['size'];
                    }
                    if (in_array('variant_color', $itemCols, true)) {
                        $itemData['variant_color'] = $vItem['color'];
                    } elseif (in_array('color', $itemCols, true)) {
                        $itemData['color'] = $vItem['color'];
                    }
                    if (in_array('quantity', $itemCols, true)) {
                        $itemData['quantity'] = $vItem['qty'];
                    } elseif (in_array('qty', $itemCols, true)) {
                        $itemData['qty'] = $vItem['qty'];
                    }
                    if (in_array('price', $itemCols, true)) {
                        $itemData['price'] = $vItem['price'];
                    }
                    if (in_array('subtotal', $itemCols, true)) {
                        $itemData['subtotal'] = $vItem['subtotal'];
                    }

                    OrderItem::create($itemData);
                }

                return $createdOrder;
            });

            return back()
                ->with('success', 'Transaksi kasir berhasil disimpan! Struk siap dicetak.')
                ->with('pos_print_order_id', $order->id);

        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal memproses transaksi kasir: ' . $e->getMessage());
        }
    }
}