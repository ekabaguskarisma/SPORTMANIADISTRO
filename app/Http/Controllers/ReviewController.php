<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class ReviewController extends Controller
{
    public function store(Request $request)
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

        $order = Order::with('items')
            ->where('id', $request->order_id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if (strtolower((string) $order->status) !== 'selesai') {
            return back()->with('error', 'Ulasan hanya dapat diberikan setelah pesanan selesai.');
        }

        $isProductInOrder = $order->items->contains(
            fn ($item) => (int) $item->product_id === (int) $request->product_id
        );

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