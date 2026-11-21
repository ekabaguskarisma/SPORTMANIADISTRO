<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class CustomerOrderController extends Controller
{
    public function index()
    {
        $orders = Order::with(['items.product'])
            ->where('user_id', Auth::id())
            ->latest()
            ->get();

        return view('customer.orders', compact('orders'));
    }

    public function uploadPaymentProof(Request $request, $id)
    {
        /** @var Order $order */
        $order = Order::findOrFail($id);
        abort_if((int) $order->user_id !== (int) Auth::id(), 403);

        $status = strtolower((string) ($order->status ?? 'pending'));
        $paymentMethod = strtolower((string) ($order->payment_method ?? ''));
        $paymentStatus = strtolower((string) ($order->payment_status ?? ''));

        if ($paymentMethod === 'cod' || in_array($status, ['dibatalkan', 'batal'], true) || $paymentStatus === 'paid') {
            return back()->with('error', 'Pesanan ini tidak memerlukan atau sudah menyelesaikan verifikasi pembayaran.');
        }

        $request->validate([
            'payment_proof' => 'required|image|mimes:jpg,jpeg,png,webp|max:4096',
        ], [
            'payment_proof.required' => 'Silakan pilih foto bukti pembayaran terlebih dahulu.',
            'payment_proof.image'    => 'File bukti pembayaran harus berupa gambar (JPG, PNG, WEBP).',
            'payment_proof.mimes'    => 'Format gambar harus JPG, JPEG, PNG, atau WEBP.',
            'payment_proof.max'      => 'Ukuran foto bukti pembayaran maksimal 4 MB.',
        ]);

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        // Hapus file bukti lama jika pelanggan mengganti foto
        if (!empty($order->payment_proof) && !filter_var($order->payment_proof, FILTER_VALIDATE_URL)) {
            $disk->delete($order->payment_proof);
        }

        $path = $request->file('payment_proof')->store('payment-proofs', 'public');

        $columns = Schema::getColumnListing('orders');
        $updateData = [];

        if (in_array('payment_proof', $columns, true)) {
            $updateData['payment_proof'] = $path;
        }
        if (in_array('payment_status', $columns, true)) {
            $updateData['payment_status'] = 'pending_verification';
        }
        if (in_array('payment_rejected_reason', $columns, true)) {
            $updateData['payment_rejected_reason'] = null;
        }

        $order->update($updateData);

        return back()->with('success', 'Bukti pembayaran berhasil diunggah dan sedang menunggu verifikasi admin.');
    }

    public function cancel($id)
    {
        /** @var Order $orderCheck */
        $orderCheck = Order::findOrFail($id);
        abort_if((int) $orderCheck->user_id !== (int) Auth::id(), 403);

        if (strtolower((string) $orderCheck->status) !== 'pending') {
            return back()->with('error', 'Hanya pesanan berstatus pending yang dapat dibatalkan.');
        }

        $cancelled = DB::transaction(function () use ($id): bool {
            /** @var Order $order */
            $order = Order::with('items')->where('id', $id)->lockForUpdate()->firstOrFail();

            if (strtolower((string) $order->status) !== 'pending') {
                return false;
            }

            foreach ($order->items as $item) {
                $qty = max(1, (int) ($item->quantity ?? $item->qty ?? 1));
                $productId = $item->product_id;
                $variantId = $item->product_variant_id ?? $item->variant_id ?? null;
                $size = $item->variant_size ?? $item->size ?? null;
                $color = $item->variant_color ?? $item->color ?? null;

                /** @var Product|null $product */
                $product = Product::where('id', $productId)->lockForUpdate()->first();
                if (!$product) {
                    continue;
                }

                $hasVariants = ProductVariant::where('product_id', $productId)->exists();

                if ($hasVariants) {
                    /** @var ProductVariant|null $variant */
                    $variant = null;

                    if (!empty($variantId)) {
                        $variant = ProductVariant::where('id', $variantId)
                            ->where('product_id', $productId)
                            ->lockForUpdate()
                            ->first();
                    }

                    if (!$variant) {
                        $variant = ProductVariant::where('product_id', $productId)
                            ->when($size && $size !== 'All Size', fn ($q) => $q->where('size', $size))
                            ->when($color && $color !== '-', fn ($q) => $q->where('color', $color))
                            ->lockForUpdate()
                            ->first();
                    }

                    if ($variant) {
                        $variant->increment('stock', $qty);
                    }

                    if (array_key_exists('stock', $product->getAttributes()) && $product->stock !== null) {
                        $totalVariantStock = (int) ProductVariant::where('product_id', $productId)->sum('stock');
                        $product->update(['stock' => $totalVariantStock]);
                    }
                } elseif (array_key_exists('stock', $product->getAttributes()) && $product->stock !== null) {
                    $product->increment('stock', $qty);
                }
            }

            $order->update(['status' => 'dibatalkan']);

            return true;
        });

        if (!$cancelled) {
            return back()->with('error', 'Pesanan tidak dapat dibatalkan karena statusnya sudah berubah.');
        }

        return back()->with('success', 'Pesanan berhasil dibatalkan dan stok produk telah dikembalikan.');
    }

    public function complete($id)
    {
        /** @var Order $order */
        $order = Order::findOrFail($id);
        abort_if((int) $order->user_id !== (int) Auth::id(), 403);

        $status = strtolower((string) $order->status);
        $shippingMethod = strtolower((string) ($order->shipping_method ?? ''));

        $canComplete = $status === 'dikirim' || ($status === 'diproses' && $shippingMethod === 'pickup');
        if (!$canComplete) {
            return back()->with('error', 'Pesanan belum dapat dikonfirmasi selesai.');
        }

        $columns = Schema::getColumnListing('orders');
        $updateData = ['status' => 'selesai'];

        // Jika metode COD, otomatis tandai lunas saat barang diterima pelanggan
        if (strtolower((string) $order->payment_method) === 'cod' && in_array('payment_status', $columns, true)) {
            $updateData['payment_status'] = 'paid';
        }

        $order->update($updateData);

        return back()->with('success', 'Terima kasih! Pesanan telah dikonfirmasi selesai. Silakan beri ulasan produk.');
    }

    public function storeReview(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'order_id'   => 'required|exists:orders,id',
            'rating'     => 'required|integer|min:1|max:5',
            'comment'    => 'required|string|max:1000',
        ], [
            'rating.required'  => 'Rating bintang wajib dipilih.',
            'rating.min'       => 'Rating minimal 1 bintang.',
            'rating.max'       => 'Rating maksimal 5 bintang.',
            'comment.required' => 'Komentar ulasan tidak boleh kosong.',
            'comment.max'      => 'Komentar ulasan maksimal 1000 karakter.',
        ]);

        $cleanComment = trim((string) $request->comment);
        if ($cleanComment === '') {
            return back()->with('error', 'Komentar ulasan tidak boleh hanya berisi spasi.');
        }

        /** @var Order $order */
        $order = Order::with('items')
            ->where('id', $request->order_id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if (strtolower((string) $order->status) !== 'selesai') {
            return back()->with('error', 'Ulasan hanya dapat diberikan setelah pesanan selesai.');
        }

        $isProductInOrder = $order->items->contains(fn ($item) => (int) $item->product_id === (int) $request->product_id);
        if (!$isProductInOrder) {
            return back()->with('error', 'Produk tidak ditemukan dalam pesanan tersebut.');
        }

        $hasOrderIdColumn = Schema::hasColumn('reviews', 'order_id');

        $alreadyReviewed = Review::where('user_id', Auth::id())
            ->where('product_id', $request->product_id)
            ->when($hasOrderIdColumn, fn ($q) => $q->where('order_id', $order->id))
            ->exists();

        if ($alreadyReviewed) {
            return back()->with('error', 'Anda sudah memberikan ulasan untuk produk ini.');
        }

        $payload = [
            'user_id'    => Auth::id(),
            'product_id' => (int) $request->product_id,
            'rating'     => (int) $request->rating,
            'comment'    => $cleanComment,
        ];

        if ($hasOrderIdColumn) {
            $payload['order_id'] = $order->id;
        }

        Review::create($payload);

        return back()->with('success', 'Terima kasih! Ulasan Anda berhasil dikirim.');
    }
}