<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class CheckoutController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $defaultPhone = $user->phone ?? '';

        // Gabungkan alamat, kota, dan kode pos dari profil jika tersedia
        $addressParts = array_filter([
            $user->address ?? null,
            $user->city ?? ($user->kota ?? null),
            $user->postal_code ?? ($user->kode_pos ?? null),
        ]);
        $defaultAddress = implode(', ', $addressParts);

        return view('customer.checkout', compact('defaultPhone', 'defaultAddress'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'            => 'required|string|max:255',
            'phone'           => ['required', 'string', 'regex:/^\+?[0-9\s\-\(\)]{8,20}$/'],
            'email'           => 'nullable|email|max:255',
            'address'         => 'required_unless:shipping_method,pickup|nullable|string|max:1000',
            'shipping_method' => 'required|string|in:jne,sicepat,pickup',
            'payment_method'  => 'required|string|in:transfer,qris,cod',
            'notes'           => 'nullable|string|max:500',
            'voucher_code'    => 'nullable|string|max:50',
            'cart_items'      => 'required',
            'payment_proof'   => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
        ], [
            'name.required'           => 'Nama lengkap wajib diisi.',
            'phone.required'          => 'Nomor WhatsApp wajib diisi.',
            'phone.regex'             => 'Format nomor WhatsApp/HP tidak valid. Masukkan 8 hingga 20 digit angka (boleh diawali tanda +).',
            'address.required_unless' => 'Alamat lengkap pengiriman wajib diisi.',
            'shipping_method.in'      => 'Metode pengiriman tidak valid.',
            'payment_method.in'       => 'Metode pembayaran tidak valid.',
            'payment_proof.image'     => 'File bukti pembayaran harus berupa gambar (JPG, PNG, WEBP).',
            'payment_proof.max'       => 'Ukuran gambar bukti pembayaran maksimal 4MB.',
        ]);

        $cartItems = is_string($request->cart_items)
            ? json_decode($request->cart_items, true)
            : $request->cart_items;

        if (!is_array($cartItems) || empty($cartItems)) {
            return response()->json([
                'success' => false,
                'message' => 'Keranjang belanja Anda kosong.',
            ], 422);
        }

        // Simpan file bukti pembayaran jika diunggah saat checkout (kecuali COD)
        $proofPath = null;
        if ($request->hasFile('payment_proof') && $request->payment_method !== 'cod') {
            $proofPath = $request->file('payment_proof')->store('payment-proofs', 'public');
        }

        DB::beginTransaction();
        try {
            $orderNumber = 'ORD-' . strtoupper(uniqid());
            $calculatedSubtotal = 0;
            $validatedItems = [];

            foreach ($cartItems as $item) {
                $qtyOrdered = max(1, (int) ($item['qty'] ?? 1));
                $productId  = $item['id'] ?? null;
                $variantId  = $item['variant_id'] ?? null;
                $size       = $item['size'] ?? null;
                $color      = $item['color'] ?? null;

                $product = Product::where('id', $productId)->lockForUpdate()->first();

                if (!$product) {
                    throw new \RuntimeException('Salah satu produk di keranjang tidak ditemukan atau sudah dihapus.');
                }

                $hasVariants = ProductVariant::where('product_id', $productId)->exists();
                $variant = null;

                if ($hasVariants) {
                    if (!empty($variantId)) {
                        $variant = ProductVariant::where('id', $variantId)
                            ->where('product_id', $productId)
                            ->lockForUpdate()
                            ->first();
                    }

                    if (!$variant) {
                        $variantQuery = ProductVariant::where('product_id', $productId)
                            ->when($size && $size !== 'All Size', fn($q) => $q->where('size', $size))
                            ->when($color && $color !== '-', fn($q) => $q->where('color', $color));

                        if ((!$size || $size === 'All Size') && (!$color || $color === '-')) {
                            $variantQuery->orderByDesc('stock');
                        }

                        $variant = $variantQuery->lockForUpdate()->first();
                    }

                    if (!$variant) {
                        throw new \RuntimeException("Varian untuk produk {$product->name} tidak ditemukan.");
                    }

                    if ($variant->stock < $qtyOrdered) {
                        $varianLabel = trim(($variant->size ?? $size) . ' ' . ($variant->color ?? $color));
                        throw new \RuntimeException(
                            "Stok {$product->name} ({$varianLabel}) tidak mencukupi. Sisa stok: {$variant->stock} pcs."
                        );
                    }

                    $variant->decrement('stock', $qtyOrdered);

                    // Sinkronkan total stok produk induk dengan sisa seluruh variannya
                    if (array_key_exists('stock', $product->getAttributes()) && $product->stock !== null) {
                        $totalVariantStock = (int) ProductVariant::where('product_id', $productId)->sum('stock');
                        $product->update(['stock' => $totalVariantStock]);
                    }

                    $size  = $variant->size ?? $size;
                    $color = $variant->color ?? $color;
                } else {
                    if (array_key_exists('stock', $product->getAttributes()) && $product->stock !== null) {
                        if ($product->stock < $qtyOrdered) {
                            throw new \RuntimeException(
                                "Stok {$product->name} tidak mencukupi. Sisa stok: {$product->stock} pcs."
                            );
                        }
                        $product->decrement('stock', $qtyOrdered);
                    }
                }

                $hargaSatuan = ($product->discount_price !== null && (float) $product->discount_price > 0)
                    ? (float) $product->discount_price
                    : (float) $product->price;
                $calculatedSubtotal += $hargaSatuan * $qtyOrdered;

                $validatedItems[] = [
                    'product_id'   => $product->id,
                    'variant_id'   => $variant?->id,
                    'product_name' => $product->name,
                    'size'         => $size ?: 'All Size',
                    'color'        => $color ?: '-',
                    'price'        => $hargaSatuan,
                    'quantity'     => $qtyOrdered,
                ];
            }

            $subtotal = $calculatedSubtotal;
            if ($request->shipping_method === 'pickup' || $subtotal >= 250000) {
                $shippingCost = 0;
            } else {
                $shippingCost = $request->shipping_method === 'sicepat' ? 25000 : 20000;
            }

            // ==========================================
            // VALIDASI & HITUNG VOUCHER DI SERVER
            // ==========================================
            $appliedVoucherCode = null;
            $discountAmount = 0;

            if ($request->filled('voucher_code') && Schema::hasTable('vouchers') && class_exists(\App\Models\Voucher::class)) {
                $rawCode = strtoupper(trim((string) $request->voucher_code));
                $voucher = \App\Models\Voucher::whereRaw('UPPER(code) = ?', [$rawCode])
                    ->lockForUpdate()
                    ->first();

                if (!$voucher) {
                    throw new \RuntimeException("Kode voucher '{$rawCode}' tidak ditemukan.");
                }

                if (isset($voucher->is_active) && !$voucher->is_active) {
                    throw new \RuntimeException("Kode voucher '{$rawCode}' sedang tidak aktif.");
                }

                $expiredField = $voucher->expires_at ?? $voucher->valid_until ?? null;
                if ($expiredField && Carbon::parse($expiredField)->isPast()) {
                    throw new \RuntimeException("Kode voucher '{$rawCode}' sudah kedaluwarsa.");
                }

                $minPurchase = (float) ($voucher->min_purchase ?? $voucher->min_order ?? 0);
                if ($minPurchase > 0 && $subtotal < $minPurchase) {
                    throw new \RuntimeException(
                        "Minimal belanja Rp " . number_format($minPurchase, 0, ',', '.') . " untuk menggunakan voucher '{$rawCode}'."
                    );
                }

                // Cek kuota / batas penggunaan
                if (isset($voucher->usage_limit) && $voucher->usage_limit !== null && (int) ($voucher->used_count ?? 0) >= (int) $voucher->usage_limit) {
                    throw new \RuntimeException("Kuota voucher '{$rawCode}' sudah habis.");
                }
                if (isset($voucher->quota) && $voucher->quota !== null && (int) $voucher->quota <= 0) {
                    throw new \RuntimeException("Kuota voucher '{$rawCode}' sudah habis.");
                }

                $type = strtolower((string) ($voucher->type ?? $voucher->discount_type ?? 'fixed'));
                $value = (float) ($voucher->value ?? $voucher->discount_value ?? $voucher->amount ?? 0);

                if (in_array($type, ['percent', 'percentage', 'persen'], true)) {
                    $discountAmount = round($subtotal * ($value / 100));
                    $maxDiscount = (float) ($voucher->max_discount ?? 0);
                    if ($maxDiscount > 0 && $discountAmount > $maxDiscount) {
                        $discountAmount = $maxDiscount;
                    }
                } else {
                    $discountAmount = $value;
                }

                $discountAmount = min($subtotal, max(0, $discountAmount));
                $appliedVoucherCode = $voucher->code;

                // Update penggunaan / sisa kuota voucher
                $voucherColumns = Schema::getColumnListing('vouchers');
                if (in_array('used_count', $voucherColumns, true)) {
                    $voucher->increment('used_count');
                }
                if (in_array('quota', $voucherColumns, true) && $voucher->quota !== null) {
                    $voucher->decrement('quota');
                }
            }

            $total = max(0, $subtotal - $discountAmount + $shippingCost);

            $alamatPengiriman = $request->shipping_method === 'pickup'
                ? 'Ambil langsung di Store Malang'
                : trim((string) $request->address);

            $orderColumns = Schema::getColumnListing('orders');
            $orderPayload = [
                'user_id'         => Auth::id(),
                'order_number'    => $orderNumber,
                'name'            => trim($request->name),
                'phone'           => trim($request->phone),
                'email'           => $request->email ? trim($request->email) : null,
                'address'         => $alamatPengiriman,
                'shipping_method' => $request->shipping_method,
                'payment_method'  => $request->payment_method,
                'subtotal'        => $subtotal,
                'shipping_cost'   => $shippingCost,
                'total_amount'    => $total,
                'status'          => 'pending',
                'notes'           => $request->notes ? trim($request->notes) : null,
            ];

            if (in_array('voucher_code', $orderColumns, true)) {
                $orderPayload['voucher_code'] = $appliedVoucherCode;
            }
            if (in_array('discount_amount', $orderColumns, true)) {
                $orderPayload['discount_amount'] = $discountAmount;
            }

            if ($proofPath && in_array('payment_proof', $orderColumns, true)) {
                $orderPayload['payment_proof'] = $proofPath;
            }
            if (in_array('payment_status', $orderColumns, true)) {
                if ($request->payment_method === 'cod') {
                    $orderPayload['payment_status'] = 'cod';
                } elseif ($proofPath) {
                    $orderPayload['payment_status'] = 'pending_verification';
                } else {
                    $orderPayload['payment_status'] = 'unpaid';
                }
            }

            $order = Order::create($orderPayload);

            // Simpan otomatis No. HP & Alamat ke profil user jika masih kosong
            $user = Auth::user();
            if ($user) {
                $userColumns = Schema::getColumnListing('users');
                $userUpdated = false;

                if (in_array('phone', $userColumns, true) && empty($user->phone)) {
                    $user->phone = trim($request->phone);
                    $userUpdated = true;
                }
                if (in_array('address', $userColumns, true) && empty($user->address) && $request->shipping_method !== 'pickup') {
                    $user->address = trim((string) $request->address);
                    $userUpdated = true;
                }
                if ($userUpdated) {
                    $user->save();
                }
            }

            $orderItemColumns = Schema::getColumnListing('order_items');

            foreach ($validatedItems as $vItem) {
                $itemPayload = [
                    'order_id'   => $order->id,
                    'product_id' => $vItem['product_id'],
                    'price'      => $vItem['price'],
                    'quantity'   => $vItem['quantity'],
                ];

                if (in_array('product_variant_id', $orderItemColumns, true) && !empty($vItem['variant_id'])) {
                    $itemPayload['product_variant_id'] = $vItem['variant_id'];
                }
                if (in_array('variant_id', $orderItemColumns, true) && !empty($vItem['variant_id'])) {
                    $itemPayload['variant_id'] = $vItem['variant_id'];
                }
                if (in_array('product_name', $orderItemColumns, true)) {
                    $itemPayload['product_name'] = $vItem['product_name'];
                }
                if (in_array('size', $orderItemColumns, true)) {
                    $itemPayload['size'] = $vItem['size'];
                }
                if (in_array('variant_size', $orderItemColumns, true)) {
                    $itemPayload['variant_size'] = $vItem['size'];
                }
                if (in_array('color', $orderItemColumns, true)) {
                    $itemPayload['color'] = $vItem['color'];
                }
                if (in_array('variant_color', $orderItemColumns, true)) {
                    $itemPayload['variant_color'] = $vItem['color'];
                }

                OrderItem::create($itemPayload);
            }

            DB::commit();

            return response()->json([
                'success'         => true,
                'order_number'    => $orderNumber,
                'voucher_code'    => $appliedVoucherCode,
                'discount_amount' => $discountAmount,
                'total'           => $total,
                'redirect'        => route('customer.orders'),
                'message'         => 'Pesanan berhasil dibuat dan stok diperbarui!',
            ]);
        } catch (\RuntimeException $e) {
            DB::rollBack();
            if ($proofPath) {
                Storage::disk('public')->delete($proofPath);
            }
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            DB::rollBack();
            if ($proofPath) {
                Storage::disk('public')->delete($proofPath);
            }
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server: ' . $e->getMessage(),
            ], 500);
        }
    }
}