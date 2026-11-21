<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Voucher;
use Illuminate\Http\Request;

class StoreFeatureController extends Controller
{
    // API Validasi Kode Voucher via Fetch/AJAX saat Checkout
    public function applyVoucher(Request $request)
    {
        $request->validate([
            'code' => 'required|string|max:50',
            'subtotal' => 'required|numeric|min:0',
        ]);

        $voucher = Voucher::where('code', strtoupper(trim($request->code)))->first();

        if (!$voucher) {
            return response()->json([
                'valid' => false,
                'message' => 'Kode voucher tidak ditemukan.'
            ], 404);
        }

        $result = $voucher->calculateDiscount((float) $request->subtotal);

        return response()->json($result, $result['valid'] ? 200 : 422);
    }

    // Halaman & Proses Lacak Pesanan Publik (Tanpa Login)
    public function trackOrder(Request $request)
    {
        $order = null;
        $searched = false;

        if ($request->filled('order_code') && $request->filled('phone')) {
            $searched = true;
            $cleanCode = ltrim(strtoupper(trim($request->order_code)), '#');
            $cleanPhone = preg_replace('/[^0-9]/', '', $request->phone);
            $phoneTail = substr($cleanPhone, -8); // Cocokkan 8 digit terakhir agar fleksibel 08 / 628

            $order = Order::with(['items.product', 'user'])
                ->where(function ($q) use ($cleanCode) {
                    $q->where('order_number', 'LIKE', "%{$cleanCode}%")
                      ->orWhere('id', $cleanCode);
                })
                ->where(function ($q) use ($phoneTail) {
                    $q->where('phone', 'LIKE', "%{$phoneTail}%")
                      ->orWhereHas('user', fn($u) => $u->where('phone', 'LIKE', "%{$phoneTail}%"));
                })
                ->first();
        }

        return view('track-order', compact('order', 'searched'));
    }

    // Admin: Simpan Voucher Baru
    public function storeVoucher(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|max:30|unique:vouchers,code',
            'type' => 'required|in:fixed,percent',
            'value' => 'required|numeric|min:1',
            'min_purchase' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'quota' => 'required|integer|min:1',
            'expires_at' => 'nullable|date',
        ]);

        $data['code'] = strtoupper(trim($data['code']));
        $data['min_purchase'] = $data['min_purchase'] ?? 0;
        $data['is_active'] = true;

        Voucher::create($data);

        return back()->with('success', 'Kode voucher ' . $data['code'] . ' berhasil ditambahkan!');
    }

    // Admin: Hapus Voucher
    public function destroyVoucher(Voucher $voucher)
    {
        $voucher->delete();
        return back()->with('success', 'Voucher berhasil dihapus.');
    }

    // Admin: Toggle Status Aktif Voucher
    public function toggleVoucher(Voucher $voucher)
    {
        $voucher->update(['is_active' => !$voucher->is_active]);
        return back()->with('success', 'Status voucher ' . $voucher->code . ' berhasil diperbarui.');
    }
}