<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrderController extends Controller
{
    private function ensureAdmin(): void
    {
        if (!Auth::check() || strtolower((string) (Auth::user()->role ?? '')) !== 'admin') {
            abort(403, 'Akses khusus Admin.');
        }
    }

    public function index(Request $request)
    {
        $this->ensureAdmin();

        $query = Order::with(['user', 'items.product'])->latest();

        if ($request->filled('status')) {
            $query->where('status', strtolower(trim((string) $request->status)));
        }

        if ($request->filled('payment_status') && Schema::hasColumn('orders', 'payment_status')) {
            $query->where('payment_status', strtolower(trim((string) $request->payment_status)));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                if (Schema::hasColumn('orders', 'order_number')) {
                    $q->orWhere('order_number', 'like', "%{$search}%");
                }
                if (Schema::hasColumn('orders', 'invoice_number')) {
                    $q->orWhere('invoice_number', 'like', "%{$search}%");
                }
                if (Schema::hasColumn('orders', 'name')) {
                    $q->orWhere('name', 'like', "%{$search}%");
                }
                if (Schema::hasColumn('orders', 'customer_name')) {
                    $q->orWhere('customer_name', 'like', "%{$search}%");
                }
                if (Schema::hasColumn('orders', 'phone')) {
                    $q->orWhere('phone', 'like', "%{$search}%");
                }
                if (Schema::hasColumn('orders', 'tracking_number')) {
                    $q->orWhere('tracking_number', 'like', "%{$search}%");
                }
                $q->orWhereHas('user', function ($uq) use ($search) {
                    $uq->where('name', 'like', "%{$search}%")
                       ->orWhere('email', 'like', "%{$search}%");
                });
            });
        }

        $orders = $query->paginate(15)->withQueryString();

        return view('admin.orders.index', compact('orders'));
    }

    public function show($id)
    {
        $this->ensureAdmin();

        $order = Order::with(['user', 'items.product'])->findOrFail($id);
        return view('admin.orders.show', compact('order'));
    }

    public function verifyPayment(Request $request, $id)
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'action'           => 'required|in:approve,reject',
            'rejection_reason' => 'required_if:action,reject|nullable|string|max:500',
        ], [
            'rejection_reason.required_if' => 'Alasan penolakan wajib diisi jika bukti pembayaran ditolak.',
        ]);

        DB::transaction(function () use ($validated, $id) {
            $order = Order::lockForUpdate()->findOrFail($id);

            if ($validated['action'] === 'approve') {
                $updateData = [];

                if (Schema::hasColumn('orders', 'payment_status')) {
                    $updateData['payment_status'] = 'paid';
                }
                if (Schema::hasColumn('orders', 'payment_rejected_reason')) {
                    $updateData['payment_rejected_reason'] = null;
                }
                if (Schema::hasColumn('orders', 'payment_verified_at')) {
                    $updateData['payment_verified_at'] = now();
                }
                if (strtolower((string) ($order->status ?? 'pending')) === 'pending') {
                    $updateData['status'] = 'diproses';
                }

                $order->update($updateData);
            } else {
                $updateData = [];

                if (Schema::hasColumn('orders', 'payment_status')) {
                    $updateData['payment_status'] = 'rejected';
                }
                if (Schema::hasColumn('orders', 'payment_rejected_reason')) {
                    $updateData['payment_rejected_reason'] = trim((string) $validated['rejection_reason']);
                }
                if (Schema::hasColumn('orders', 'payment_verified_at')) {
                    $updateData['payment_verified_at'] = null;
                }

                $order->update($updateData);
            }
        });

        $msg = $validated['action'] === 'approve'
            ? 'Pembayaran berhasil diverifikasi dan status pesanan otomatis diproses!'
            : 'Bukti pembayaran ditolak. Pelanggan dapat mengunggah ulang bukti yang valid.';

        return redirect()->back()->with('success', $msg);
    }

    public function update(Request $request, $id)
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'status'          => 'required|in:pending,diproses,dikirim,selesai,dibatalkan',
            'tracking_number' => 'nullable|string|max:100',
            'courier'         => 'nullable|string|max:100',
        ]);

        try {
            DB::transaction(function () use ($request, $validated, $id) {
                $order = Order::with('items')->lockForUpdate()->findOrFail($id);
                $oldStatus = strtolower((string) ($order->status ?? 'pending'));
                $newStatus = strtolower((string) $validated['status']);

                // Kembalikan stok jika pesanan dibatalkan
                if ($newStatus === 'dibatalkan' && $oldStatus !== 'dibatalkan') {
                    $this->adjustOrderStock($order, 'increment');
                }

                // Kurangi stok kembali jika pesanan dibatalkan lalu diaktifkan ulang
                if ($oldStatus === 'dibatalkan' && $newStatus !== 'dibatalkan') {
                    $this->adjustOrderStock($order, 'decrement');
                }

                $updateData = [
                    'status' => $newStatus,
                ];

                if ($newStatus === 'selesai' && Schema::hasColumn('orders', 'payment_status') && $order->payment_status !== 'paid') {
                    $updateData['payment_status'] = 'paid';
                    if (Schema::hasColumn('orders', 'payment_verified_at') && empty($order->payment_verified_at)) {
                        $updateData['payment_verified_at'] = now();
                    }
                }

                if ($request->has('tracking_number') && Schema::hasColumn('orders', 'tracking_number')) {
                    $updateData['tracking_number'] = filled($validated['tracking_number'])
                        ? trim((string) $validated['tracking_number'])
                        : null;
                }

                if ($request->has('courier') && Schema::hasColumn('orders', 'courier')) {
                    $updateData['courier'] = filled($validated['courier'])
                        ? trim((string) $validated['courier'])
                        : $order->courier;
                }

                $order->update($updateData);
            });
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Status pesanan dan nomor resi pengiriman berhasil diperbarui!');
    }

    public function updateStatus(Request $request, $id)
    {
        return $this->update($request, $id);
    }

    public function rekapBulanan(Request $request)
    {
        $this->ensureAdmin();

        $bulan = (int) $request->input('bulan', now()->month);
        $tahun = (int) $request->input('tahun', now()->year);

        $orders = Order::with(['user', 'items.product'])
            ->whereMonth('created_at', $bulan)
            ->whereYear('created_at', $tahun)
            ->latest()
            ->get();

        $validOrders = $orders->filter(fn ($o) => !in_array(strtolower((string) $o->status), ['dibatalkan', 'cancelled', 'batal']));

        $totalPendapatan = $validOrders->sum(function ($o) {
            return (float) ($o->total_price ?? $o->total_amount ?? $o->grand_total ?? $o->total ?? 0);
        });

        $totalTransaksi = $validOrders->count();
        $totalSelesai   = $orders->filter(fn ($o) => strtolower((string) $o->status) === 'selesai')->count();
        $totalBatal     = $orders->filter(fn ($o) => in_array(strtolower((string) $o->status), ['dibatalkan', 'cancelled', 'batal']))->count();

        if (view()->exists('admin.orders.rekap_bulanan')) {
            return view('admin.orders.rekap_bulanan', compact(
                'orders', 'bulan', 'tahun', 'totalPendapatan', 'totalTransaksi', 'totalSelesai', 'totalBatal'
            ));
        }

        return redirect()->route('admin.orders.index')->with('info', "Rekap {$bulan}/{$tahun}: {$totalTransaksi} pesanan valid, total Rp " . number_format($totalPendapatan, 0, ',', '.'));
    }

    public function exportCsv(Request $request)
    {
        $this->ensureAdmin();

        $query = Order::with(['user', 'items.product'])->latest();

        if ($request->filled('status')) {
            $query->where('status', strtolower(trim((string) $request->status)));
        }
        if ($request->filled('bulan')) {
            $query->whereMonth('created_at', (int) $request->bulan);
        }
        if ($request->filled('tahun')) {
            $query->whereYear('created_at', (int) $request->tahun);
        }

        $orders = $query->get();
        $filename = 'laporan-pesanan-sportmania-' . now()->format('Ymd-His') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($orders) {
            $file = fopen('php://output', 'w');
            // BOM UTF-8 agar karakter terbaca rapi di Excel
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($file, [
                'ID Order',
                'Nomor Pesanan',
                'Tanggal',
                'Pelanggan',
                'No HP',
                'Kurir',
                'No Resi',
                'Kode Voucher',
                'Diskon',
                'Total Bayar',
                'Status Pembayaran',
                'Status Pesanan',
            ]);

            foreach ($orders as $order) {
                $total = (float) ($order->total_price ?? $order->total_amount ?? $order->grand_total ?? $order->total ?? 0);
                fputcsv($file, [
                    $order->id,
                    $order->order_number ?? $order->invoice_number ?? '#' . $order->id,
                    $order->created_at ? Carbon::parse($order->created_at)->format('Y-m-d H:i:s') : '-',
                    $order->name ?? $order->customer_name ?? optional($order->user)->name ?? 'Guest',
                    $order->phone ?? optional($order->user)->phone ?? '-',
                    $order->courier ?? '-',
                    $order->tracking_number ?? '-',
                    $order->voucher_code ?? '-',
                    (float) ($order->discount_amount ?? 0),
                    $total,
                    $order->payment_status ?? '-',
                    $order->status ?? '-',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function adjustOrderStock(Order $order, string $direction): void
    {
        foreach ($order->items as $item) {
            if (!$item->product_id) {
                continue;
            }

            $qty = max(1, (int) ($item->quantity ?? $item->qty ?? 1));
            $product = Product::where('id', $item->product_id)->lockForUpdate()->first();
            if (!$product) {
                continue;
            }

            $variantId = $item->product_variant_id ?? $item->variant_id ?? null;
            $size      = $item->size ?? $item->variant_size ?? null;
            $color     = $item->color ?? $item->variant_color ?? null;

            $hasVariants = ProductVariant::where('product_id', $product->id)->exists();

            if ($hasVariants) {
                $variant = null;

                if (!empty($variantId)) {
                    $variant = ProductVariant::where('id', $variantId)
                        ->where('product_id', $product->id)
                        ->lockForUpdate()
                        ->first();
                }

                if (!$variant) {
                    $variant = ProductVariant::where('product_id', $product->id)
                        ->when($size && $size !== 'All Size', fn ($q) => $q->where('size', $size))
                        ->when($color && $color !== '-', fn ($q) => $q->where('color', $color))
                        ->lockForUpdate()
                        ->first();
                }

                if ($variant) {
                    if ($direction === 'increment') {
                        $variant->increment('stock', $qty);
                    } else {
                        if ($variant->stock < $qty) {
                            throw new \RuntimeException("Gagal mengaktifkan pesanan: stok varian {$product->name} tidak mencukupi (sisa {$variant->stock} pcs).");
                        }
                        $variant->decrement('stock', $qty);
                    }
                }

                if (array_key_exists('stock', $product->getAttributes()) && $product->stock !== null) {
                    if ($direction === 'increment') {
                        $product->increment('stock', $qty);
                    } elseif ($product->stock >= $qty) {
                        $product->decrement('stock', $qty);
                    }
                }
            } else {
                if (array_key_exists('stock', $product->getAttributes()) && $product->stock !== null) {
                    if ($direction === 'increment') {
                        $product->increment('stock', $qty);
                    } else {
                        if ($product->stock < $qty) {
                            throw new \RuntimeException("Gagal mengaktifkan pesanan: stok {$product->name} tidak mencukupi (sisa {$product->stock} pcs).");
                        }
                        $product->decrement('stock', $qty);
                    }
                }
            }
        }
    }
}