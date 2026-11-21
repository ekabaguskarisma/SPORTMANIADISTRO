<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminOrderController extends Controller
{
    private function ensureAdmin(): void
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        $role = strtolower((string) ($user?->getAttribute('role') ?? ''));

        if (!in_array($role, ['admin', 'owner'], true)) {
            abort(403, 'Akses khusus Admin.');
        }
    }

    private function formatOrderCode(Order $order): string
    {
        $orderNumber = $order->getAttribute('order_number');
        if (filled($orderNumber)) {
            return (string) $orderNumber;
        }

        $invoiceNumber = $order->getAttribute('invoice_number');
        if (filled($invoiceNumber)) {
            return (string) $invoiceNumber;
        }

        return 'ORD-' . str_pad((string) $order->getKey(), 4, '0', STR_PAD_LEFT);
    }

    private function getTimeLabel(Order $order): string
    {
        $createdAt = $order->getAttribute('created_at');
        if (!$createdAt) {
            return '-';
        }

        $dt = $createdAt instanceof Carbon ? $createdAt : Carbon::parse($createdAt);
        $dt->setTimezone(config('app.timezone', 'Asia/Jakarta'));

        if ($dt->isToday()) {
            return 'Hari Ini';
        }

        if ($dt->isYesterday()) {
            return 'Kemarin';
        }

        return $dt->translatedFormat('d M Y');
    }

    private function applyOrderFilters($query, Request $request)
    {
        // Filter Pencarian (Order ID / Nama / No. HP / Resi / Tanggal)
        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $numericId = preg_replace('/[^0-9]/', '', $search);

            $query->where(function ($q) use ($search, $numericId) {
                if (Schema::hasColumn('orders', 'order_number')) {
                    $q->orWhere('order_number', 'like', "%{$search}%");
                }
                if (Schema::hasColumn('orders', 'invoice_number')) {
                    $q->orWhere('invoice_number', 'like', "%{$search}%");
                }
                if (Schema::hasColumn('orders', 'name')) {
                    $q->orWhere('name', 'like', "%{$search}%");
                }
                if (Schema::hasColumn('orders', 'recipient_name')) {
                    $q->orWhere('recipient_name', 'like', "%{$search}%");
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
                if (Schema::hasColumn('orders', 'resi')) {
                    $q->orWhere('resi', 'like', "%{$search}%");
                }
                $q->orWhere('created_at', 'like', "%{$search}%");

                if ($numericId !== '') {
                    $q->orWhere('id', (int) $numericId);
                }
            });
        }

        // Filter Preset Waktu (today, yesterday, 7days, this_month)
        $timePreset = strtolower(trim((string) $request->input('time_filter', '')));
        $tz = config('app.timezone', 'Asia/Jakarta');

        if ($timePreset === 'today') {
            $start = Carbon::today($tz)->startOfDay();
            $end = Carbon::today($tz)->endOfDay();
            $query->whereBetween('created_at', [$start, $end]);
        } elseif ($timePreset === 'yesterday') {
            $start = Carbon::yesterday($tz)->startOfDay();
            $end = Carbon::yesterday($tz)->endOfDay();
            $query->whereBetween('created_at', [$start, $end]);
        } elseif ($timePreset === '7days') {
            $start = Carbon::today($tz)->subDays(6)->startOfDay();
            $end = Carbon::today($tz)->endOfDay();
            $query->whereBetween('created_at', [$start, $end]);
        } elseif ($timePreset === 'this_month') {
            $start = Carbon::now($tz)->startOfMonth();
            $end = Carbon::now($tz)->endOfMonth();
            $query->whereBetween('created_at', [$start, $end]);
        }

        // Filter Tanggal Spesifik (date_from & date_until)
        if ($request->filled('date_from')) {
            try {
                $from = Carbon::parse((string) $request->input('date_from'), $tz)->startOfDay();
                $query->where('created_at', '>=', $from);
            } catch (\Throwable $e) {
                // Abaikan jika format tanggal tidak valid
            }
        }

        if ($request->filled('date_until')) {
            try {
                $until = Carbon::parse((string) $request->input('date_until'), $tz)->endOfDay();
                $query->where('created_at', '<=', $until);
            } catch (\Throwable $e) {
                // Abaikan jika format tanggal tidak valid
            }
        }

        // Filter Status Pesanan
        if ($request->filled('status')) {
            $status = strtolower(trim((string) $request->input('status')));
            if (in_array($status, ['dibatalkan', 'batal'], true)) {
                $query->whereIn('status', ['dibatalkan', 'batal']);
            } else {
                $query->where('status', $status);
            }
        }

        // Filter Status Pembayaran
        if ($request->filled('payment_status') && Schema::hasColumn('orders', 'payment_status')) {
            $paymentStatus = strtolower(trim((string) $request->input('payment_status')));
            $query->where('payment_status', $paymentStatus);
        }

        return $query;
    }

    public function index(Request $request)
    {
        $this->ensureAdmin();

        $query = Order::with(['items.product', 'user']);
        $this->applyOrderFilters($query, $request);

        $orders = $query->latest()->paginate(15)->withQueryString();

        $orders->getCollection()->transform(function (Order $order) {
            $order->setAttribute('time_label', $this->getTimeLabel($order));
            return $order;
        });

        if (view()->exists('admin.orders.index')) {
            return view('admin.orders.index', compact('orders'));
        }

        return redirect()->route('admin.dashboard');
    }

    public function exportCsv(Request $request)
    {
        $this->ensureAdmin();

        $query = Order::with(['items.product', 'user']);
        $this->applyOrderFilters($query, $request);

        $orders = $query->latest()->get();

        $tz = config('app.timezone', 'Asia/Jakarta');
        $fileName = 'SportMania_Rekap_Transaksi_' . now($tz)->format('Y-m-d_His') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'Cache-Control'       => 'no-store, no-cache, must-revalidate',
        ];

        $delimiter = ';';
        $cleanText = fn ($val): string => preg_replace('/\s+/', ' ', trim((string) ($val ?? '-'))) ?: '-';

        $callback = function () use ($orders, $delimiter, $cleanText, $tz) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM agar Excel membaca karakter dengan benar
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Kode Order',
                'Keterangan Waktu',
                'Tanggal & Jam',
                'Pelanggan',
                'Telepon',
                'Kota',
                'Alamat',
                'Metode Bayar',
                'Status Bayar',
                'Ekspedisi',
                'No Resi',
                'Status Pesanan',
                'Total Tagihan (Rp)',
            ], $delimiter);

            foreach ($orders as $order) {
                $createdAt = $order->getAttribute('created_at');
                $dt = $createdAt ? ($createdAt instanceof Carbon ? $createdAt : Carbon::parse($createdAt))->setTimezone($tz) : null;

                $statusRaw = strtolower((string) ($order->getAttribute('status') ?? 'pending'));
                if ($statusRaw === 'batal') {
                    $statusRaw = 'dibatalkan';
                }

                // Diselaraskan dengan urutan prioritas di dashboard.blade.php
                $customerName = $order->getAttribute('recipient_name')
                    ?? $order->getAttribute('customer_name')
                    ?? $order->getAttribute('name')
                    ?? optional($order->getRelationValue('user'))->name
                    ?? 'Pelanggan';

                $phone = $order->getAttribute('recipient_phone')
                    ?? $order->getAttribute('customer_phone')
                    ?? $order->getAttribute('phone')
                    ?? optional($order->getRelationValue('user'))->phone
                    ?? '-';

                $shippingMethodRaw = strtolower((string) ($order->getAttribute('shipping_method') ?? 'delivery'));
                $shippingMethodLabel = $shippingMethodRaw === 'pickup' ? 'AMBIL DI STORE' : strtoupper($shippingMethodRaw);

                $tracking = $order->getAttribute('tracking_number') ?? $order->getAttribute('resi') ?? '-';
                $address = $order->getAttribute('shipping_address')
                    ?? $order->getAttribute('address')
                    ?? ($shippingMethodRaw === 'pickup' ? 'Ambil di Store Sport Mania Distro' : '-');

                $payStatusRaw = strtolower((string) ($order->getAttribute('payment_status') ?? ''));
                if ($payStatusRaw === 'paid' || in_array($statusRaw, ['diproses', 'dikirim', 'selesai'], true)) {
                    $payStatus = 'paid';
                } elseif ($payStatusRaw === 'rejected') {
                    $payStatus = 'rejected';
                } elseif (!empty($order->getAttribute('payment_proof')) || $payStatusRaw === 'waiting_verification') {
                    $payStatus = 'waiting_verification';
                } else {
                    $payStatus = $payStatusRaw ?: 'unpaid';
                }

                $total = (int) round((float) (
                    $order->getAttribute('total_amount')
                    ?? $order->getAttribute('grand_total')
                    ?? $order->getAttribute('total_price')
                    ?? $order->getAttribute('total')
                    ?? 0
                ));

                fputcsv($handle, [
                    $this->formatOrderCode($order),
                    $this->getTimeLabel($order),
                    $dt ? $dt->format('d M Y, H:i') : '-',
                    $cleanText($customerName),
                    $cleanText($phone),
                    $cleanText($order->getAttribute('city')),
                    $cleanText($address),
                    strtoupper($cleanText($order->getAttribute('payment_method'))),
                    strtolower($cleanText($payStatus)),
                    $shippingMethodLabel,
                    $cleanText($tracking),
                    strtoupper($statusRaw),
                    $total,
                ], $delimiter);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function show(Request $request, $id)
    {
        $this->ensureAdmin();

        /** @var Order $order */
        $order = Order::with(['items.product', 'user'])->findOrFail($id);

        if ($request->expectsJson()) {
            return response()->json($order);
        }

        if (view()->exists('admin.orders.show')) {
            return view('admin.orders.show', compact('order'));
        }

        return redirect()->route('admin.dashboard');
    }

    public function verifyPayment(Request $request, $id)
    {
        $this->ensureAdmin();

        $request->validate([
            'action'                  => 'required|string|in:approve,reject',
            'rejection_reason'        => 'nullable|string|max:500',
            'rejected_reason'         => 'nullable|string|max:500',
            'payment_rejected_reason' => 'nullable|string|max:500',
        ], [
            'action.required'             => 'Aksi verifikasi wajib dipilih.',
            'action.in'                   => 'Aksi verifikasi tidak valid.',
            'rejection_reason.max'        => 'Alasan penolakan maksimal 500 karakter.',
            'rejected_reason.max'         => 'Alasan penolakan maksimal 500 karakter.',
            'payment_rejected_reason.max' => 'Alasan penolakan maksimal 500 karakter.',
        ]);

        $action = (string) $request->input('action');
        $reasonInput = $request->input(
            'rejection_reason',
            $request->input('rejected_reason', $request->input('payment_rejected_reason'))
        );
        $reasonClean = trim((string) $reasonInput);

        if ($action === 'reject' && $reasonClean === '') {
            return back()
                ->withErrors(['rejection_reason' => 'Alasan penolakan wajib diisi jika bukti pembayaran ditolak.'])
                ->with('error', 'Alasan penolakan wajib diisi jika bukti pembayaran ditolak.')
                ->withInput();
        }

        /** @var Order $order */
        $order = Order::findOrFail($id);
        $orderCode = $this->formatOrderCode($order);

        if (strtolower((string) ($order->getAttribute('payment_method') ?? '')) === 'cod') {
            return back()->with('error', "Pesanan #{$orderCode} menggunakan metode COD sehingga tidak memerlukan verifikasi transfer.");
        }

        if ($action === 'approve' && empty($order->getAttribute('payment_proof'))) {
            return back()->with('error', "Bukti pembayaran untuk pesanan #{$orderCode} belum diunggah oleh pelanggan.");
        }

        DB::transaction(function () use ($id, $action, $reasonClean) {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::where('id', $id)->lockForUpdate()->firstOrFail();

            $updateData = [];
            $hasPaymentStatus = Schema::hasColumn('orders', 'payment_status');
            $hasRejectedReason = Schema::hasColumn('orders', 'payment_rejected_reason');
            $hasVerifiedAt = Schema::hasColumn('orders', 'payment_verified_at');

            if ($action === 'approve') {
                if ($hasPaymentStatus) {
                    $updateData['payment_status'] = 'paid';
                }
                if ($hasRejectedReason) {
                    $updateData['payment_rejected_reason'] = null;
                }
                if ($hasVerifiedAt) {
                    $updateData['payment_verified_at'] = now();
                }

                if (strtolower((string) ($lockedOrder->getAttribute('status') ?? 'pending')) === 'pending') {
                    $updateData['status'] = 'diproses';
                }
            } else {
                if ($hasPaymentStatus) {
                    $updateData['payment_status'] = 'rejected';
                }
                if ($hasRejectedReason) {
                    $updateData['payment_rejected_reason'] = $reasonClean;
                }
                if ($hasVerifiedAt) {
                    $updateData['payment_verified_at'] = null;
                }

                $currentStatus = strtolower((string) ($lockedOrder->getAttribute('status') ?? 'pending'));
                if (in_array($currentStatus, ['pending', 'diproses'], true)) {
                    $updateData['status'] = 'pending';
                }
            }

            if (!empty($updateData)) {
                $lockedOrder->update($updateData);
            }
        });

        if ($action === 'approve') {
            return back()->with('success', "Pembayaran pesanan #{$orderCode} telah diverifikasi (LUNAS) dan siap diproses.");
        }

        return back()->with('success', "Bukti pembayaran pesanan #{$orderCode} ditolak. Pelanggan akan diminta mengunggah ulang.");
    }

    public function update(Request $request, $id)
    {
        $this->ensureAdmin();

        $request->validate([
            'status'                  => 'required|string|in:pending,diproses,dikirim,selesai,dibatalkan,batal',
            'tracking_number'         => 'nullable|string|max:100',
            'resi'                    => 'nullable|string|max:100',
            'no_resi'                 => 'nullable|string|max:100',
            'action'                  => 'nullable|string|in:approve,reject',
            'rejection_reason'        => 'nullable|string|max:500',
            'rejected_reason'         => 'nullable|string|max:500',
            'payment_rejected_reason' => 'nullable|string|max:500',
        ], [
            'status.required' => 'Status pesanan wajib dipilih.',
            'status.in'       => 'Status pesanan tidak valid.',
        ]);

        // Fallback otomatis jika form verifikasi/penolakan pembayaran dikirim ke route update()
        if ($request->filled('action')) {
            return $this->verifyPayment($request, $id);
        }

        $newStatusRaw = strtolower(trim((string) $request->input('status')));
        $newStatus = $newStatusRaw === 'batal' ? 'dibatalkan' : $newStatusRaw;

        $resiInput = $request->input(
            'tracking_number',
            $request->input('resi', $request->input('no_resi'))
        );
        $resiClean = is_string($resiInput) ? trim($resiInput) : null;

        /** @var Order $order */
        $order = DB::transaction(function () use ($id, $newStatus, $resiClean, $request) {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::with('items')->where('id', $id)->lockForUpdate()->firstOrFail();

            $oldStatusRaw = strtolower((string) ($lockedOrder->getAttribute('status') ?? 'pending'));
            $oldStatus = $oldStatusRaw === 'batal' ? 'dibatalkan' : $oldStatusRaw;

            $updateData = ['status' => $newStatus];

            $hasTrackingCol = Schema::hasColumn('orders', 'tracking_number');
            $hasResiCol = Schema::hasColumn('orders', 'resi');

            if ($request->hasAny(['tracking_number', 'resi', 'no_resi'])) {
                if ($hasTrackingCol) {
                    $updateData['tracking_number'] = filled($resiClean) ? $resiClean : null;
                }
                if ($hasResiCol) {
                    $updateData['resi'] = filled($resiClean) ? $resiClean : null;
                }
            }

            // Sinkronisasi payment_status agar konsisten antara DB dan tampilan dashboard
            if (Schema::hasColumn('orders', 'payment_status')) {
                $payMethod = strtolower((string) ($lockedOrder->getAttribute('payment_method') ?? ''));
                $currentPayStatus = strtolower((string) ($lockedOrder->getAttribute('payment_status') ?? 'unpaid'));

                $shouldMarkPaid = (
                    $payMethod !== 'cod'
                    && in_array($newStatus, ['diproses', 'dikirim', 'selesai'], true)
                    && $currentPayStatus !== 'paid'
                ) || (
                    $payMethod === 'cod'
                    && $newStatus === 'selesai'
                    && $currentPayStatus !== 'paid'
                );

                if ($shouldMarkPaid) {
                    $updateData['payment_status'] = 'paid';
                    if (Schema::hasColumn('orders', 'payment_verified_at') && empty($lockedOrder->getAttribute('payment_verified_at'))) {
                        $updateData['payment_verified_at'] = now();
                    }
                    if (Schema::hasColumn('orders', 'payment_rejected_reason')) {
                        $updateData['payment_rejected_reason'] = null;
                    }
                }
            }

            $lockedOrder->update($updateData);

            if ($oldStatus !== 'dibatalkan' && $newStatus === 'dibatalkan') {
                $this->adjustOrderStock($lockedOrder, 'restore');
            }

            if ($oldStatus === 'dibatalkan' && $newStatus !== 'dibatalkan') {
                $this->adjustOrderStock($lockedOrder, 'deduct');
            }

            return $lockedOrder;
        });

        $orderCode = $this->formatOrderCode($order);

        return back()->with('success', "Pesanan #{$orderCode} berhasil diperbarui ke status " . strtoupper($newStatus) . ".");
    }

    public function rekapBulanan(Request $request)
    {
        $this->ensureAdmin();

        $bulan = (int) $request->input('bulan', now()->month);
        $tahun = (int) $request->input('tahun', now()->year);

        $query = Order::with(['user', 'items.product'])
            ->whereMonth('created_at', $bulan)
            ->whereYear('created_at', $tahun);

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $status = strtolower(trim((string) $request->input('status')));
            if (in_array($status, ['dibatalkan', 'batal'], true)) {
                $query->whereIn('status', ['dibatalkan', 'batal']);
            } else {
                $query->where('status', $status);
            }
        }

        $orders = $query->latest()->get();

        $omzetSelesai = 0.0;
        $omzetPipeline = 0.0;
        $totalTransaksi = $orders->count();
        $transaksiSelesai = 0;
        $transaksiBatal = 0;
        $rekapMetode = collect();

        foreach ($orders as $order) {
            /** @var Order $order */
            $order->setAttribute('time_label', $this->getTimeLabel($order));

            $statusOrder = strtolower((string) ($order->getAttribute('status') ?? ''));
            $nominal = (float) ($order->getAttribute('total_price') ?? $order->getAttribute('total_amount') ?? $order->getAttribute('total') ?? 0);

            if ($statusOrder === 'selesai') {
                $omzetSelesai += $nominal;
                $transaksiSelesai++;
            } elseif (in_array($statusOrder, ['pending', 'diproses', 'dikirim'], true)) {
                $omzetPipeline += $nominal;
            } elseif (in_array($statusOrder, ['dibatalkan', 'batal'], true)) {
                $transaksiBatal++;
            }

            if (!in_array($statusOrder, ['dibatalkan', 'batal'], true)) {
                $metode = (string) ($order->getAttribute('payment_method') ?? 'lainnya');
                $current = $rekapMetode->get($metode, [
                    'jumlah'  => 0,
                    'nominal' => 0.0,
                ]);

                $rekapMetode->put($metode, [
                    'jumlah'  => $current['jumlah'] + 1,
                    'nominal' => $current['nominal'] + $nominal,
                ]);
            }
        }

        return view('admin.rekap-bulanan', compact(
            'orders',
            'bulan',
            'tahun',
            'omzetSelesai',
            'omzetPipeline',
            'totalTransaksi',
            'transaksiSelesai',
            'transaksiBatal',
            'rekapMetode'
        ));
    }

    private function adjustOrderStock(Order $order, string $direction): void
    {
        $items = $order->getRelationValue('items') ?? $order->items()->get();

        foreach ($items as $item) {
            /** @var \Illuminate\Database\Eloquent\Model $item */
            $qty = (int) ($item->getAttribute('quantity') ?? $item->getAttribute('qty') ?? 1);
            if ($qty <= 0) {
                continue;
            }

            $handled = false;
            $productId = $item->getAttribute('product_id');

            /** @var Product|null $product */
            $product = $productId
                ? Product::where('id', $productId)->lockForUpdate()->first()
                : null;

            if (Schema::hasTable('product_variants') && $productId) {
                $variantId = $item->getAttribute('product_variant_id') ?? $item->getAttribute('variant_id');
                $size = $item->getAttribute('variant_size') ?? $item->getAttribute('size');
                $color = $item->getAttribute('variant_color') ?? $item->getAttribute('color');

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
                    if ($direction === 'restore') {
                        $variant->increment('stock', $qty);
                    } else {
                        $newStock = max(0, (int) $variant->getAttribute('stock') - $qty);
                        $variant->update(['stock' => $newStock]);
                    }

                    if ($product && array_key_exists('stock', $product->getAttributes()) && $product->getAttribute('stock') !== null) {
                        $totalVariantStock = (int) ProductVariant::where('product_id', $productId)->sum('stock');
                        $product->update(['stock' => $totalVariantStock]);
                    }

                    $handled = true;
                }
            }

            if (!$handled && $product && array_key_exists('stock', $product->getAttributes()) && $product->getAttribute('stock') !== null) {
                if ($direction === 'restore') {
                    $product->increment('stock', $qty);
                } else {
                    $newStock = max(0, (int) $product->getAttribute('stock') - $qty);
                    $product->update(['stock' => $newStock]);
                }
            }
        }
    }
}