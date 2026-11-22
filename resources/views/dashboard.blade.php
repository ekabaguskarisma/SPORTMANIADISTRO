<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel — Sport Mania Distro</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        [x-cloak] { display: none !important; }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(148, 163, 184, 0.35); border-radius: 9999px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(148, 163, 184, 0.55); }
    </style>
</head>
<body class="bg-[#f4f6f9] text-slate-800 antialiased selection:bg-red-600 selection:text-white" x-data="adminPanel()">

    @php
        $products = $products ?? collect();
        $orders = $orders ?? collect();
        $customers = $customers ?? collect();
        $promos = $promos ?? collect();
        $vouchers = $vouchers ?? collect();

        $totalStockAll = $products->sum(function ($p) {
            return ($p->variants && $p->variants->count() > 0)
                ? $p->variants->sum('stock')
                : ($p->stock ?? 0);
        });

        $lowStockProducts = $products->filter(function ($p) {
            $totalStok = ($p->variants && $p->variants->count() > 0)
                ? $p->variants->sum('stock')
                : ($p->stock ?? 0);
            $hasLowVariant = ($p->variants && $p->variants->count() > 0)
                ? $p->variants->where('stock', '<=', 3)->count() > 0
                : false;
            return $totalStok <= 10 || $hasLowVariant;
        });

        $resolvePaymentStatus = function ($order) {
            $rawPay = strtolower((string) ($order->payment_status ?? ''));
            if (in_array($rawPay, ['paid', 'lunas', 'verified', 'settlement'], true)) {
                return 'paid';
            }
            if (in_array($rawPay, ['rejected', 'ditolak', 'invalid'], true)) {
                return 'rejected';
            }
            if (in_array($rawPay, ['waiting_verification', 'pending_verification', 'menunggu_verifikasi'], true)) {
                return 'waiting_verification';
            }

            $orderSt = strtolower((string) ($order->status ?? 'pending'));
            if ($orderSt === 'selesai') {
                return 'paid';
            }

            if (filled($order->payment_proof ?? null)) {
                return 'waiting_verification';
            }

            return 'unpaid';
        };

        $finalizedOrders = $orders->filter(function ($o) {
            $st = strtolower($o->status ?? '');
            return in_array($st, ['selesai', 'dibatalkan', 'batal'], true);
        });

        $waitingVerificationCount = $orders->filter(function ($o) use ($resolvePaymentStatus) {
            $st = strtolower($o->status ?? 'pending');
            $normSt = $st === 'batal' ? 'dibatalkan' : $st;
            return $normSt !== 'dibatalkan' && $resolvePaymentStatus($o) === 'waiting_verification';
        })->count();

        $usersById = \App\Models\User::pluck('name', 'id')->toArray();

        $ordersMap = [];
        foreach ($orders as $o) {
            $stRaw = strtolower($o->status ?? 'pending');
            $stNorm = $stRaw === 'batal' ? 'dibatalkan' : $stRaw;
            $payStat = $resolvePaymentStatus($o);
            $proofRaw = $o->payment_proof ?? null;
            $proofUrl = filled($proofRaw)
                ? (filter_var($proofRaw, FILTER_VALIDATE_URL) ? $proofRaw : asset('storage/' . ltrim($proofRaw, '/')))
                : null;

            $codeClean = filled($o->order_number ?? null)
                ? $o->order_number
                : (filled($o->invoice_number ?? null)
                    ? $o->invoice_number
                    : ('ORD-' . str_pad($o->id, 4, '0', STR_PAD_LEFT)));

            $custName = filled($o->recipient_name ?? null) ? $o->recipient_name
                : (filled($o->customer_name ?? null) ? $o->customer_name
                : (filled($o->name ?? null) ? $o->name
                : (optional($o->user)->name ?? $usersById[$o->user_id ?? 0] ?? 'Pelanggan Setia')));

            $phoneClean = filled($o->recipient_phone ?? null) ? $o->recipient_phone
                : (filled($o->customer_phone ?? null) ? $o->customer_phone
                : (filled($o->phone ?? null) ? $o->phone : '-'));

            $waDigits = preg_replace('/[^0-9]/', '', (string) $phoneClean);
            if ($waDigits !== '') {
                if (str_starts_with($waDigits, '0')) {
                    $waDigits = '62' . substr($waDigits, 1);
                } elseif (!str_starts_with($waDigits, '62')) {
                    $waDigits = '62' . $waDigits;
                }
            }
            $waLink = $waDigits !== '' ? 'https://wa.me/' . $waDigits : null;

            $addrClean = filled($o->shipping_address ?? null) ? $o->shipping_address
                : (filled($o->address ?? null) ? $o->address : '-');

            $cityClean = filled($o->city ?? null) ? $o->city
                : (filled($o->shipping_city ?? null) ? $o->shipping_city : '-');

            $shipMethodLabel = match(strtolower($o->shipping_method ?? '')) {
                'pickup'  => 'Ambil Langsung di Store',
                'pos'     => 'Kasir Toko (POS)',
                'jne'     => 'JNE Reguler',
                'sicepat' => 'SiCepat Express',
                default   => filled($o->shipping_method ?? null) ? strtoupper($o->shipping_method) : 'Reguler',
            };

            $resiClean = $o->tracking_number ?? $o->no_resi ?? $o->resi ?? '';
            $totalVal = (float) ($o->total_price ?? $o->total_amount ?? $o->total ?? 0);
            $shipCostVal = (float) ($o->shipping_cost ?? 0);
            $subtotalVal = isset($o->subtotal) && (float) $o->subtotal > 0
                ? (float) $o->subtotal
                : max(0, $totalVal - $shipCostVal);

            $mappedItems = [];
            if ($o->items && $o->items->count() > 0) {
                foreach ($o->items as $it) {
                    $qty = (int) ($it->quantity ?? $it->qty ?? 1);
                    $price = (float) ($it->price ?? optional($it->product)->price ?? 0);
                    $sub = (float) ($it->subtotal ?? ($price * $qty));
                    $prodImg = optional($it->product)->image ?? null;
                    $imgUrl = filled($prodImg)
                        ? (filter_var($prodImg, FILTER_VALIDATE_URL) ? $prodImg : asset('storage/' . ltrim($prodImg, '/')))
                        : null;
                    $unitHpp = $it->hpp_snapshot ?? $it->hpp ?? $it->cost_price ?? optional($it->product)->hpp ?? optional($it->product)->cost_price ?? null;

                    $mappedItems[] = [
                        'product_id' => $it->product_id ?? optional($it->product)->id,
                        'name'       => filled($it->product_name ?? null) ? $it->product_name : (optional($it->product)->name ?? 'Produk Terhapus'),
                        'size'       => $it->size ?? optional($it->variant)->size ?? 'All Size',
                        'color'      => $it->color ?? optional($it->variant)->color ?? '-',
                        'qty'        => $qty,
                        'price'      => $price,
                        'hpp'        => $unitHpp !== null ? (float) $unitHpp : null,
                        'subtotal'   => $sub,
                        'image'      => $imgUrl,
                    ];
                }
            }

            $createdAt = $o->created_at;
            $updatedAt = $o->updated_at ?? $createdAt;
            $dateFormatted = optional($createdAt)->format('d M Y, H:i') ?? '-';
            $dateDisplay = optional($createdAt)->format('d F Y, H:i') ?? '-';
            $updatedFormatted = optional($updatedAt)->format('d M Y, H:i') ?? $dateFormatted;
            $updatedDisplay = optional($updatedAt)->format('d F Y, H:i') ?? $dateDisplay;
            $payUpper = strtoupper($o->payment_method ?? 'TRANSFER');
            $firstSummary = count($mappedItems) > 0
                ? ($mappedItems[0]['name'] . (count($mappedItems) > 1 ? ' +' . (count($mappedItems) - 1) . ' produk' : ''))
                : 'Item tidak tersedia';

            $hasVerifyRoute = \Illuminate\Support\Facades\Route::has('admin.orders.verify-payment');
            $verifyUrl = $hasVerifyRoute
                ? route('admin.orders.verify-payment', $o->id)
                : route('admin.orders.update', $o->id);

            $ordersMap[$o->id] = [
                'id'                      => $o->id,
                'code'                    => $codeClean,
                'customer'                => $custName,
                'phone'                   => $phoneClean,
                'wa_link'                 => $waLink,
                'address'                 => $addrClean,
                'city'                    => $cityClean,
                'date'                    => $dateFormatted,
                'date_display'            => $dateDisplay,
                'updated_date'            => $updatedFormatted,
                'updated_date_display'    => $updatedDisplay,
                'created_year'            => $createdAt ? (int) $createdAt->format('Y') : (int) now()->format('Y'),
                'created_month'           => $createdAt ? (int) $createdAt->format('n') : (int) now()->format('n'),
                'created_day'             => $createdAt ? (int) $createdAt->format('j') : (int) now()->format('j'),
                'is_today'                => $createdAt ? $createdAt->isToday() : false,
                'payment'                 => $payUpper,
                'payment_status'          => $payStat,
                'payment_proof_url'       => $proofUrl,
                'payment_verified_at'     => $o->payment_verified_at ? \Illuminate\Support\Carbon::parse($o->payment_verified_at)->format('d M Y, H:i') : null,
                'payment_rejected_reason' => $o->payment_rejected_reason ?? '',
                'shipping_method'         => $shipMethodLabel,
                'shipping_raw'            => strtolower($o->shipping_method ?? ''),
                'tracking_number'         => $resiClean,
                'status'                  => $stNorm,
                'subtotal'                => $subtotalVal,
                'shipping_cost'           => $shipCostVal,
                'total'                   => $totalVal,
                'first_item_summary'      => $firstSummary,
                'order_haystack'          => strtolower('#' . $codeClean . ' ' . $custName . ' ' . $resiClean . ' ' . $payUpper . ' ' . $dateDisplay . ' ' . $firstSummary),
                'history_haystack'        => strtolower('#' . $codeClean . ' ' . $custName . ' ' . $updatedDisplay . ' ' . $dateDisplay),
                'update_url'              => route('admin.orders.update', $o->id),
                'verify_url'              => $verifyUrl,
                'has_verify_route'        => $hasVerifyRoute,
                'items'                   => $mappedItems,
            ];
        }

        $customersList = [];
        foreach ($customers as $cust) {
            $cCreated = $cust->created_at;
            $cOrderCount = $orders->where('user_id', $cust->id)->count();
            $customersList[] = [
                'id'            => $cust->id,
                'name'          => $cust->name,
                'email'         => $cust->email,
                'total_orders'  => $cOrderCount,
                'joined_label'  => optional($cCreated)->format('d M Y') ?? '-',
                'created_year'  => $cCreated ? (int) $cCreated->format('Y') : 0,
                'created_month' => $cCreated ? (int) $cCreated->format('n') : 0,
            ];
        }

        $posProductsList = [];
        foreach ($products as $p) {
            $pImgRaw = $p->image ?? null;
            $pImgUrl = filled($pImgRaw)
                ? (filter_var($pImgRaw, FILTER_VALIDATE_URL) ? $pImgRaw : asset('storage/' . ltrim($pImgRaw, '/')))
                : null;

            $variantsArr = [];
            if ($p->variants && $p->variants->count() > 0) {
                foreach ($p->variants as $v) {
                    $variantsArr[] = [
                        'id'    => $v->id,
                        'size'  => $v->size ?? 'All Size',
                        'color' => $v->color ?? '',
                        'stock' => (int) ($v->stock ?? 0),
                    ];
                }
            }

            $totalStokProd = count($variantsArr) > 0
                ? array_sum(array_column($variantsArr, 'stock'))
                : (int) ($p->stock ?? 0);

            $posProductsList[] = [
                'id'            => $p->id,
                'name'          => $p->name,
                'category_name' => optional($p->category)->name ?? 'Umum',
                'category_slug' => strtolower(optional($p->category)->slug ?? optional($p->category)->name ?? 'umum'),
                'price'         => (float) ($p->price ?? 0),
                'hpp'           => (float) ($p->hpp ?? $p->cost_price ?? 0),
                'stock'         => $totalStokProd,
                'has_variants'  => count($variantsArr) > 0,
                'variants'      => $variantsArr,
                'image'         => $pImgUrl,
            ];
        }
    @endphp

    <!-- Toast Notifikasi Salin / Aksi -->
    <div x-show="toastMessage"
         x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-2"
         class="fixed bottom-5 right-5 z-[70] bg-slate-900 text-white text-xs font-bold px-4 py-3 rounded-xl shadow-xl border border-slate-700/80 flex items-center gap-2 print:hidden">
        <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
        <span x-text="toastMessage"></span>
    </div>

    <div class="min-h-screen flex print:hidden">

        <!-- =================================================================== -->
        <!-- SIDEBAR NAVIGASI KIRI                                               -->
        <!-- =================================================================== -->
        <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
               class="fixed lg:static inset-y-0 left-0 z-40 w-64 bg-slate-950 text-slate-300 flex flex-col justify-between transition-transform duration-300 ease-out border-r border-slate-800/80 shrink-0">

            <div>
                <div class="h-16 px-5 flex items-center justify-between border-b border-slate-800/80">
                    <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 group">
                        <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-red-600 to-rose-700 flex items-center justify-center font-black text-white text-sm shadow-lg shadow-red-600/30 group-hover:scale-105 transition-transform">
                            SM
                        </div>
                        <div class="leading-tight">
                            <span class="block font-black text-white tracking-tight text-sm uppercase">Sport Mania</span>
                            <span class="block text-[10px] font-bold tracking-widest uppercase text-red-500">Distro Admin</span>
                        </div>
                    </a>
                    <button @click="sidebarOpen = false" class="lg:hidden text-slate-400 hover:text-white p-1.5 rounded-lg hover:bg-slate-800">
                        ✕
                    </button>
                </div>

                <div class="px-3 py-5 space-y-1 overflow-y-auto custom-scrollbar max-h-[calc(100vh-11rem)]">
                    <p class="px-3 pb-2 text-[10px] font-extrabold uppercase tracking-widest text-slate-500">Menu Utama</p>

                    <button @click="setTab('dashboard')"
                            :class="activeTab === 'dashboard' ? 'bg-gradient-to-r from-red-600 to-rose-600 text-white shadow-md shadow-red-600/25' : 'text-slate-400 hover:bg-slate-900 hover:text-white'"
                            class="w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                        <span>Ringkasan</span>
                    </button>

                    <button @click="setTab('kasir')"
                            :class="activeTab === 'kasir' ? 'bg-gradient-to-r from-red-600 to-rose-600 text-white shadow-md shadow-red-600/25' : 'text-slate-400 hover:bg-slate-900 hover:text-white'"
                            class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all">
                        <span class="flex items-center gap-3">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                            <span>Kasir Toko (POS)</span>
                        </span>
                        <span :class="activeTab === 'kasir' ? 'bg-white/20 text-white' : 'bg-emerald-500/15 text-emerald-400'" class="text-[9px] font-black px-1.5 py-0.5 rounded-md uppercase">POS</span>
                    </button>

                    <button @click="setTab('produk')"
                            :class="activeTab === 'produk' ? 'bg-gradient-to-r from-red-600 to-rose-600 text-white shadow-md shadow-red-600/25' : 'text-slate-400 hover:bg-slate-900 hover:text-white'"
                            class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all">
                        <span class="flex items-center gap-3">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                            <span>Manajemen Produk</span>
                        </span>
                        <span :class="activeTab === 'produk' ? 'bg-white/20 text-white' : 'bg-slate-800 text-slate-300'" class="text-[10px] font-extrabold px-2 py-0.5 rounded-full tabular-nums">{{ $products->count() }}</span>
                    </button>

                    <button @click="setTab('promo')"
                            :class="activeTab === 'promo' ? 'bg-gradient-to-r from-red-600 to-rose-600 text-white shadow-md shadow-red-600/25' : 'text-slate-400 hover:bg-slate-900 hover:text-white'"
                            class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all">
                        <span class="flex items-center gap-3">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path></svg>
                            <span>Promo & Banner</span>
                        </span>
                        <span :class="activeTab === 'promo' ? 'bg-white/20 text-white' : 'bg-slate-800 text-slate-300'" class="text-[10px] font-extrabold px-2 py-0.5 rounded-full tabular-nums">{{ $promos->count() }}</span>
                    </button>

                    <button @click="setTab('voucher')"
                            :class="activeTab === 'voucher' ? 'bg-gradient-to-r from-red-600 to-rose-600 text-white shadow-md shadow-red-600/25' : 'text-slate-400 hover:bg-slate-900 hover:text-white'"
                            class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all">
                        <span class="flex items-center gap-3">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"></path></svg>
                            <span>Kode Voucher</span>
                        </span>
                        <span :class="activeTab === 'voucher' ? 'bg-white/20 text-white' : 'bg-slate-800 text-slate-300'" class="text-[10px] font-extrabold px-2 py-0.5 rounded-full tabular-nums">{{ $vouchers->count() }}</span>
                    </button>

                    <button @click="setTab('pesanan')"
                            :class="activeTab === 'pesanan' ? 'bg-gradient-to-r from-red-600 to-rose-600 text-white shadow-md shadow-red-600/25' : 'text-slate-400 hover:bg-slate-900 hover:text-white'"
                            class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all">
                        <span class="flex items-center gap-3">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                            <span>Kelola Pesanan</span>
                        </span>
                        <span class="flex items-center gap-1">
                            @if($waitingVerificationCount > 0)
                                <span class="bg-amber-500 text-slate-950 text-[9px] font-black px-1.5 py-0.5 rounded-full tabular-nums" title="Menunggu Verifikasi Pembayaran">{{ $waitingVerificationCount }}</span>
                            @endif
                            <span :class="activeTab === 'pesanan' ? 'bg-white/20 text-white' : 'bg-red-500/20 text-red-400'" class="text-[10px] font-extrabold px-2 py-0.5 rounded-full tabular-nums">{{ $orders->count() }}</span>
                        </span>
                    </button>

                    <button @click="setTab('pelanggan')"
                            :class="activeTab === 'pelanggan' ? 'bg-gradient-to-r from-red-600 to-rose-600 text-white shadow-md shadow-red-600/25' : 'text-slate-400 hover:bg-slate-900 hover:text-white'"
                            class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all">
                        <span class="flex items-center gap-3">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                            <span>Data Pelanggan</span>
                        </span>
                        <span :class="activeTab === 'pelanggan' ? 'bg-white/20 text-white' : 'bg-slate-800 text-slate-300'" class="text-[10px] font-extrabold px-2 py-0.5 rounded-full tabular-nums">{{ $customers->count() }}</span>
                    </button>

                    <button @click="setTab('riwayat')"
                            :class="activeTab === 'riwayat' ? 'bg-gradient-to-r from-red-600 to-rose-600 text-white shadow-md shadow-red-600/25' : 'text-slate-400 hover:bg-slate-900 hover:text-white'"
                            class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all">
                        <span class="flex items-center gap-3">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <span>Riwayat & Log</span>
                        </span>
                        <span :class="activeTab === 'riwayat' ? 'bg-white/20 text-white' : 'bg-slate-800 text-slate-300'" class="text-[10px] font-extrabold px-2 py-0.5 rounded-full tabular-nums">{{ $finalizedOrders->count() }}</span>
                    </button>

                    <button @click="setTab('rekap')"
                            :class="activeTab === 'rekap' ? 'bg-gradient-to-r from-red-600 to-rose-600 text-white shadow-md shadow-red-600/25' : 'text-slate-400 hover:bg-slate-900 hover:text-white'"
                            class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all">
                        <span class="flex items-center gap-3">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                            <span>Rekap Bulanan</span>
                        </span>
                        <span :class="activeTab === 'rekap' ? 'bg-white/20 text-white' : 'bg-red-500/15 text-red-400'" class="text-[9px] font-black px-1.5 py-0.5 rounded-md uppercase">GRAFIK</span>
                    </button>

                    <div class="pt-4 mt-4 border-t border-slate-800/80">
                        <p class="px-3 pb-2 text-[10px] font-extrabold uppercase tracking-widest text-slate-500">Tautan Cepat</p>
                        <a href="{{ url('/') }}" target="_blank" class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs font-semibold text-slate-400 hover:bg-slate-900 hover:text-white transition">
                            <span class="flex items-center gap-3">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                                <span>Lihat Web</span>
                            </span>
                            <span class="text-[10px] text-slate-600">↗</span>
                        </a>
                        <a href="{{ route('admin.home-sections.index') }}"
   class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs font-bold text-slate-300 hover:bg-red-600 hover:text-white transition">
    <span class="flex items-center gap-3">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
        <span>Edit Tampilan Beranda</span>
    </span>
    <span class="text-[10px]">→</span>
</a>
                    </div>
                </div>
            </div>

            <div class="p-3.5 border-t border-slate-800/80 bg-slate-950/90">
                <div class="flex items-center gap-2.5 px-2 py-1.5 mb-2.5">
                    <div class="w-8 h-8 rounded-lg bg-slate-800 text-white flex items-center justify-center font-extrabold text-xs shrink-0">
                        {{ strtoupper(substr(Auth::user()->name ?? 'A', 0, 1)) }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-bold text-white truncate">{{ Auth::user()->name ?? 'Admin Sport Mania' }}</p>
                        <p class="text-[10px] text-slate-500 truncate">{{ Auth::user()->email ?? 'admin@sportmania.id' }}</p>
                    </div>
                </div>
                <form id="admin-logout-form" method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="button"
                            @click="openConfirm({
                                title: 'Keluar dari Admin Panel?',
                                message: 'Sesi admin Anda saat ini akan diakhiri dan diarahkan ke halaman login.',
                                confirmText: 'Ya, Log Out',
                                variant: 'danger',
                                formId: 'admin-logout-form'
                            })"
                            class="w-full flex items-center justify-center gap-2 bg-slate-900 hover:bg-red-600 text-slate-300 hover:text-white px-3 py-2 rounded-xl text-xs font-bold transition-colors border border-slate-800 hover:border-red-600">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                        <span>Log Out</span>
                    </button>
                </form>
            </div>
        </aside>

        <div x-show="sidebarOpen" @click="sidebarOpen = false" x-cloak class="fixed inset-0 z-30 bg-slate-950/60 backdrop-blur-xs lg:hidden"></div>

        <!-- =================================================================== -->
        <!-- AREA KONTEN UTAMA                                                   -->
        <!-- =================================================================== -->
        <div class="flex-1 flex flex-col min-w-0">

            <header class="h-16 bg-white/90 backdrop-blur-md border-b border-slate-200/80 sticky top-0 z-20 px-4 sm:px-8 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <button @click="sidebarOpen = true" class="lg:hidden p-2 rounded-xl text-slate-600 hover:bg-slate-100">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    </button>
                    <div>
                        <h1 class="text-base sm:text-lg font-black text-slate-900 tracking-tight leading-none">
                            <span x-show="activeTab === 'dashboard'">Ringkasan Panel</span>
                            <span x-show="activeTab === 'kasir'">Kasir Toko Offline (POS)</span>
                            <span x-show="activeTab === 'produk'">Manajemen Produk</span>
                            <span x-show="activeTab === 'promo'">Kelola Promo & Banner</span>
                            <span x-show="activeTab === 'voucher'">Kode Voucher & Diskon</span>
                            <span x-show="activeTab === 'pesanan'">Kelola Pesanan</span>
                            <span x-show="activeTab === 'pelanggan'">Data Pelanggan</span>
                            <span x-show="activeTab === 'riwayat'">Riwayat & Log Aktivitas</span>
                            <span x-show="activeTab === 'rekap'">Rekap & Grafik Penjualan Bulanan</span>
                        </h1>
                        <p class="text-[11px] text-slate-400 font-medium mt-0.5 hidden sm:block">Sport Mania Distro • Store Management System</p>
                    </div>
                </div>

                <div class="flex items-center gap-2.5 sm:gap-3">
                    <button type="button"
                            @click="setTab('kasir')"
                            class="inline-flex items-center gap-1.5 bg-slate-900 hover:bg-red-600 text-white text-[11px] font-black uppercase tracking-wider px-3 py-1.5 rounded-xl transition shadow-xs">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                        <span>Buka Kasir</span>
                    </button>
                    <span class="hidden md:inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 bg-slate-100 px-3 py-1.5 rounded-full">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        {{ now()->translatedFormat('d M Y') }}
                    </span>
                    <div class="flex items-center gap-2 pl-2 border-l border-slate-200">
                        <div class="text-right hidden sm:block leading-tight">
                            <p class="text-xs font-bold text-slate-800">{{ Auth::user()->name ?? 'Admin Sport Mania' }}</p>
                            <p class="text-[10px] font-bold text-red-600 uppercase tracking-wider">Admin</p>
                        </div>
                        <div class="w-9 h-9 rounded-xl bg-slate-900 text-white flex items-center justify-center font-black text-xs shadow-xs">
                            {{ strtoupper(substr(Auth::user()->name ?? 'A', 0, 1)) }}
                        </div>
                    </div>
                </div>
            </header>

            <main class="flex-1 p-4 sm:p-8 max-w-7xl w-full mx-auto">

                @if(session('success'))
                    <div class="mb-6 bg-emerald-50 border border-emerald-200/80 text-emerald-800 px-4 py-3.5 rounded-2xl flex items-center justify-between shadow-2xs">
                        <div class="flex items-center gap-3">
                            <span class="w-6 h-6 rounded-full bg-emerald-600 text-white flex items-center justify-center text-xs font-bold shrink-0">✓</span>
                            <span class="text-xs sm:text-sm font-bold">{{ session('success') }}</span>
                        </div>
                    </div>
                @endif

                @if($errors->any())
                    <div class="mb-6 bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3.5 rounded-2xl text-xs font-bold">
                        <ul class="list-disc list-inside space-y-0.5">
                            @foreach($errors->all() as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <!-- =========================================================== -->
                <!-- TAB 1: RINGKASAN PANEL                                      -->
                <!-- =========================================================== -->
                <div x-show="activeTab === 'dashboard'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">

                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
                        <div>
                            <h2 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">
                                Selamat datang kembali, {{ Auth::user()->name ?? 'Admin Sport Mania' }} 👋
                            </h2>
                            <p class="text-xs sm:text-sm text-slate-500 mt-1">Pantau performa penjualan, pesanan aktif, dan ketersediaan stok Sport Mania Distro secara real-time.</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <button type="button"
                                    @click="setTab('rekap')"
                                    class="inline-flex items-center gap-2 bg-slate-900 hover:bg-red-600 text-white text-xs font-bold px-4 py-2.5 rounded-xl transition shadow-xs">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                                <span>Lihat Grafik Bulanan</span>
                            </button>
                            <button type="button"
                                    @click="exportOrdersCsv(false)"
                                    class="inline-flex items-center gap-2 bg-white hover:bg-slate-900 text-slate-700 hover:text-white text-xs font-bold px-4 py-2.5 rounded-xl border border-slate-200/80 transition shadow-2xs">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                <span>Unduh CSV</span>
                            </button>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-slate-200/80 px-4 py-3 mb-5 shadow-2xs flex flex-wrap items-center justify-between gap-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 mr-1">Periode Ringkasan:</span>
                            <button type="button"
                                    @click="dashPeriodMode = 'all'"
                                    :class="dashPeriodMode === 'all' ? 'bg-slate-900 text-white shadow-2xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                    class="px-3 py-1.5 rounded-xl text-xs font-bold transition">
                                Semua Waktu
                            </button>
                            <button type="button"
                                    @click="dashPeriodMode = 'monthly'"
                                    :class="dashPeriodMode === 'monthly' ? 'bg-red-600 text-white shadow-2xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                    class="px-3 py-1.5 rounded-xl text-xs font-bold transition">
                                Per Bulan
                            </button>

                            <template x-if="dashPeriodMode === 'monthly'">
                                <div class="flex items-center gap-2 ml-1">
                                    <select x-model="dashMonth" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-1.5 text-xs font-black text-slate-800 focus:ring-2 focus:ring-red-500 outline-none">
                                        <option value="1">Januari</option>
                                        <option value="2">Februari</option>
                                        <option value="3">Maret</option>
                                        <option value="4">April</option>
                                        <option value="5">Mei</option>
                                        <option value="6">Juni</option>
                                        <option value="7">Juli</option>
                                        <option value="8">Agustus</option>
                                        <option value="9">September</option>
                                        <option value="10">Oktober</option>
                                        <option value="11">November</option>
                                        <option value="12">Desember</option>
                                    </select>
                                    <select x-model="dashYear" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-1.5 text-xs font-black text-slate-800 focus:ring-2 focus:ring-red-500 outline-none">
                                        <template x-for="yr in getAvailableRecapYears()" :key="yr">
                                            <option :value="yr" x-text="yr"></option>
                                        </template>
                                    </select>
                                </div>
                            </template>
                        </div>

                        <span class="text-[11px] font-bold text-slate-500">
                            Klik kartu ringkasan untuk melihat rincian cepat
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5 mb-5">
                        <div @click="dashModal = { open: true, type: 'products' }"
                             class="bg-white p-6 rounded-2xl border border-slate-200/70 shadow-2xs hover:shadow-md hover:border-red-200 transition-all cursor-pointer group">
                            <div class="flex items-center justify-between mb-4">
                                <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400 group-hover:text-red-600 transition-colors">Total Produk</span>
                                <div class="w-9 h-9 rounded-xl bg-slate-100 text-slate-700 group-hover:bg-red-50 group-hover:text-red-600 flex items-center justify-center transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                                </div>
                            </div>
                            <div class="flex items-baseline gap-2">
                                <span class="text-3xl font-black text-slate-900 tabular-nums">{{ $products->count() }}</span>
                                <span class="text-xs font-semibold text-slate-400">katalog</span>
                            </div>
                            <p class="text-xs text-slate-500 mt-3 tabular-nums">Akumulasi stok: <strong class="text-slate-700">{{ $totalStockAll }} pcs</strong></p>
                        </div>

                        <div @click="dashModal = { open: true, type: 'orders' }"
                             class="bg-white p-6 rounded-2xl border border-slate-200/70 shadow-2xs hover:shadow-md hover:border-amber-300 transition-all cursor-pointer group">
                            <div class="flex items-center justify-between mb-4">
                                <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400 group-hover:text-amber-700 transition-colors">Pesanan Masuk</span>
                                <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                                </div>
                            </div>
                            <div class="flex items-baseline gap-2">
                                <span class="text-3xl font-black text-slate-900 tabular-nums" x-text="getDashboardStats().totalOrders"></span>
                                <span class="text-xs font-semibold text-slate-400">transaksi</span>
                            </div>
                            <div class="flex flex-wrap items-center gap-2 mt-3 text-xs tabular-nums">
                                <span class="text-slate-500">Perlu diproses: <strong class="text-amber-600" x-text="getDashboardStats().needProcess"></strong></span>
                                <template x-if="getDashboardStats().waitingVerify > 0">
                                    <span class="bg-amber-100 text-amber-800 text-[10px] font-black px-2 py-0.5 rounded-full" x-text="getDashboardStats().waitingVerify + ' cek bayar'"></span>
                                </template>
                            </div>
                        </div>

                        <div @click="dashModal = { open: true, type: 'customers' }"
                             class="bg-white p-6 rounded-2xl border border-slate-200/70 shadow-2xs hover:shadow-md hover:border-blue-200 transition-all cursor-pointer group">
                            <div class="flex items-center justify-between mb-4">
                                <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400 group-hover:text-blue-600 transition-colors"
                                      x-text="dashPeriodMode === 'all' ? 'Total Pelanggan' : 'Pelanggan Baru'"></span>
                                <div class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                </div>
                            </div>
                            <div class="flex items-baseline gap-2">
                                <span class="text-3xl font-black text-slate-900 tabular-nums" x-text="getDashboardStats().customerCount"></span>
                                <span class="text-xs font-semibold text-slate-400">akun aktif</span>
                            </div>
                            <p class="text-xs text-slate-500 mt-3" x-text="dashPeriodMode === 'all' ? 'Member terdaftar di sistem' : 'Terdaftar pada ' + getDashboardStats().periodLabel"></p>
                        </div>

                        <div @click="dashModal = { open: true, type: 'revenue' }"
                             class="bg-gradient-to-br from-slate-900 via-slate-950 to-slate-900 p-6 rounded-2xl border border-slate-800 shadow-lg hover:shadow-xl transition-all cursor-pointer relative overflow-hidden group">
                            <div class="absolute -right-6 -top-6 w-24 h-24 rounded-full bg-red-600/20 blur-2xl group-hover:bg-red-600/30 transition-all"></div>
                            <div class="flex items-center justify-between mb-4 relative">
                                <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Omzet Selesai</span>
                                <div class="w-9 h-9 rounded-xl bg-white/10 text-emerald-400 flex items-center justify-center">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                </div>
                            </div>
                            <div class="relative">
                                <span class="text-2xl font-black text-white tabular-nums tracking-tight block" x-text="'Rp ' + Number(getDashboardStats().realizedRevenue).toLocaleString('id-ID')"></span>
                                <p class="text-[11px] text-slate-400 mt-1.5 tabular-nums">
                                    Lunas Masuk: <strong class="text-emerald-400" x-text="'Rp ' + Number(getDashboardStats().paidRevenue).toLocaleString('id-ID')"></strong> (<span x-text="getDashboardStats().selesai"></span> selesai)
                                </p>
                                <p class="text-[11px] text-slate-400 mt-0.5 tabular-nums">
                                    Hari ini: <strong class="text-sky-400" x-text="'Rp ' + Number(getDashboardStats().todayRevenue).toLocaleString('id-ID')"></strong> • Pipeline: <strong class="text-amber-300" x-text="'Rp ' + Number(getDashboardStats().pipelineRevenue).toLocaleString('id-ID')"></strong>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-8">
                        <div @click="orderFilter = 'pending'; setTab('pesanan')" class="bg-white px-4 py-3.5 rounded-xl border border-slate-200/70 hover:border-amber-400 cursor-pointer transition shadow-2xs">
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-amber-600 block">Pending</span>
                            <span class="text-xl font-black text-slate-900 tabular-nums mt-0.5 block" x-text="getDashboardStats().pending"></span>
                        </div>
                        <div @click="orderFilter = 'diproses'; setTab('pesanan')" class="bg-white px-4 py-3.5 rounded-xl border border-slate-200/70 hover:border-blue-400 cursor-pointer transition shadow-2xs">
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-blue-600 block">Diproses</span>
                            <span class="text-xl font-black text-slate-900 tabular-nums mt-0.5 block" x-text="getDashboardStats().diproses"></span>
                        </div>
                        <div @click="orderFilter = 'dikirim'; setTab('pesanan')" class="bg-white px-4 py-3.5 rounded-xl border border-slate-200/70 hover:border-purple-400 cursor-pointer transition shadow-2xs">
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-purple-600 block">Dikirim</span>
                            <span class="text-xl font-black text-slate-900 tabular-nums mt-0.5 block" x-text="getDashboardStats().dikirim"></span>
                        </div>
                        <div @click="orderFilter = 'selesai'; setTab('pesanan')" class="bg-white px-4 py-3.5 rounded-xl border border-slate-200/70 hover:border-emerald-400 cursor-pointer transition shadow-2xs">
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-emerald-600 block">Selesai</span>
                            <span class="text-xl font-black text-slate-900 tabular-nums mt-0.5 block" x-text="getDashboardStats().selesai"></span>
                        </div>
                        <div @click="orderFilter = 'dibatalkan'; setTab('pesanan')" class="bg-white px-4 py-3.5 rounded-xl border border-slate-200/70 hover:border-rose-400 cursor-pointer transition shadow-2xs col-span-2 sm:col-span-1">
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-rose-600 block">Dibatalkan</span>
                            <span class="text-xl font-black text-slate-900 tabular-nums mt-0.5 block" x-text="getDashboardStats().dibatalkan"></span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                        <div class="lg:col-span-7 bg-white rounded-2xl border border-slate-200/70 shadow-2xs p-6">
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <h3 class="text-sm font-black uppercase tracking-wider text-slate-900">Peringatan Stok Menipis</h3>
                                    <p class="text-xs text-slate-400 mt-0.5">Produk dengan total stok ≤ 10 pcs atau memiliki varian stok ≤ 3 pcs.</p>
                                </div>
                                <span class="text-[11px] font-extrabold px-2.5 py-1 rounded-full bg-rose-50 text-rose-600 border border-rose-100 tabular-nums">
                                    {{ $lowStockProducts->count() }} Produk
                                </span>
                            </div>

                            @if($lowStockProducts->count() > 0)
                                <div class="divide-y divide-slate-100">
                                    @foreach($lowStockProducts->take(4) as $lowItem)
                                        @php
                                            $stokItem = ($lowItem->variants && $lowItem->variants->count() > 0)
                                                ? $lowItem->variants->sum('stock')
                                                : ($lowItem->stock ?? 0);
                                            $emptyVariants = ($lowItem->variants && $lowItem->variants->count() > 0)
                                                ? $lowItem->variants->filter(fn($v) => (int) ($v->stock ?? 0) <= 0)
                                                : collect();
                                        @endphp
                                        <div class="py-3 flex items-center justify-between gap-4">
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center gap-2.5">
                                                    <span class="text-xs font-black tabular-nums w-6 text-right shrink-0 {{ $stokItem <= 5 ? 'text-rose-600' : 'text-amber-600' }}">{{ $stokItem }}</span>
                                                    <div class="min-w-0">
                                                        <p class="text-xs font-bold text-slate-900 truncate">{{ $lowItem->name }}</p>
                                                        <p class="text-[11px] text-slate-400">Kategori: {{ optional($lowItem->category)->name ?? 'Umum' }}</p>
                                                    </div>
                                                </div>
                                                @if($emptyVariants->count() > 0)
                                                    <div class="flex flex-wrap gap-1 mt-1.5 pl-8">
                                                        @foreach($emptyVariants->take(4) as $ev)
                                                            <span class="text-[9px] font-bold bg-slate-900 text-white px-1.5 py-0.5 rounded">
                                                                {{ $ev->size }}{{ filled($ev->color) ? ' (' . $ev->color . ')' : '' }}: Habis
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                            <a href="{{ route('admin.produk.edit', $lowItem->id) }}" class="text-[11px] font-bold text-blue-600 hover:underline shrink-0">
                                                + Update Stok
                                            </a>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="p-6 text-center text-xs text-slate-400 bg-slate-50 rounded-2xl border border-dashed border-slate-200">
                                    Semua stok produk dalam kondisi aman.
                                </div>
                            @endif
                        </div>

                        <div class="lg:col-span-5 bg-white rounded-2xl border border-slate-200/70 shadow-2xs p-6 flex flex-col justify-between">
                            <div>
                                <h3 class="text-sm font-black uppercase tracking-wider text-slate-900">Aksi Cepat Admin</h3>
                                <p class="text-xs text-slate-400 mt-0.5 mb-5">Pintasan pengelolaan katalog dan transaksi.</p>

                                <div class="space-y-2.5">
                                    <a href="{{ route('admin.produk.create') }}" class="w-full flex items-center justify-between px-4 py-3 rounded-xl bg-slate-50 hover:bg-red-50 text-slate-800 hover:text-red-700 border border-slate-200/70 hover:border-red-200 text-xs font-bold transition-all group">
                                        <span>+ Tambah Produk Baru</span>
                                        <span class="text-slate-400 group-hover:text-red-600 transition-transform group-hover:translate-x-0.5">→</span>
                                    </a>
                                    <button type="button" @click="setTab('voucher'); voucherFormOpen = true" class="w-full flex items-center justify-between px-4 py-3 rounded-xl bg-slate-50 hover:bg-red-50 text-slate-800 hover:text-red-700 border border-slate-200/70 hover:border-red-200 text-xs font-bold transition-all group">
                                        <span>+ Buat Kode Voucher Diskon</span>
                                        <span class="text-slate-400 group-hover:text-red-600 transition-transform group-hover:translate-x-0.5">→</span>
                                    </button>
                                    <button type="button" @click="setTab('promo'); openNewPromo()" class="w-full flex items-center justify-between px-4 py-3 rounded-xl bg-slate-50 hover:bg-red-50 text-slate-800 hover:text-red-700 border border-slate-200/70 hover:border-red-200 text-xs font-bold transition-all group">
                                        <span>+ Buat Postingan Promo Beranda</span>
                                        <span class="text-slate-400 group-hover:text-red-600 transition-transform group-hover:translate-x-0.5">→</span>
                                    </button>
                                    <button type="button" @click="setTab('rekap')" class="w-full flex items-center justify-between px-4 py-3 rounded-xl bg-slate-50 hover:bg-slate-900 text-slate-800 hover:text-white border border-slate-200/70 text-xs font-bold transition-all group">
                                        <span>📊 Buka Rekap & Grafik Bulanan</span>
                                        <span class="text-slate-400 group-hover:text-white transition-transform group-hover:translate-x-0.5">→</span>
                                    </button>
                                </div>
                            </div>

                            <div class="mt-6 pt-4 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-400">
                                <span>Sport Mania Distro • Malang, Jawa Timur</span>
                                <span class="font-bold text-emerald-600">● Online</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- =========================================================== -->
                <!-- TAB KASIR (POS OFFLINE TOKO)                                -->
                <!-- =========================================================== -->
                <div x-show="activeTab === 'kasir'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
                        <div>
                            <h3 class="text-base font-black text-slate-900">Kasir Toko Offline (Point of Sale)</h3>
                            <p class="text-xs text-slate-500 mt-0.5">Catat transaksi pembeli langsung di toko, potong stok varian otomatis, dan cetak struk 80mm.</p>
                        </div>
                        <div class="flex items-center gap-2 text-xs">
                            <span class="bg-emerald-50 text-emerald-700 border border-emerald-200 font-bold px-3 py-1.5 rounded-xl tabular-nums">
                                Keranjang: <strong x-text="posCart.reduce((s, i) => s + i.qty, 0)"></strong> pcs
                            </span>
                            <button type="button"
                                    x-show="posCart.length > 0"
                                    @click="clearPosCart()"
                                    class="bg-white hover:bg-rose-50 text-rose-600 border border-rose-200 font-bold px-3 py-1.5 rounded-xl transition">
                                Kosongkan
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                        <div class="lg:col-span-7 bg-white rounded-2xl border border-slate-200/80 shadow-2xs p-5">
                            <div class="flex flex-col sm:flex-row gap-2.5 mb-4">
                                <div class="relative flex-1">
                                    <input type="text"
                                           x-model="posSearch"
                                           placeholder="Cari nama produk untuk transaksi kasir..."
                                           class="w-full bg-slate-50 text-xs text-slate-900 rounded-xl pl-9 pr-3 py-2.5 border border-slate-200 focus:outline-none focus:ring-2 focus:ring-red-500 font-medium">
                                    <svg class="w-4 h-4 absolute left-3 top-2.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                </div>
                                <select x-model="posCategory" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs font-bold text-slate-700 focus:ring-2 focus:ring-red-500 outline-none">
                                    <option value="semua">Semua Kategori</option>
                                    <option value="pakaian">Pakaian</option>
                                    <option value="sepatu">Sepatu</option>
                                    <option value="tas">Tas</option>
                                    <option value="aksesoris">Aksesoris</option>
                                </select>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 max-h-[620px] overflow-y-auto custom-scrollbar pr-1">
                                <template x-for="prod in getPosFilteredProducts()" :key="prod.id">
                                    <div class="border border-slate-200/80 rounded-2xl p-3.5 flex flex-col justify-between hover:border-red-200 transition bg-white">
                                        <div class="flex items-start gap-3">
                                            <template x-if="prod.image">
                                                <img :src="prod.image" :alt="prod.name" class="w-14 h-14 rounded-xl object-cover border border-slate-200 shrink-0">
                                            </template>
                                            <template x-if="!prod.image">
                                                <div class="w-14 h-14 rounded-xl bg-slate-100 border border-slate-200 flex items-center justify-center text-lg shrink-0">👕</div>
                                            </template>
                                            <div class="min-w-0 flex-1">
                                                <span class="text-[10px] font-bold uppercase text-slate-400 block" x-text="prod.category_name"></span>
                                                <h4 class="text-xs font-black text-slate-900 line-clamp-2" x-text="prod.name"></h4>
                                                <p class="text-xs font-black text-red-600 tabular-nums mt-1" x-text="'Rp ' + Number(prod.price).toLocaleString('id-ID')"></p>
                                            </div>
                                        </div>

                                        <div class="mt-3 pt-3 border-t border-slate-100 space-y-2">
                                            <template x-if="prod.has_variants">
                                                <div>
                                                    <div class="flex items-center justify-between text-[10px] mb-1">
                                                        <span class="font-bold uppercase text-slate-400">Varian</span>
                                                        <span class="font-bold tabular-nums"
                                                              :class="getPosAvailableStock(prod) > 0 ? 'text-emerald-600' : 'text-rose-600'"
                                                              x-text="'Stok: ' + getPosAvailableStock(prod) + ' pcs'"></span>
                                                    </div>
                                                    <select x-model="posSelectedVariants[prod.id]"
                                                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-2.5 py-2 text-[11px] font-bold text-slate-800 focus:ring-2 focus:ring-red-500 outline-none">
                                                        <template x-for="v in prod.variants" :key="v.id">
                                                            <option :value="v.id"
                                                                    :disabled="v.stock <= 0"
                                                                    x-text="v.size + (v.color ? ' • ' + v.color : '') + ' (Sisa ' + v.stock + ')'"></option>
                                                        </template>
                                                    </select>
                                                </div>
                                            </template>

                                            <template x-if="!prod.has_variants">
                                                <div class="flex items-center justify-between text-[11px] py-1">
                                                    <span class="text-rose-600 font-bold">Belum ada varian stok</span>
                                                    <span class="text-slate-400 tabular-nums" x-text="prod.stock + ' pcs'"></span>
                                                </div>
                                            </template>

                                            <button type="button"
                                                    @click="addToPosCart(prod)"
                                                    :disabled="!prod.has_variants || getPosAvailableStock(prod) <= 0"
                                                    class="w-full py-2 rounded-xl text-[11px] font-black uppercase tracking-wider transition flex items-center justify-center gap-1.5"
                                                    :class="(!prod.has_variants || getPosAvailableStock(prod) <= 0)
                                                        ? 'bg-slate-100 text-slate-400 cursor-not-allowed'
                                                        : 'bg-slate-900 hover:bg-red-600 text-white shadow-2xs'">
                                                <span>+ Tambah ke Keranjang</span>
                                            </button>
                                        </div>
                                    </div>
                                </template>

                                <template x-if="getPosFilteredProducts().length === 0">
                                    <div class="col-span-full py-12 text-center text-xs text-slate-400 bg-slate-50 rounded-2xl border border-dashed border-slate-200">
                                        Produk tidak ditemukan sesuai pencarian/filter.
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div class="lg:col-span-5 bg-white rounded-2xl border border-slate-200/80 shadow-2xs p-5">
                            <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-100">
                                <h4 class="text-xs font-black uppercase tracking-wider text-slate-900">Keranjang & Pembayaran Kasir</h4>
                                <span class="text-[10px] font-bold bg-slate-100 text-slate-600 px-2.5 py-1 rounded-lg">POS OFFLINE</span>
                            </div>

                            <div class="space-y-2.5 max-h-60 overflow-y-auto custom-scrollbar mb-4">
                                <template x-for="(item, idx) in posCart" :key="item.key">
                                    <div class="flex items-center justify-between gap-2.5 bg-slate-50 border border-slate-200/70 rounded-xl p-2.5 text-xs">
                                        <div class="min-w-0 flex-1">
                                            <p class="font-bold text-slate-900 truncate" x-text="item.name"></p>
                                            <p class="text-[10px] text-slate-500">
                                                <span x-text="item.size + (item.color ? ' • ' + item.color : '')"></span> •
                                                <span class="tabular-nums" x-text="'Rp ' + Number(item.price).toLocaleString('id-ID')"></span>
                                            </p>
                                        </div>
                                        <div class="flex items-center gap-1 shrink-0">
                                            <button type="button" @click="updatePosCartQty(idx, -1)" class="w-6 h-6 rounded-lg bg-white border border-slate-200 font-black text-slate-700 hover:bg-slate-100">-</button>
                                            <span class="w-6 text-center font-black text-slate-900 tabular-nums" x-text="item.qty"></span>
                                            <button type="button" @click="updatePosCartQty(idx, 1)" class="w-6 h-6 rounded-lg bg-white border border-slate-200 font-black text-slate-700 hover:bg-slate-100">+</button>
                                        </div>
                                        <div class="text-right shrink-0 tabular-nums">
                                            <p class="font-black text-slate-900" x-text="'Rp ' + Number(item.price * item.qty).toLocaleString('id-ID')"></p>
                                            <button type="button" @click="removePosCartItem(idx)" class="text-[10px] font-bold text-rose-600 hover:underline">Hapus</button>
                                        </div>
                                    </div>
                                </template>

                                <template x-if="posCart.length === 0">
                                    <div class="py-8 text-center text-xs text-slate-400 bg-slate-50 rounded-xl border border-dashed border-slate-200">
                                        Belum ada item di keranjang kasir. Klik produk di sebelah kiri untuk menambahkan.
                                    </div>
                                </template>
                            </div>

                            <form action="{{ \Illuminate\Support\Facades\Route::has('admin.pos.checkout') ? route('admin.pos.checkout') : url('/admin/pos/checkout') }}"
                                  method="POST"
                                  class="space-y-3 text-xs border-t border-slate-100 pt-4">
                                @csrf
                                <input type="hidden" name="items_json" :value="JSON.stringify(posCart.map(i => ({ product_id: i.product_id, variant_id: i.variant_id, qty: i.qty, price: i.price, hpp: i.hpp })))">
                                <input type="hidden" name="discount" :value="Math.max(0, Number(posDiscount || 0))">
                                <input type="hidden" name="total_amount" :value="getPosTotal()">

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                    <div>
                                        <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">Nama Pembeli</label>
                                        <input type="text" name="customer_name" x-model="posCustomerName" placeholder="Pelanggan Walk-in" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 font-bold text-slate-900 focus:ring-2 focus:ring-red-500 outline-none">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">No. HP / WA (Opsional)</label>
                                        <input type="text" name="customer_phone" x-model="posCustomerPhone" placeholder="08..." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 font-semibold text-slate-900 focus:ring-2 focus:ring-red-500 outline-none">
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                    <div>
                                        <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">Metode Bayar</label>
                                        <select name="payment_method" x-model="posPaymentMethod" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 font-bold text-slate-800 focus:ring-2 focus:ring-red-500 outline-none">
                                            <option value="TUNAI">Tunai (Cash)</option>
                                            <option value="QRIS">QRIS</option>
                                            <option value="TRANSFER">Transfer Bank</option>
                                            <option value="DEBIT">Kartu Debit</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">Potongan / Diskon (Rp)</label>
                                        <input type="number" min="0" x-model.number="posDiscount" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 font-bold text-slate-900 focus:ring-2 focus:ring-red-500 outline-none tabular-nums">
                                    </div>
                                </div>

                                <template x-if="posPaymentMethod === 'TUNAI'">
                                    <div class="bg-slate-50 border border-slate-200/80 rounded-xl p-3 space-y-2">
                                        <div class="flex items-center justify-between gap-2">
                                            <label class="text-[10px] font-black uppercase tracking-wider text-slate-600">Uang Diterima (Rp)</label>
                                            <input type="number" name="cash_received" min="0" x-model.number="posCashReceived" class="w-36 bg-white border border-slate-200 rounded-lg px-2.5 py-1.5 text-right font-black text-slate-900 focus:ring-2 focus:ring-red-500 outline-none tabular-nums">
                                        </div>
                                        <div class="flex flex-wrap gap-1.5">
                                            <button type="button" @click="posCashReceived = getPosTotal()" class="px-2 py-1 rounded-md bg-white border border-slate-200 text-[10px] font-bold text-slate-700 hover:bg-slate-100">Uang Pas</button>
                                            <button type="button" @click="posCashReceived = 50000" class="px-2 py-1 rounded-md bg-white border border-slate-200 text-[10px] font-bold text-slate-700 hover:bg-slate-100 tabular-nums">50rb</button>
                                            <button type="button" @click="posCashReceived = 100000" class="px-2 py-1 rounded-md bg-white border border-slate-200 text-[10px] font-bold text-slate-700 hover:bg-slate-100 tabular-nums">100rb</button>
                                            <button type="button" @click="posCashReceived = 200000" class="px-2 py-1 rounded-md bg-white border border-slate-200 text-[10px] font-bold text-slate-700 hover:bg-slate-100 tabular-nums">200rb</button>
                                        </div>
                                        <div class="flex justify-between text-xs pt-1 border-t border-slate-200/70 tabular-nums">
                                            <span class="font-bold text-slate-600">Kembalian</span>
                                            <span class="font-black text-emerald-700" x-text="'Rp ' + Number(getPosChange()).toLocaleString('id-ID')"></span>
                                        </div>
                                    </div>
                                </template>

                                <div class="bg-slate-950 text-white rounded-2xl p-3.5 space-y-1 tabular-nums">
                                    <div class="flex justify-between text-[11px] text-slate-400">
                                        <span>Subtotal</span>
                                        <span x-text="'Rp ' + Number(getPosSubtotal()).toLocaleString('id-ID')"></span>
                                    </div>
                                    <div class="flex justify-between text-[11px] text-slate-400">
                                        <span>Diskon</span>
                                        <span x-text="'- Rp ' + Number(Math.max(0, posDiscount || 0)).toLocaleString('id-ID')"></span>
                                    </div>
                                    <div class="flex justify-between text-sm font-black pt-1.5 border-t border-slate-800">
                                        <span>TOTAL BAYAR</span>
                                        <span class="text-emerald-400" x-text="'Rp ' + Number(getPosTotal()).toLocaleString('id-ID')"></span>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 pt-1">
                                    <button type="button"
                                            @click="previewPosReceipt()"
                                            class="px-3.5 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-xs transition">
                                        Preview Struk
                                    </button>
                                    <button type="submit"
                                            :disabled="!canSubmitPosCheckout()"
                                            class="flex-1 py-2.5 px-4 rounded-xl font-black text-xs uppercase tracking-wider transition flex items-center justify-center gap-1.5"
                                            :class="canSubmitPosCheckout()
                                                ? 'bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-700 hover:to-rose-700 text-white shadow-md shadow-red-600/20'
                                                : 'bg-slate-200 text-slate-400 cursor-not-allowed'">
                                        <span>Proses Transaksi POS</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- =========================================================== -->
                <!-- TAB 2: MANAJEMEN PRODUK                                     -->
                <!-- =========================================================== -->
                <div x-show="activeTab === 'produk'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
                        <div>
                            <h3 class="text-base font-black text-slate-900">Katalog & Stok Produk</h3>
                            <p class="text-xs text-slate-500 mt-0.5">Kelola daftar produk, harga, kategori, dan stok per ukuran/warna.</p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2.5 w-full md:w-auto">
                            <div class="relative flex-1 sm:w-56">
                                <input type="text" x-model="searchProduct" placeholder="Cari nama produk..." class="w-full bg-white text-xs text-slate-900 rounded-xl pl-9 pr-3 py-2.5 border border-slate-200/80 focus:outline-none focus:ring-2 focus:ring-red-500 transition shadow-2xs font-medium">
                                <svg class="w-4 h-4 absolute left-3 top-2.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                            </div>

                            <select x-model="filterCategory" class="bg-white text-xs font-bold text-slate-700 rounded-xl px-3.5 py-2.5 border border-slate-200/80 focus:outline-none focus:ring-2 focus:ring-red-500 shadow-2xs">
                                <option value="semua">Semua Kategori</option>
                                <option value="pakaian">Pakaian</option>
                                <option value="sepatu">Sepatu</option>
                                <option value="tas">Tas</option>
                                <option value="aksesoris">Aksesoris</option>
                            </select>

                            <a href="{{ route('admin.produk.create') }}" class="bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-700 hover:to-rose-700 text-white px-4 py-2.5 rounded-xl font-bold text-xs uppercase tracking-wider flex items-center gap-1.5 transition shadow-md shadow-red-600/20 shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                                <span>Tambah Produk</span>
                            </a>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl shadow-2xs border border-slate-200/70 overflow-hidden">
                        <div class="overflow-x-auto custom-scrollbar">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-slate-50/80 border-b border-slate-200/70 text-[10px] uppercase tracking-widest text-slate-500 font-bold">
                                        <th class="px-6 py-4">Produk</th>
                                        <th class="px-6 py-4">Kategori</th>
                                        <th class="px-6 py-4">Harga Satuan</th>
                                        <th class="px-6 py-4">Rincian Stok & Varian</th>
                                        <th class="px-6 py-4 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse($products as $product)
                                        @php
                                            $totalStok = ($product->variants && $product->variants->count() > 0)
                                                ? $product->variants->sum('stock')
                                                : ($product->stock ?? 0);
                                            $catSlug = strtolower(optional($product->category)->slug ?? optional($product->category)->name ?? '');
                                            $prodNameLower = strtolower($product->name);
                                        @endphp
                                        <tr x-show="(filterCategory === 'semua' || @js($catSlug).includes(filterCategory)) && (@js($prodNameLower).includes(searchProduct.toLowerCase().trim()))"
                                            class="hover:bg-slate-50/80 transition-colors">
                                            <td class="px-6 py-4">
                                                <div class="flex items-center gap-3.5">
                                                    @if($product->image)
                                                        <img src="{{ filter_var($product->image, FILTER_VALIDATE_URL) ? $product->image : asset('storage/' . ltrim($product->image, '/')) }}" alt="{{ $product->name }}" class="w-12 h-12 rounded-xl object-cover border border-slate-200/80 shrink-0 shadow-2xs">
                                                    @else
                                                        <div class="w-12 h-12 rounded-xl bg-slate-100 border border-slate-200/80 flex items-center justify-center text-lg shrink-0">👕</div>
                                                    @endif
                                                    <div>
                                                        <span class="font-bold text-slate-900 text-sm block">{{ $product->name }}</span>
                                                        <span class="text-[11px] text-slate-400 font-medium">ID #{{ $product->id }}</span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <span class="bg-slate-100 text-slate-700 text-[11px] font-bold px-2.5 py-1 rounded-lg">
                                                    {{ optional($product->category)->name ?? 'Umum' }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 font-extrabold text-slate-900 text-sm tabular-nums">
                                                Rp {{ number_format($product->price, 0, ',', '.') }}
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="flex items-center gap-2 mb-1.5">
                                                    @if($totalStok > 10)
                                                        <span class="bg-emerald-50 text-emerald-700 border border-emerald-200/70 text-[11px] font-extrabold px-2.5 py-0.5 rounded-full tabular-nums">
                                                            {{ $totalStok }} pcs tersedia
                                                        </span>
                                                    @elseif($totalStok > 0)
                                                        <span class="bg-amber-50 text-amber-700 border border-amber-200/70 text-[11px] font-extrabold px-2.5 py-0.5 rounded-full tabular-nums">
                                                            Sisa {{ $totalStok }} pcs
                                                        </span>
                                                    @else
                                                        <span class="bg-rose-50 text-rose-700 border border-rose-200/70 text-[11px] font-extrabold px-2.5 py-0.5 rounded-full">
                                                            Habis
                                                        </span>
                                                    @endif
                                                </div>

                                                @if($product->variants && $product->variants->count() > 0)
                                                    <div class="flex flex-wrap gap-1">
                                                        @foreach($product->variants as $v)
                                                            <span class="text-[10px] font-semibold px-2 py-0.5 rounded-md border tabular-nums {{ ($v->stock ?? 0) > 0 ? 'bg-slate-50 text-slate-600 border-slate-200/80' : 'bg-rose-50/70 text-rose-600 border-rose-200/70 line-through' }}">
                                                                {{ $v->size }}{{ filled($v->color) ? ' • ' . $v->color : '' }}: {{ $v->stock ?? 0 }}
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 text-right">
                                                <div class="inline-flex items-center justify-end gap-1.5">
                                                    <a href="{{ route('admin.produk.edit', $product->id) }}" class="p-2 text-blue-600 hover:bg-blue-50 rounded-xl transition-colors" title="Edit Produk">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                                    </a>
                                                    <form id="delete-product-{{ $product->id }}" action="{{ route('admin.produk.destroy', $product->id) }}" method="POST" class="hidden">
                                                        @csrf
                                                        @method('DELETE')
                                                    </form>
                                                    <button type="button"
                                                            @click="openConfirm({
                                                                title: 'Hapus Produk Ini?',
                                                                message: 'Produk ' + @js($product->name) + ' beserta data variannya akan dihapus permanen dari katalog.',
                                                                confirmText: 'Ya, Hapus Produk',
                                                                variant: 'danger',
                                                                formId: 'delete-product-{{ $product->id }}'
                                                            })"
                                                            class="p-2 text-rose-600 hover:bg-rose-50 rounded-xl transition-colors"
                                                            title="Hapus Produk">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-6 py-12 text-center">
                                                <p class="text-slate-400 text-sm font-medium mb-3">Belum ada produk di katalog Sport Mania Distro.</p>
                                                <a href="{{ route('admin.produk.create') }}" class="inline-flex items-center gap-1.5 bg-red-600 text-white px-4 py-2 rounded-xl text-xs font-bold hover:bg-red-700 transition">
                                                    + Tambah Produk Pertama
                                                </a>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- =========================================================== -->
                <!-- TAB PROMO & BANNER BERANDA                                  -->
                <!-- =========================================================== -->
                <div x-show="activeTab === 'promo'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                        <div>
                            <h3 class="text-base font-black text-slate-900">Kelola Promo & Banner Beranda</h3>
                            <p class="text-xs text-slate-500 mt-0.5">Buat dan atur postingan promo/diskon pada posisi Hero Utama atau Showcase di beranda toko.</p>
                        </div>
                        <button type="button"
                                @click="openNewPromo()"
                                class="bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-700 hover:to-rose-700 text-white px-5 py-2.5 rounded-xl font-bold text-xs uppercase tracking-wider flex items-center gap-2 transition shadow-md shadow-red-600/20">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                            <span>Buat Promo Baru</span>
                        </button>
                    </div>

                    <div x-show="promoFormOpen" x-transition class="mb-6 bg-white rounded-2xl border border-slate-200/80 shadow-2xs p-6">
                        <div class="flex items-center justify-between pb-4 mb-5 border-b border-slate-100">
                            <h4 class="text-sm font-black uppercase tracking-wider text-slate-900" x-text="editingPromo ? 'Edit Postingan Promo' : 'Buat Postingan Promo Baru'"></h4>
                            <button type="button" @click="promoFormOpen = false" class="text-xs font-bold text-slate-400 hover:text-rose-600">✕ Tutup</button>
                        </div>

                        <form :action="editingPromo ? editingPromo.update_url : '{{ \Illuminate\Support\Facades\Route::has('admin.promos.store') ? route('admin.promos.store') : url('/admin/promos') }}'"
                              method="POST"
                              enctype="multipart/form-data"
                              class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                            @csrf
                            <template x-if="editingPromo">
                                <input type="hidden" name="_method" value="PUT">
                            </template>

                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1.5">Posisi Tampil (Placement) *</label>
                                <select name="placement"
                                        required
                                        class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-bold text-slate-900 focus:ring-2 focus:ring-red-500 outline-none">
                                    <option value="hero" :selected="!editingPromo || editingPromo.placement === 'hero'">Hero Banner Utama (Atas Beranda)</option>
                                    <option value="showcase" :selected="editingPromo && editingPromo.placement === 'showcase'">Showcase Promo (Etalase Sorotan)</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1.5">Urutan Tampil (Sort Order)</label>
                                <input type="number"
                                       name="sort_order"
                                       min="1"
                                       :value="editingPromo ? editingPromo.sort_order : 1"
                                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-bold text-slate-900 focus:ring-2 focus:ring-red-500 outline-none tabular-nums">
                            </div>

                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1.5">Judul Promo *</label>
                                <input type="text"
                                       name="title"
                                       :value="editingPromo ? editingPromo.title : ''"
                                       required
                                       placeholder="Contoh: Diskon Spesial Jersey Original 25%"
                                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-bold text-slate-900 focus:ring-2 focus:ring-red-500 outline-none">
                            </div>

                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1.5">Subjudul / Tagline Singkat (Opsional)</label>
                                <input type="text"
                                       name="subtitle"
                                       :value="editingPromo ? editingPromo.subtitle : ''"
                                       placeholder="Contoh: Koleksi Terbatas Musim 2026"
                                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-semibold text-slate-900 focus:ring-2 focus:ring-red-500 outline-none">
                            </div>

                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1.5">Label Badge / Tag (Opsional)</label>
                                <input type="text"
                                       name="badge_text"
                                       :value="editingPromo ? editingPromo.badge_text : ''"
                                       placeholder="Contoh: FLASH SALE, NEW ARRIVAL, HEMAT 25%"
                                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-bold text-slate-900 focus:ring-2 focus:ring-red-500 outline-none">
                            </div>

                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1.5">Teks Watermark Latar (Opsional)</label>
                                <input type="text"
                                       name="bg_typography"
                                       maxlength="40"
                                       :value="editingPromo ? editingPromo.bg_typography : ''"
                                       placeholder="Otomatis dari kata pertama jika dikosongkan"
                                       class="w-full uppercase bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-bold text-slate-900 focus:ring-2 focus:ring-red-500 outline-none">
                            </div>

                            <div class="md:col-span-2">
                                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1.5">Deskripsi Promo</label>
                                <textarea name="description"
                                          rows="2"
                                          x-text="editingPromo ? editingPromo.description : ''"
                                          placeholder="Tuliskan detail promo, syarat & ketentuan, atau periode berlaku..."
                                          class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-medium text-slate-800 focus:ring-2 focus:ring-red-500 outline-none"></textarea>
                            </div>

                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1.5">Media Banner (Gambar / Video MP4/WebM)</label>
                                <input type="file"
                                       name="image"
                                       accept="image/jpeg,image/png,image/webp,video/mp4,video/webm"
                                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-700 file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-slate-900 file:text-white hover:file:bg-red-600">
                                <p class="text-[10px] text-slate-400 mt-1">Format JPG, PNG, WEBP, MP4, atau WEBM (maks 20MB).</p>
                            </div>

                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1.5">Keterangan Media / Caption (Opsional)</label>
                                <input type="text"
                                       name="image_caption"
                                       :value="editingPromo ? editingPromo.image_caption : ''"
                                       placeholder="Contoh: Jersey Home Edition 2026"
                                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-semibold text-slate-800 focus:ring-2 focus:ring-red-500 outline-none">
                            </div>

                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1.5">Teks Tombol CTA *</label>
                                <input type="text"
                                       name="button_text"
                                       required
                                       :value="editingPromo ? editingPromo.button_text : 'Belanja Sekarang'"
                                       placeholder="Contoh: Belanja Sekarang"
                                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-bold text-slate-800 focus:ring-2 focus:ring-red-500 outline-none">
                            </div>

                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1.5">Link Tujuan Tombol *</label>
                                <input type="text"
                                       name="button_url"
                                       required
                                       :value="editingPromo ? editingPromo.button_url : '/produk'"
                                       placeholder="Contoh: /produk atau https://..."
                                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-semibold text-slate-800 focus:ring-2 focus:ring-red-500 outline-none">
                            </div>

                            <div class="md:col-span-2 flex items-center gap-3 pt-2">
                                <input type="checkbox"
                                       id="promo_is_active"
                                       name="is_active"
                                       value="1"
                                       :checked="editingPromo ? Boolean(editingPromo.is_active) : true"
                                       class="w-4 h-4 rounded text-red-600 focus:ring-red-500">
                                <label for="promo_is_active" class="font-bold text-slate-800">Tampilkan promo ini di beranda toko</label>
                            </div>

                            <div class="md:col-span-2 flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                                <button type="button" @click="promoFormOpen = false" class="px-4 py-2.5 rounded-xl font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 transition">Batal</button>
                                <button type="submit" class="px-5 py-2.5 rounded-xl font-black uppercase tracking-wider text-white bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-700 hover:to-rose-700 shadow-md shadow-red-600/20 transition">
                                    <span x-text="editingPromo ? 'Update Promo' : 'Simpan Promo'"></span>
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                        @forelse($promos as $promo)
                            @php
                                $promoImgRaw = $promo->image ?? null;
                                $promoVidRaw = $promo->video_path ?? null;
                                $promoImgUrl = filled($promoImgRaw)
                                    ? (filter_var($promoImgRaw, FILTER_VALIDATE_URL) ? $promoImgRaw : asset('storage/' . ltrim($promoImgRaw, '/')))
                                    : null;
                                $promoVidUrl = filled($promoVidRaw)
                                    ? (filter_var($promoVidRaw, FILTER_VALIDATE_URL) ? $promoVidRaw : asset('storage/' . ltrim($promoVidRaw, '/')))
                                    : null;
                                $badgeLabel = $promo->badge_text ?? $promo->badge ?? '';
                                $promoPayload = [
                                    'id'            => $promo->id,
                                    'placement'     => $promo->placement ?? 'hero',
                                    'sort_order'    => $promo->sort_order ?? 1,
                                    'title'         => $promo->title ?? '',
                                    'subtitle'      => $promo->subtitle ?? '',
                                    'badge_text'    => $badgeLabel,
                                    'bg_typography' => $promo->bg_typography ?? '',
                                    'description'   => $promo->description ?? '',
                                    'image_caption' => $promo->image_caption ?? '',
                                    'button_url'    => $promo->button_url ?? '/produk',
                                    'button_text'   => $promo->button_text ?? 'Belanja Sekarang',
                                    'is_active'     => (bool) ($promo->is_active ?? true),
                                    'update_url'    => \Illuminate\Support\Facades\Route::has('admin.promos.update') ? route('admin.promos.update', $promo->id) : url('/admin/promos/' . $promo->id),
                                ];
                            @endphp
                            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-2xs overflow-hidden flex flex-col justify-between">
                                <div>
                                    @if(($promo->media_type ?? 'image') === 'video' && $promoVidUrl)
                                        <div class="h-40 bg-slate-900 overflow-hidden relative">
                                            <video src="{{ $promoVidUrl }}" muted loop autoplay playsinline class="w-full h-full object-cover"></video>
                                            <div class="absolute top-3 left-3 flex flex-wrap gap-1.5">
                                                <span class="bg-slate-950/85 text-white text-[9px] font-black uppercase px-2 py-1 rounded-lg">{{ strtoupper($promo->placement ?? 'HERO') }}</span>
                                                @if(filled($badgeLabel))
                                                    <span class="bg-red-600 text-white text-[9px] font-black uppercase px-2 py-1 rounded-lg shadow-xs">{{ $badgeLabel }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    @elseif($promoImgUrl)
                                        <div class="h-40 bg-slate-100 overflow-hidden relative">
                                            <img src="{{ $promoImgUrl }}" alt="{{ $promo->title }}" class="w-full h-full object-cover">
                                            <div class="absolute top-3 left-3 flex flex-wrap gap-1.5">
                                                <span class="bg-slate-950/85 text-white text-[9px] font-black uppercase px-2 py-1 rounded-lg">{{ strtoupper($promo->placement ?? 'HERO') }}</span>
                                                @if(filled($badgeLabel))
                                                    <span class="bg-red-600 text-white text-[9px] font-black uppercase px-2 py-1 rounded-lg shadow-xs">{{ $badgeLabel }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    @else
                                        <div class="h-28 bg-gradient-to-r from-slate-900 to-slate-800 p-4 flex items-end justify-between">
                                            <div class="flex flex-wrap gap-1.5">
                                                <span class="bg-white/15 text-white text-[9px] font-black uppercase px-2 py-1 rounded-lg">{{ strtoupper($promo->placement ?? 'HERO') }}</span>
                                                @if(filled($badgeLabel))
                                                    <span class="bg-red-600 text-white text-[9px] font-black uppercase px-2 py-1 rounded-lg">{{ $badgeLabel }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    @endif

                                    <div class="p-4">
                                        <div class="flex items-center justify-between gap-2 mb-1">
                                            <h4 class="font-black text-slate-900 text-sm line-clamp-1">{{ $promo->title }}</h4>
                                            @if($promo->is_active)
                                                <span class="bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-black px-2 py-0.5 rounded-md shrink-0">Aktif</span>
                                            @else
                                                <span class="bg-slate-100 text-slate-500 border border-slate-200 text-[10px] font-black px-2 py-0.5 rounded-md shrink-0">Nonaktif</span>
                                            @endif
                                        </div>
                                        @if(filled($promo->subtitle))
                                            <p class="text-[11px] font-bold text-red-600 mb-1 line-clamp-1">{{ $promo->subtitle }}</p>
                                        @endif
                                        <p class="text-xs text-slate-500 line-clamp-2">{{ $promo->description ?: 'Tanpa deskripsi tambahan.' }}</p>
                                        <p class="text-[10px] font-semibold text-slate-400 mt-2">
                                            Tombol: <strong class="text-slate-600">{{ $promo->button_text }}</strong> → <span class="underline">{{ $promo->button_url }}</span>
                                        </p>
                                    </div>
                                </div>

                                <div class="px-4 py-3 bg-slate-50 border-t border-slate-100 flex items-center justify-between gap-2 text-xs">
                                    <button type="button"
                                            @click="openEditPromo(@js($promoPayload))"
                                            class="font-bold text-blue-600 hover:underline">
                                        Edit Promo
                                    </button>

                                    <form id="delete-promo-{{ $promo->id }}" action="{{ \Illuminate\Support\Facades\Route::has('admin.promos.destroy') ? route('admin.promos.destroy', $promo->id) : url('/admin/promos/' . $promo->id) }}" method="POST" class="hidden">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                    <button type="button"
                                            @click="openConfirm({
                                                title: 'Hapus Promo Ini?',
                                                message: 'Postingan promo ' + @js($promo->title) + ' akan dihapus dari beranda toko.',
                                                confirmText: 'Ya, Hapus Promo',
                                                variant: 'danger',
                                                formId: 'delete-promo-{{ $promo->id }}'
                                            })"
                                            class="font-bold text-rose-600 hover:underline">
                                        Hapus
                                    </button>
                                </div>
                            </div>
                        @empty
                            <div class="col-span-full bg-white rounded-2xl border border-dashed border-slate-200 p-10 text-center text-xs text-slate-400">
                                Belum ada postingan promo. Klik <strong>Buat Promo Baru</strong> untuk menampilkan banner promo di beranda.
                            </div>
                        @endforelse
                    </div>
                </div>

                <!-- =========================================================== -->
                <!-- TAB KODE VOUCHER PROMO                                      -->
                <!-- =========================================================== -->
                <div x-show="activeTab === 'voucher'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                        <div>
                            <h3 class="text-base font-black text-slate-900">Kelola Kode Voucher & Kupon Diskon</h3>
                            <p class="text-xs text-slate-500 mt-0.5">Buat kode promo potongan harga yang dapat diklaim pelanggan saat checkout di Sport Mania Distro.</p>
                        </div>
                        <button type="button"
                                @click="voucherFormOpen = !voucherFormOpen"
                                class="bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-700 hover:to-rose-700 text-white px-5 py-2.5 rounded-xl font-bold text-xs uppercase tracking-wider flex items-center gap-2 transition shadow-md shadow-red-600/20">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                            <span x-text="voucherFormOpen ? 'Tutup Form' : 'Buat Voucher Baru'"></span>
                        </button>
                    </div>

                    <div x-show="voucherFormOpen" x-transition class="mb-6 bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6">
                        <div class="flex items-center justify-between pb-4 mb-5 border-b border-slate-100">
                            <h4 class="text-sm font-black uppercase tracking-wider text-slate-900">Buat Kode Voucher Baru</h4>
                            <button type="button" @click="voucherFormOpen = false" class="text-xs font-bold text-slate-400 hover:text-rose-600">✕ Tutup</button>
                        </div>

                        <form action="{{ \Illuminate\Support\Facades\Route::has('admin.vouchers.store') ? route('admin.vouchers.store') : url('/admin/vouchers') }}" method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
                            @csrf
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1.5">Kode Voucher (Maks 30 Karakter) *</label>
                                <input type="text" name="code" maxlength="30" required placeholder="Contoh: SPORTMANIA20" class="w-full uppercase bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-black text-slate-900 focus:ring-2 focus:ring-red-500 outline-none">
                            </div>

                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1.5">Tipe Potongan *</label>
                                <select name="type" x-model="voucherType" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-bold text-slate-800 focus:ring-2 focus:ring-red-500 outline-none">
                                    <option value="percent">Persentase (%)</option>
                                    <option value="fixed">Nominal Tetap (Rp)</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1.5" x-text="voucherType === 'percent' ? 'Besaran Diskon (%) *' : 'Nominal Diskon (Rp) *'"></label>
                                <input type="number" name="value" min="1" required :placeholder="voucherType === 'percent' ? 'Contoh: 15' : 'Contoh: 25000'" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-bold text-slate-900 focus:ring-2 focus:ring-red-500 outline-none tabular-nums">
                            </div>

                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1.5">Minimal Belanja (Rp)</label>
                                <input type="number" name="min_purchase" min="0" value="0" placeholder="0 = Tanpa minimal belanja" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-semibold text-slate-900 focus:ring-2 focus:ring-red-500 outline-none tabular-nums">
                            </div>

                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1.5">Maksimal Potongan (Rp, Opsional)</label>
                                <input type="number" name="max_discount" min="0" placeholder="Khusus diskon % (kosongkan jika bebas)" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-semibold text-slate-900 focus:ring-2 focus:ring-red-500 outline-none tabular-nums">
                            </div>

                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1.5">Kuota Pemakaian *</label>
                                <input type="number" name="quota" min="1" value="100" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-bold text-slate-900 focus:ring-2 focus:ring-red-500 outline-none tabular-nums">
                            </div>

                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1.5">Berlaku Hingga (Opsional)</label>
                                <input type="date" name="expires_at" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-semibold text-slate-900 focus:ring-2 focus:ring-red-500 outline-none">
                            </div>

                            <div class="md:col-span-3 flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                                <button type="button" @click="voucherFormOpen = false" class="px-4 py-2.5 rounded-xl font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 transition">Batal</button>
                                <button type="submit" class="px-5 py-2.5 rounded-xl font-black uppercase tracking-wider text-white bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-700 hover:to-rose-700 shadow-md shadow-red-600/20 transition">Simpan Voucher</button>
                            </div>
                        </form>
                    </div>

                    <div class="bg-white rounded-2xl shadow-xs border border-slate-200/70 overflow-hidden">
                        <div class="overflow-x-auto custom-scrollbar">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-slate-50/80 border-b border-slate-200/70 text-[10px] uppercase tracking-widest text-slate-500 font-bold">
                                        <th class="px-6 py-4">Kode Voucher</th>
                                        <th class="px-6 py-4">Potongan Diskon</th>
                                        <th class="px-6 py-4">Syarat Minimal</th>
                                        <th class="px-6 py-4 text-center">Kuota Terpakai</th>
                                        <th class="px-6 py-4">Masa Berlaku</th>
                                        <th class="px-6 py-4 text-center">Status</th>
                                        <th class="px-6 py-4 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse($vouchers as $voucher)
                                        @php
                                            $isExpired = $voucher->expires_at && \Illuminate\Support\Carbon::parse($voucher->expires_at)->endOfDay()->isPast();
                                            $isQuotaFull = $voucher->used_count >= $voucher->quota;
                                        @endphp
                                        <tr class="hover:bg-slate-50/80 transition-colors">
                                            <td class="px-6 py-4">
                                                <button type="button"
                                                        @click="copyText(@js($voucher->code), 'Kode voucher ' + @js($voucher->code) + ' disalin')"
                                                        class="inline-flex items-center gap-1.5 font-black text-xs tracking-wider uppercase px-3 py-1.5 rounded-lg bg-slate-900 text-white hover:bg-red-600 transition"
                                                        title="Klik untuk menyalin kode">
                                                    <span>{{ $voucher->code }}</span>
                                                    <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                                                </button>
                                            </td>
                                            <td class="px-6 py-4 font-black text-red-600 text-sm tabular-nums">
                                                @if($voucher->type === 'percent')
                                                    Diskon {{ rtrim(rtrim(number_format($voucher->value, 2, '.', ''), '0'), '.') }}%
                                                    @if(!empty($voucher->max_discount))
                                                        <span class="block text-[10px] font-semibold text-slate-400">Maks. Rp {{ number_format($voucher->max_discount, 0, ',', '.') }}</span>
                                                    @endif
                                                @else
                                                    Rp {{ number_format($voucher->value, 0, ',', '.') }}
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 text-xs font-semibold text-slate-700 tabular-nums">
                                                {{ $voucher->min_purchase > 0 ? 'Min. Rp ' . number_format($voucher->min_purchase, 0, ',', '.') : 'Tanpa minimal' }}
                                            </td>
                                            <td class="px-6 py-4 text-center text-xs font-bold text-slate-800 tabular-nums">
                                                {{ $voucher->used_count }} / {{ $voucher->quota }}
                                            </td>
                                            <td class="px-6 py-4 text-xs text-slate-500 tabular-nums">
                                                {{ $voucher->expires_at ? \Illuminate\Support\Carbon::parse($voucher->expires_at)->format('d M Y') : 'Selamanya' }}
                                            </td>
                                            <td class="px-6 py-4 text-center">
                                                @if($isExpired)
                                                    <span class="bg-slate-100 text-slate-500 text-[10px] font-black px-2.5 py-1 rounded-md uppercase border border-slate-200">Kedaluwarsa</span>
                                                @elseif($isQuotaFull)
                                                    <span class="bg-rose-50 text-rose-700 text-[10px] font-black px-2.5 py-1 rounded-md uppercase border border-rose-200">Kuota Habis</span>
                                                @elseif($voucher->is_active)
                                                    <span class="bg-emerald-50 text-emerald-700 text-[10px] font-black px-2.5 py-1 rounded-md uppercase border border-emerald-200/70">Aktif</span>
                                                @else
                                                    <span class="bg-amber-50 text-amber-700 text-[10px] font-black px-2.5 py-1 rounded-md uppercase border border-amber-200/70">Nonaktif</span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 text-right">
                                                <div class="inline-flex items-center gap-1.5">
                                                    @if(\Illuminate\Support\Facades\Route::has('admin.vouchers.toggle'))
                                                        <form action="{{ route('admin.vouchers.toggle', $voucher->id) }}" method="POST">
                                                            @csrf
                                                            @method('PATCH')
                                                            <button type="submit" class="text-[11px] font-bold px-2.5 py-1.5 rounded-lg border transition {{ $voucher->is_active ? 'bg-white text-slate-600 border-slate-200 hover:bg-slate-100' : 'bg-emerald-600 text-white border-emerald-600 hover:bg-emerald-700' }}">
                                                                {{ $voucher->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                                            </button>
                                                        </form>
                                                    @endif

                                                    <form id="delete-voucher-{{ $voucher->id }}" action="{{ \Illuminate\Support\Facades\Route::has('admin.vouchers.destroy') ? route('admin.vouchers.destroy', $voucher->id) : url('/admin/vouchers/' . $voucher->id) }}" method="POST" class="hidden">
                                                        @csrf
                                                        @method('DELETE')
                                                    </form>
                                                    <button type="button"
                                                            @click="openConfirm({
                                                                title: 'Hapus Kode Voucher?',
                                                                message: 'Kode voucher ' + @js($voucher->code) + ' akan dihapus permanen dan tidak bisa lagi dipakai pelanggan.',
                                                                confirmText: 'Ya, Hapus Voucher',
                                                                variant: 'danger',
                                                                formId: 'delete-voucher-{{ $voucher->id }}'
                                                            })"
                                                            class="p-2 text-rose-600 hover:bg-rose-50 rounded-xl transition-colors"
                                                            title="Hapus Voucher">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="px-6 py-10 text-center text-slate-400 text-xs font-medium">
                                                Belum ada kode voucher. Klik <strong>Buat Voucher Baru</strong> untuk menambahkan kupon diskon.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- =========================================================== -->
                <!-- TAB 3: KELOLA PESANAN                                       -->
                <!-- =========================================================== -->
                <div x-show="activeTab === 'pesanan'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">

                    @if($waitingVerificationCount > 0)
                        <div class="mb-5 bg-amber-50 border border-amber-200 rounded-2xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <span class="w-8 h-8 rounded-xl bg-amber-600 text-white flex items-center justify-center text-sm shrink-0">💳</span>
                                <p class="text-xs font-bold text-amber-950">
                                    Ada <span class="underline">{{ $waitingVerificationCount }} pesanan</span> yang sudah mengunggah bukti pembayaran dan menunggu verifikasi Anda.
                                </p>
                            </div>
                            <button type="button"
                                    @click="orderFilter = (orderFilter === 'verifikasi' ? 'semua' : 'verifikasi')"
                                    class="text-[11px] font-black uppercase tracking-wider px-3.5 py-2 rounded-xl transition shrink-0"
                                    :class="orderFilter === 'verifikasi' ? 'bg-slate-900 text-white' : 'bg-amber-600 hover:bg-amber-700 text-white'">
                                <span x-text="orderFilter === 'verifikasi' ? 'Tampilkan Semua Pesanan' : 'Filter: Perlu Verifikasi (' + {{ $waitingVerificationCount }} + ')'"></span>
                            </button>
                        </div>
                    @endif

                    <div class="flex flex-col gap-3 mb-6">
                        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
                            <div>
                                <h3 class="text-base font-black text-slate-900">Daftar Pesanan Masuk</h3>
                                <p class="text-xs text-slate-500 mt-0.5">Verifikasi bukti transfer/QRIS, input nomor resi, ubah status, dan cetak struk/invoice.</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <button type="button"
                                        @click="exportOrdersCsv(false)"
                                        class="inline-flex items-center gap-1.5 bg-white hover:bg-slate-900 text-slate-700 hover:text-white border border-slate-200/80 px-3.5 py-2 rounded-xl text-xs font-bold transition shadow-xs">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                    Export Filter CSV
                                </button>
                                <div class="flex flex-wrap gap-1 bg-white p-1 rounded-xl border border-slate-200/80 shadow-xs">
                                    <button @click="orderFilter = 'semua'" :class="orderFilter === 'semua' ? 'bg-slate-900 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'" class="px-2.5 py-1.5 rounded-lg text-[11px] font-bold uppercase transition">Semua</button>
                                    <button @click="orderFilter = 'verifikasi'" :class="orderFilter === 'verifikasi' ? 'bg-amber-600 text-white shadow-xs' : 'text-amber-800 hover:bg-amber-50'" class="px-2.5 py-1.5 rounded-lg text-[11px] font-black uppercase transition flex items-center gap-1">
                                        <span>Verifikasi</span>
                                        @if($waitingVerificationCount > 0)
                                            <span class="px-1.5 py-0.5 rounded-full text-[9px]" :class="orderFilter === 'verifikasi' ? 'bg-white text-amber-800' : 'bg-amber-600 text-white'">{{ $waitingVerificationCount }}</span>
                                        @endif
                                    </button>
                                    <button @click="orderFilter = 'pending'" :class="orderFilter === 'pending' ? 'bg-slate-900 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'" class="px-2.5 py-1.5 rounded-lg text-[11px] font-bold uppercase transition">Pending</button>
                                    <button @click="orderFilter = 'diproses'" :class="orderFilter === 'diproses' ? 'bg-slate-900 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'" class="px-2.5 py-1.5 rounded-lg text-[11px] font-bold uppercase transition">Diproses</button>
                                    <button @click="orderFilter = 'dikirim'" :class="orderFilter === 'dikirim' ? 'bg-slate-900 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'" class="px-2.5 py-1.5 rounded-lg text-[11px] font-bold uppercase transition">Dikirim</button>
                                    <button @click="orderFilter = 'selesai'" :class="orderFilter === 'selesai' ? 'bg-slate-900 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'" class="px-2.5 py-1.5 rounded-lg text-[11px] font-bold uppercase transition">Selesai</button>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <div class="relative w-full sm:w-64">
                                <input type="text" x-model="searchOrder" placeholder="Cari #ORD, pelanggan, resi, tanggal..." class="w-full bg-white text-xs text-slate-900 rounded-xl pl-9 pr-3 py-2.5 border border-slate-200/80 focus:outline-none focus:ring-2 focus:ring-red-500 font-medium shadow-xs">
                                <svg class="w-3.5 h-3.5 absolute left-3 top-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                            </div>

                            <select x-model="orderTimeFilter"
                                    class="bg-white text-xs font-bold text-slate-700 rounded-xl px-3 py-2.5 border border-slate-200/80 focus:outline-none focus:ring-2 focus:ring-red-500 shadow-xs">
                                <option value="semua">Semua Waktu</option>
                                <option value="today">Hari Ini</option>
                                <option value="yesterday">Kemarin</option>
                                <option value="7days">7 Hari Terakhir</option>
                                <option value="this_month">Bulan Ini</option>
                            </select>

                            <div class="flex items-center gap-1.5 bg-white border border-slate-200/80 rounded-xl px-2.5 py-1.5 text-xs shadow-xs">
                                <span class="text-[10px] font-bold uppercase text-slate-400">Dari</span>
                                <input type="date" x-model="orderDateFrom" class="text-xs font-semibold text-slate-700 focus:outline-none">
                                <span class="text-[10px] font-bold uppercase text-slate-400">s/d</span>
                                <input type="date" x-model="orderDateUntil" class="text-xs font-semibold text-slate-700 focus:outline-none">
                            </div>

                            <button type="button"
                                    x-show="searchOrder !== '' || orderTimeFilter !== 'semua' || orderDateFrom !== '' || orderDateUntil !== '' || orderFilter !== 'semua'"
                                    x-cloak
                                    @click="searchOrder = ''; orderTimeFilter = 'semua'; orderDateFrom = ''; orderDateUntil = ''; orderFilter = 'semua'"
                                    class="text-xs font-bold text-slate-500 hover:text-red-600 px-2.5 py-2">
                                Reset
                            </button>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl shadow-xs border border-slate-200/70 overflow-hidden">
                        <div class="w-full overflow-x-auto custom-scrollbar">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-slate-50/80 border-b border-slate-200/70 text-[10px] uppercase tracking-widest text-slate-500 font-bold">
                                        <th class="px-6 py-4">Pesanan & Pembayaran</th>
                                        <th class="px-6 py-4">Pelanggan</th>
                                        <th class="px-6 py-4">Tanggal & Waktu</th>
                                        <th class="px-6 py-4">Total Tagihan</th>
                                        <th class="px-6 py-4 text-center">Verifikasi, Cetak & Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse($orders as $order)
                                        @php
                                            $statusRaw = strtolower($order->status ?? 'pending');
                                            $statusNormalized = $statusRaw === 'batal' ? 'dibatalkan' : $statusRaw;
                                            $payStatus = $resolvePaymentStatus($order);
                                            $proofRaw = $order->payment_proof ?? null;
                                            $proofUrl = filled($proofRaw)
                                                ? (filter_var($proofRaw, FILTER_VALIDATE_URL) ? $proofRaw : asset('storage/' . ltrim($proofRaw, '/')))
                                                : null;
                                            $payMethodUpper = strtoupper($order->payment_method ?? 'TRANSFER');

                                            $orderCode = '#' . (filled($order->order_number ?? null) ? $order->order_number : (filled($order->invoice_number ?? null) ? $order->invoice_number : ('ORD-' . str_pad($order->id, 4, '0', STR_PAD_LEFT))));
                                            $customerName = filled($order->recipient_name ?? null) ? $order->recipient_name
                                                : (filled($order->customer_name ?? null) ? $order->customer_name
                                                : (filled($order->name ?? null) ? $order->name
                                                : (optional($order->user)->name ?? $usersById[$order->user_id ?? 0] ?? 'Pelanggan Setia')));
                                            $totalOrderPrice = $order->total_price ?? $order->total_amount ?? $order->total ?? 0;
                                            $resiNumber = $order->tracking_number ?? $order->no_resi ?? $order->resi ?? '';
                                            $shippingTag = match(strtolower($order->shipping_method ?? '')) {
                                                'pickup'  => 'AMBIL DI STORE',
                                                'pos'     => 'KASIR TOKO (POS)',
                                                'jne'     => 'JNE REG',
                                                'sicepat' => 'SICEPAT',
                                                default   => filled($order->shipping_method ?? null) ? strtoupper($order->shipping_method) : '',
                                            };
                                            $firstItem = $order->items ? $order->items->first() : null;
                                            $firstItemName = $firstItem
                                                ? (filled($firstItem->product_name ?? null) ? $firstItem->product_name : (optional($firstItem->product)->name ?? 'Produk Terhapus'))
                                                : null;
                                            $orderDateFormatted = optional($order->created_at)->format('d M Y, H:i') ?? '-';
                                            $orderDateDisplay = optional($order->created_at)->format('d F Y, H:i') ?? '-';
                                            $searchHaystack = strtolower($orderCode . ' ' . $customerName . ' ' . $resiNumber . ' ' . $payMethodUpper . ' ' . $orderDateDisplay . ' ' . ($firstItemName ?? ''));
                                        @endphp
                                        <tr x-show="(orderFilter === 'semua' || (orderFilter === 'verifikasi' && @js($payStatus) === 'waiting_verification' && @js($statusNormalized) !== 'dibatalkan') || orderFilter === @js($statusNormalized)) && (@js($searchHaystack).includes(searchOrder.toLowerCase().trim())) && matchTimeFilter(@js($orderDateFormatted), orderTimeFilter, orderDateFrom, orderDateUntil)"
                                            class="hover:bg-slate-50/80 transition-colors {{ $payStatus === 'waiting_verification' && $statusNormalized !== 'dibatalkan' ? 'bg-amber-50/40' : '' }}">

                                            <td class="px-6 py-4">
                                                <div class="flex items-center gap-3.5">
                                                    @if($firstItem && optional($firstItem->product)->image)
                                                        <img src="{{ filter_var($firstItem->product->image, FILTER_VALIDATE_URL) ? $firstItem->product->image : asset('storage/' . ltrim($firstItem->product->image, '/')) }}" alt="Foto Produk" class="w-12 h-12 rounded-xl object-cover border border-slate-200/80 shrink-0 shadow-xs">
                                                    @else
                                                        <div class="w-12 h-12 rounded-xl bg-slate-100 border border-slate-200/80 flex items-center justify-center text-base shrink-0">
                                                            👕
                                                        </div>
                                                    @endif
                                                    <div>
                                                        <div class="flex flex-wrap items-center gap-1.5">
                                                            <span class="font-black text-slate-900 text-xs tracking-tight">{{ $orderCode }}</span>

                                                            @if($payStatus === 'waiting_verification' && $statusNormalized !== 'dibatalkan')
                                                                <button type="button"
                                                                        @click="openOrderModal({{ $order->id }})"
                                                                        class="text-[9px] font-black uppercase px-2 py-0.5 rounded bg-amber-600 text-white shadow-xs animate-pulse hover:bg-amber-700 transition">
                                                                    ⏳ Perlu Verifikasi
                                                                </button>
                                                            @elseif($payStatus === 'paid')
                                                                <span class="text-[9px] font-black uppercase px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                                    ✓ Lunas ({{ $payMethodUpper }})
                                                                </span>
                                                            @elseif($payStatus === 'rejected')
                                                                <span class="text-[9px] font-black uppercase px-2 py-0.5 rounded bg-rose-50 text-rose-700 border border-rose-200">
                                                                    ✕ Bukti Ditolak
                                                                </span>
                                                            @else
                                                                <span class="text-[9px] font-bold uppercase px-2 py-0.5 rounded bg-amber-50 text-amber-700 border border-amber-200/80">
                                                                    {{ $payMethodUpper === 'COD' ? 'COD' : 'Belum Bayar (' . $payMethodUpper . ')' }}
                                                                </span>
                                                            @endif

                                                            @if($shippingTag)
                                                                <span class="text-[9px] font-bold uppercase px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 border border-slate-200/80">
                                                                    {{ $shippingTag }}
                                                                </span>
                                                            @endif
                                                            @if(filled($resiNumber))
                                                                <span class="text-[10px] font-bold bg-purple-50 text-purple-700 border border-purple-200/80 px-2 py-0.5 rounded-md">
                                                                    Resi: {{ $resiNumber }}
                                                                </span>
                                                            @endif
                                                        </div>
                                                        <p class="text-xs font-semibold text-slate-600 mt-1">
                                                            @if($firstItemName)
                                                                {{ $firstItemName }}
                                                                @if($order->items->count() > 1)
                                                                    <span class="text-[10px] bg-red-50 text-red-600 px-1.5 py-0.5 rounded-md font-extrabold ml-1">+{{ $order->items->count() - 1 }} produk</span>
                                                                @endif
                                                            @else
                                                                <span class="text-slate-400 italic">Item tidak tersedia</span>
                                                            @endif
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>

                                            <td class="px-6 py-4 text-sm font-bold text-slate-800">{{ $customerName }}</td>
                                            <td class="px-6 py-4 text-xs tabular-nums">
                                                <span class="inline-block text-[10px] font-black uppercase px-2 py-0.5 rounded-md border mb-1"
                                                      :class="{
                                                          'bg-emerald-50 text-emerald-700 border-emerald-200': getTimeLabelText(@js($orderDateFormatted)) === 'Hari Ini',
                                                          'bg-amber-50 text-amber-700 border-amber-200': getTimeLabelText(@js($orderDateFormatted)) === 'Kemarin',
                                                          'bg-slate-100 text-slate-600 border-slate-200': !['Hari Ini', 'Kemarin'].includes(getTimeLabelText(@js($orderDateFormatted)))
                                                      }"
                                                      x-text="getTimeLabelText(@js($orderDateFormatted))">
                                                </span>
                                                <span class="block text-slate-500">{{ $orderDateDisplay }}</span>
                                            </td>
                                            <td class="px-6 py-4 font-black text-red-600 text-sm tabular-nums">Rp {{ number_format($totalOrderPrice, 0, ',', '.') }}</td>

                                            <td class="px-6 py-4 text-center">
                                                <div class="inline-flex items-center gap-1.5">
                                                    @if(filled($proofUrl))
                                                        <button type="button"
                                                                @click="openProofLightbox(@js($proofUrl), @js(ltrim($orderCode, '#')), {{ $order->id }})"
                                                                class="p-2 rounded-xl {{ $payStatus === 'waiting_verification' ? 'bg-amber-600 text-white hover:bg-amber-700 shadow-xs' : 'bg-amber-50 text-amber-800 hover:bg-amber-600 hover:text-white' }} transition"
                                                                title="Lihat Foto Bukti Pembayaran">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                                        </button>
                                                    @endif

                                                    <button type="button"
                                                            @click="openOrderModal({{ $order->id }})"
                                                            class="p-2 rounded-xl bg-slate-100 hover:bg-slate-900 text-slate-700 hover:text-white transition"
                                                            title="Detail Pesanan, Verifikasi Bayar & Input Resi">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                                    </button>

                                                    <button type="button"
                                                            @click="printOrderById({{ $order->id }}, 'invoice')"
                                                            class="p-2 rounded-xl bg-red-50 hover:bg-red-600 text-red-600 hover:text-white transition"
                                                            title="Cetak Invoice / Struk Pembayaran">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                                                    </button>

                                                    <form id="quick-status-form-{{ $order->id }}" action="{{ route('admin.orders.update', $order->id) }}" method="POST" class="inline-block">
                                                        @csrf
                                                        @method('PUT')
                                                        <select name="status"
                                                                @change="handleQuickStatusChange($event.target, {{ $order->id }}, @js($statusNormalized))"
                                                                class="text-[11px] font-black uppercase tracking-wider px-3 py-2 rounded-xl border shadow-xs transition cursor-pointer outline-none focus:ring-2 focus:ring-red-500
                                                                {{ $statusNormalized === 'pending' ? 'bg-amber-50 text-amber-700 border-amber-200/80' : '' }}
                                                                {{ $statusNormalized === 'diproses' ? 'bg-blue-50 text-blue-700 border-blue-200/80' : '' }}
                                                                {{ $statusNormalized === 'dikirim' ? 'bg-purple-50 text-purple-700 border-purple-200/80' : '' }}
                                                                {{ $statusNormalized === 'selesai' ? 'bg-emerald-50 text-emerald-700 border-emerald-200/80' : '' }}
                                                                {{ $statusNormalized === 'dibatalkan' ? 'bg-rose-50 text-rose-700 border-rose-200/80' : '' }}">
                                                            <option value="pending" {{ $statusNormalized === 'pending' ? 'selected' : '' }}>Pending</option>
                                                            <option value="diproses" {{ $statusNormalized === 'diproses' ? 'selected' : '' }}>Diproses</option>
                                                            <option value="dikirim" {{ $statusNormalized === 'dikirim' ? 'selected' : '' }}>Dikirim</option>
                                                            <option value="selesai" {{ $statusNormalized === 'selesai' ? 'selected' : '' }}>Selesai</option>
                                                            <option value="dibatalkan" {{ $statusNormalized === 'dibatalkan' ? 'selected' : '' }}>Batal</option>
                                                        </select>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-6 py-12 text-center text-slate-400 text-xs font-medium">
                                                Belum ada pesanan masuk di sistem.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="px-6 py-3.5 bg-slate-50/90 border-t border-slate-200/70 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs tabular-nums">
                            <span class="text-slate-600 font-semibold">
                                Menampilkan <strong class="text-slate-900" x-text="getFilteredOrdersList().length"></strong> dari <strong class="text-slate-900">{{ $orders->count() }}</strong> pesanan
                            </span>
                            <span class="text-slate-600 font-semibold">
                                Total Nominal Filter: <strong class="text-red-600 font-black" x-text="'Rp ' + sumOrdersNominal(getFilteredOrdersList()).toLocaleString('id-ID')"></strong>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- =========================================================== -->
                <!-- TAB 4: DATA PELANGGAN                                       -->
                <!-- =========================================================== -->
                <div x-show="activeTab === 'pelanggan'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                        <div>
                            <h3 class="text-base font-black text-slate-900">Data Pelanggan Terdaftar</h3>
                            <p class="text-xs text-slate-500 mt-0.5">Daftar akun pembeli aktif beserta riwayat belanja di Sport Mania Distro.</p>
                        </div>
                        <div class="relative w-full sm:w-72">
                            <input type="text" x-model="searchCustomer" placeholder="Cari nama atau email pelanggan..." class="w-full bg-white text-xs text-slate-900 rounded-xl pl-10 pr-4 py-2.5 border border-slate-200/80 focus:outline-none focus:ring-2 focus:ring-red-500 transition shadow-xs font-medium">
                            <svg class="w-4 h-4 absolute left-3.5 top-2.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl shadow-xs border border-slate-200/70 overflow-hidden">
                        <div class="overflow-x-auto custom-scrollbar">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-slate-50/80 border-b border-slate-200/70 text-[10px] uppercase tracking-widest text-slate-500 font-bold">
                                        <th class="px-6 py-4">Pelanggan</th>
                                        <th class="px-6 py-4">Email</th>
                                        <th class="px-6 py-4 text-center">Total Transaksi</th>
                                        <th class="px-6 py-4">Tanggal Bergabung</th>
                                        <th class="px-6 py-4 text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse($customers as $cust)
                                        @php
                                            $custSearch = strtolower($cust->name . ' ' . $cust->email);
                                            $custOrderCount = $orders->where('user_id', $cust->id)->count();
                                        @endphp
                                        <tr x-show="@js($custSearch).includes(searchCustomer.toLowerCase().trim())" class="hover:bg-slate-50/80 transition-colors">
                                            <td class="px-6 py-4 flex items-center gap-3">
                                                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-red-50 to-rose-100 text-red-600 border border-red-200/60 flex items-center justify-center font-black text-xs shrink-0">
                                                    {{ strtoupper(substr($cust->name, 0, 1)) }}
                                                </div>
                                                <span class="font-bold text-sm text-slate-900">{{ $cust->name }}</span>
                                            </td>
                                            <td class="px-6 py-4 text-xs font-medium text-slate-600">{{ $cust->email }}</td>
                                            <td class="px-6 py-4 text-center tabular-nums">
                                                <span class="bg-slate-100 text-slate-800 text-xs font-bold px-2.5 py-1 rounded-lg">
                                                    {{ $custOrderCount }}x pesanan
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-xs text-slate-500 tabular-nums">{{ optional($cust->created_at)->format('d M Y') ?? '-' }}</td>
                                            <td class="px-6 py-4 text-center">
                                                <span class="bg-emerald-50 text-emerald-700 text-[10px] font-black px-2.5 py-1 rounded-md uppercase border border-emerald-200/70">Aktif</span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-6 py-8 text-center text-slate-500 text-sm">Belum ada pelanggan terdaftar.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- =========================================================== -->
                <!-- TAB 5: RIWAYAT & LOG                                        -->
                <!-- =========================================================== -->
                <div x-show="activeTab === 'riwayat'" x-cloak x-data="{ subTab: 'transaksi' }" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">

                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                        <div>
                            <h3 class="text-base font-black text-slate-900">Arsip Riwayat & Log Toko</h3>
                            <p class="text-xs text-slate-500 mt-0.5">Pantau arsip transaksi selesai/dibatalkan dan cetak ulang invoice kapan saja.</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2.5">
                            <button type="button"
                                    x-show="subTab === 'transaksi'"
                                    @click="exportOrdersCsv(true)"
                                    class="inline-flex items-center gap-1.5 bg-white hover:bg-slate-900 text-slate-700 hover:text-white border border-slate-200/80 px-3.5 py-2 rounded-xl text-xs font-bold transition shadow-xs">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                Export CSV
                            </button>
                            <div class="flex bg-white p-1 rounded-xl border border-slate-200/80 shadow-xs">
                                <button @click="subTab = 'transaksi'"
                                        :class="subTab === 'transaksi' ? 'bg-slate-900 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'"
                                        class="px-4 py-2 rounded-lg text-xs font-bold uppercase transition">
                                    Riwayat Transaksi ({{ $finalizedOrders->count() }})
                                </button>
                                <button @click="subTab = 'log'"
                                        :class="subTab === 'log' ? 'bg-slate-900 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'"
                                        class="px-4 py-2 rounded-lg text-xs font-bold uppercase transition">
                                    Log Aktivitas Produk
                                </button>
                            </div>
                        </div>
                    </div>

                    <div x-show="subTab === 'transaksi'" class="flex flex-wrap items-center justify-between gap-3 mb-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <div class="relative w-full sm:w-64">
                                <input type="text"
                                       x-model="searchHistory"
                                       placeholder="Cari kode, pelanggan, atau tanggal..."
                                       class="w-full bg-white text-xs text-slate-900 rounded-xl pl-9 pr-4 py-2.5 border border-slate-200/80 focus:outline-none focus:ring-2 focus:ring-red-500 font-medium shadow-xs">
                                <svg class="w-3.5 h-3.5 absolute left-3 top-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                            </div>

                            <select x-model="historyTimeFilter"
                                    class="bg-white text-xs font-bold text-slate-700 rounded-xl px-3 py-2.5 border border-slate-200/80 focus:outline-none focus:ring-2 focus:ring-red-500 shadow-xs">
                                <option value="semua">Semua Waktu</option>
                                <option value="today">Hari Ini</option>
                                <option value="yesterday">Kemarin</option>
                                <option value="7days">7 Hari Terakhir</option>
                                <option value="this_month">Bulan Ini</option>
                            </select>

                            <div class="flex items-center gap-1.5 bg-white border border-slate-200/80 rounded-xl px-2.5 py-1.5 text-xs shadow-xs">
                                <span class="text-[10px] font-bold uppercase text-slate-400">Dari</span>
                                <input type="date" x-model="historyDateFrom" class="text-xs font-semibold text-slate-700 focus:outline-none">
                                <span class="text-[10px] font-bold uppercase text-slate-400">s/d</span>
                                <input type="date" x-model="historyDateUntil" class="text-xs font-semibold text-slate-700 focus:outline-none">
                            </div>

                            <button type="button"
                                    x-show="searchHistory !== '' || historyTimeFilter !== 'semua' || historyDateFrom !== '' || historyDateUntil !== '' || historyFilter !== 'semua'"
                                    @click="searchHistory = ''; historyTimeFilter = 'semua'; historyDateFrom = ''; historyDateUntil = ''; historyFilter = 'semua'"
                                    class="text-xs font-bold text-slate-500 hover:text-red-600 px-2.5 py-2">
                                Reset
                            </button>
                        </div>

                        <div class="flex gap-1 bg-white p-1 rounded-xl border border-slate-200/80 shadow-xs self-start">
                            <button type="button" @click="historyFilter = 'semua'" :class="historyFilter === 'semua' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100'" class="px-3 py-1.5 rounded-lg text-[11px] font-bold uppercase transition">Semua</button>
                            <button type="button" @click="historyFilter = 'selesai'" :class="historyFilter === 'selesai' ? 'bg-emerald-600 text-white' : 'text-slate-600 hover:bg-slate-100'" class="px-3 py-1.5 rounded-lg text-[11px] font-bold uppercase transition">Selesai</button>
                            <button type="button" @click="historyFilter = 'dibatalkan'" :class="historyFilter === 'dibatalkan' ? 'bg-rose-600 text-white' : 'text-slate-600 hover:bg-slate-100'" class="px-3 py-1.5 rounded-lg text-[11px] font-bold uppercase transition">Dibatalkan</button>
                        </div>
                    </div>

                    <div x-show="subTab === 'transaksi'" class="bg-white rounded-2xl shadow-xs border border-slate-200/70 overflow-hidden">
                        <div class="overflow-x-auto custom-scrollbar">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-slate-50/80 border-b border-slate-200/70 text-[10px] uppercase tracking-widest text-slate-500 font-bold">
                                        <th class="px-6 py-4">ID Transaksi</th>
                                        <th class="px-6 py-4">Pelanggan</th>
                                        <th class="px-6 py-4">Tanggal & Waktu</th>
                                        <th class="px-6 py-4">Total (Rp)</th>
                                        <th class="px-6 py-4 text-center">Status & Cetak</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse($finalizedOrders as $order)
                                        @php
                                            $finalStatusRaw = strtolower($order->status ?? 'selesai');
                                            $finalStatus = $finalStatusRaw === 'batal' ? 'dibatalkan' : $finalStatusRaw;
                                            $trxCode = '#' . (filled($order->order_number ?? null) ? $order->order_number : (filled($order->invoice_number ?? null) ? $order->invoice_number : ('TRX-' . str_pad($order->id, 4, '0', STR_PAD_LEFT))));
                                            $trxCustomer = filled($order->recipient_name ?? null) ? $order->recipient_name
                                                : (filled($order->customer_name ?? null) ? $order->customer_name
                                                : (filled($order->name ?? null) ? $order->name
                                                : (optional($order->user)->name ?? $usersById[$order->user_id ?? 0] ?? 'Pelanggan')));
                                            $trxDateFormatted = optional($order->updated_at ?? $order->created_at)->format('d M Y, H:i') ?? '-';
                                            $trxDateDisplay = optional($order->updated_at ?? $order->created_at)->format('d F Y, H:i') ?? '-';
                                            $trxSearch = strtolower($trxCode . ' ' . $trxCustomer . ' ' . $trxDateDisplay);
                                        @endphp
                                        <tr x-show="(historyFilter === 'semua' || historyFilter === @js($finalStatus)) && (@js($trxSearch).includes(searchHistory.toLowerCase().trim())) && matchTimeFilter(@js($trxDateFormatted), historyTimeFilter, historyDateFrom, historyDateUntil)"
                                            class="hover:bg-slate-50/80 transition-colors">
                                            <td class="px-6 py-4 font-bold text-slate-900 text-xs">{{ $trxCode }}</td>
                                            <td class="px-6 py-4 text-sm font-semibold text-slate-800">{{ $trxCustomer }}</td>
                                            <td class="px-6 py-4 text-xs tabular-nums">
                                                <span class="inline-block text-[10px] font-black uppercase px-2 py-0.5 rounded-md border mb-1"
                                                      :class="{
                                                          'bg-emerald-50 text-emerald-700 border-emerald-200': getTimeLabelText(@js($trxDateFormatted)) === 'Hari Ini',
                                                          'bg-amber-50 text-amber-700 border-amber-200': getTimeLabelText(@js($trxDateFormatted)) === 'Kemarin',
                                                          'bg-slate-100 text-slate-600 border-slate-200': !['Hari Ini', 'Kemarin'].includes(getTimeLabelText(@js($trxDateFormatted)))
                                                      }"
                                                      x-text="getTimeLabelText(@js($trxDateFormatted))">
                                                </span>
                                                <span class="block text-slate-500">{{ $trxDateDisplay }}</span>
                                            </td>
                                            <td class="px-6 py-4 font-bold text-slate-900 tabular-nums">Rp {{ number_format($order->total_price ?? $order->total_amount ?? $order->total ?? 0, 0, ',', '.') }}</td>
                                            <td class="px-6 py-4 text-center">
                                                <div class="inline-flex items-center gap-2">
                                                    @if($finalStatus === 'selesai')
                                                        <span class="bg-emerald-50 text-emerald-700 text-[10px] font-black px-2.5 py-1 rounded-md uppercase border border-emerald-200/70">Selesai</span>
                                                    @else
                                                        <span class="bg-rose-50 text-rose-700 text-[10px] font-black px-2.5 py-1 rounded-md uppercase border border-rose-200/70">Dibatalkan</span>
                                                    @endif
                                                    <button type="button"
                                                            @click="openOrderModal({{ $order->id }})"
                                                            class="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-800 text-slate-700 hover:text-white transition"
                                                            title="Lihat Detail Transaksi">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                                    </button>
                                                    <button type="button"
                                                            @click="printOrderById({{ $order->id }}, 'invoice')"
                                                            class="p-1.5 rounded-lg bg-slate-100 hover:bg-red-600 text-slate-700 hover:text-white transition"
                                                            title="Cetak Ulang Invoice">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-6 py-10 text-center text-slate-400 text-xs font-medium">
                                                Belum ada riwayat transaksi yang selesai atau dibatalkan.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="px-6 py-3.5 bg-slate-50/90 border-t border-slate-200/70 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs tabular-nums">
                            <span class="text-slate-600 font-semibold">
                                Menampilkan <strong class="text-slate-900" x-text="getFilteredHistoryList().length"></strong> dari <strong class="text-slate-900">{{ $finalizedOrders->count() }}</strong> riwayat transaksi
                            </span>
                            <span class="text-slate-600 font-semibold">
                                Total Nominal Filter: <strong class="text-slate-900 font-black" x-text="'Rp ' + sumOrdersNominal(getFilteredHistoryList()).toLocaleString('id-ID')"></strong>
                            </span>
                        </div>
                    </div>

                    <div x-show="subTab === 'log'" x-cloak class="bg-white rounded-2xl shadow-xs border border-slate-200/70 overflow-hidden">
                        <div class="overflow-x-auto custom-scrollbar">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-slate-50/80 border-b border-slate-200/70 text-[10px] uppercase tracking-widest text-slate-500 font-bold">
                                        <th class="px-6 py-4">Waktu</th>
                                        <th class="px-6 py-4">Admin</th>
                                        <th class="px-6 py-4">Aktivitas / Aksi Sistem</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse($products->sortByDesc('updated_at')->take(10) as $prodLog)
                                        @php
                                            $isNewlyCreated = $prodLog->created_at && $prodLog->updated_at && $prodLog->created_at->eq($prodLog->updated_at);
                                        @endphp
                                        <tr class="hover:bg-slate-50/80 transition-colors">
                                            <td class="px-6 py-4 text-xs text-slate-500 font-medium">{{ optional($prodLog->updated_at)->diffForHumans() ?? 'Baru saja' }}</td>
                                            <td class="px-6 py-4 font-bold text-slate-900 text-sm">{{ Auth::user()->name ?? 'Admin Sport Mania' }}</td>
                                            <td class="px-6 py-4 text-xs text-slate-700">
                                                {{ $isNewlyCreated ? 'Menambahkan produk baru' : 'Memperbarui data & stok varian produk' }}
                                                <span class="font-bold text-red-600">{{ $prodLog->name }}</span>
                                                (Total stok: <strong class="tabular-nums">{{ ($prodLog->variants && $prodLog->variants->count() > 0) ? $prodLog->variants->sum('stock') : ($prodLog->stock ?? 0) }} pcs</strong>).
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="px-6 py-8 text-center text-slate-400 text-xs">Belum ada log aktivitas produk.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- =========================================================== -->
                <!-- TAB 6: REKAP & GRAFIK BULANAN (MODAL AWAL, HPP & LABA KOTOR) -->
                <!-- =========================================================== -->
                <div x-show="activeTab === 'rekap'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">

                    <div class="bg-white rounded-2xl border border-slate-200/80 p-5 mb-6 shadow-xs flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-red-600"></span>
                                <h3 class="text-base font-black text-slate-900" x-text="'Laporan Performa Bulan ' + getMonthName(recapMonth) + ' ' + recapYear"></h3>
                            </div>
                            <p class="text-xs text-slate-500 mt-0.5">Analisis tren omzet harian, modal awal, HPP per produk, laba kotor, perbandingan tahunan, produk terlaris, dan metode pembayaran.</p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2.5">
                            <select x-model="recapMonth" class="bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs font-black text-slate-800 focus:ring-2 focus:ring-red-500 outline-none">
                                <option value="1">Januari</option>
                                <option value="2">Februari</option>
                                <option value="3">Maret</option>
                                <option value="4">April</option>
                                <option value="5">Mei</option>
                                <option value="6">Juni</option>
                                <option value="7">Juli</option>
                                <option value="8">Agustus</option>
                                <option value="9">September</option>
                                <option value="10">Oktober</option>
                                <option value="11">November</option>
                                <option value="12">Desember</option>
                            </select>

                            <select x-model="recapYear" class="bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs font-black text-slate-800 focus:ring-2 focus:ring-red-500 outline-none">
                                <template x-for="yr in getAvailableRecapYears()" :key="yr">
                                    <option :value="yr" x-text="yr" :selected="yr == recapYear"></option>
                                </template>
                            </select>

                            <select x-model="recapBasis" class="bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs font-bold text-slate-700 focus:ring-2 focus:ring-red-500 outline-none">
                                <option value="realized">Basis: Lunas / Selesai</option>
                                <option value="all_valid">Basis: Semua Order Aktif & Selesai</option>
                            </select>

                            <button type="button"
                                    @click="openModalAwalEditor()"
                                    class="inline-flex items-center gap-1.5 bg-amber-50 hover:bg-amber-600 text-amber-800 hover:text-white border border-amber-200 px-3.5 py-2.5 rounded-xl text-xs font-black uppercase tracking-wider transition shadow-xs">
                                <span>💰 Modal Awal</span>
                            </button>

                            <button type="button"
                                    @click="exportMonthlyRecapCsv()"
                                    class="inline-flex items-center gap-1.5 bg-slate-900 hover:bg-red-600 text-white px-4 py-2.5 rounded-xl text-xs font-black uppercase tracking-wider transition shadow-xs">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                Export Rekap CSV
                            </button>
                        </div>
                    </div>

                    <div x-show="modalAwalOpen" x-transition class="mb-6 bg-white rounded-2xl border border-amber-200 shadow-xs p-5">
                        <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-100">
                            <div>
                                <h4 class="text-sm font-black uppercase tracking-wider text-slate-900" x-text="'Atur Modal Awal — ' + getMonthName(recapMonth) + ' ' + recapYear"></h4>
                                <p class="text-[11px] text-slate-500 mt-0.5">Tersimpan otomatis per bulan & tahun untuk memantau posisi modal dan laba kotor.</p>
                            </div>
                            <button type="button" @click="modalAwalOpen = false" class="text-xs font-bold text-slate-400 hover:text-rose-600">✕ Tutup</button>
                        </div>

                        <div class="flex flex-col sm:flex-row items-stretch sm:items-end gap-3 text-xs">
                            <div class="flex-1">
                                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1.5">Nominal Modal Awal (Rp)</label>
                                <input type="number"
                                       min="0"
                                       step="1000"
                                       x-model.number="modalAwalDraft"
                                       placeholder="Contoh: 5000000"
                                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-black text-slate-900 focus:ring-2 focus:ring-red-500 outline-none tabular-nums">
                            </div>
                            <button type="button"
                                    @click="saveCurrentModalAwal()"
                                    class="px-5 py-2.5 rounded-xl font-black uppercase tracking-wider text-white bg-emerald-600 hover:bg-emerald-700 transition shrink-0">
                                Simpan Modal Awal
                            </button>
                        </div>
                    </div>

                    <!-- KPI Baris 1: Omzet, Modal Awal, HPP, Laba Kotor -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-5">
                        <div class="bg-gradient-to-br from-slate-900 via-slate-950 to-slate-900 p-6 rounded-2xl border border-slate-800 shadow-lg relative overflow-hidden">
                            <div class="absolute -right-6 -top-6 w-24 h-24 rounded-full bg-red-600/20 blur-2xl"></div>
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 block">Omzet Bulan Ini</span>
                            <p class="text-2xl font-black text-white tabular-nums mt-2" x-text="'Rp ' + Number(getRecapSummary().revenue).toLocaleString('id-ID')"></p>
                            <div class="mt-2.5 flex items-center gap-1.5 text-[11px] font-bold">
                                <span class="px-2 py-0.5 rounded-md tabular-nums"
                                      :class="getRecapSummary().growthPct >= 0 ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300'"
                                      x-text="(getRecapSummary().growthPct >= 0 ? '▲ +' : '▼ ') + getRecapSummary().growthPct + '%'"></span>
                                <span class="text-slate-400" x-text="'vs ' + getRecapSummary().prevMonthLabel"></span>
                            </div>
                        </div>

                        <div class="bg-white p-6 rounded-2xl border border-slate-200/70 shadow-xs">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 block">Modal Awal Bulan</span>
                                <button type="button" @click="openModalAwalEditor()" class="text-[10px] font-black text-blue-600 hover:underline">Ubah</button>
                            </div>
                            <p class="text-2xl font-black text-slate-900 tabular-nums mt-2" x-text="'Rp ' + Number(getRecapSummary().modalAwal).toLocaleString('id-ID')"></p>
                            <p class="text-xs text-slate-500 mt-2 tabular-nums" x-text="getRecapSummary().modalAwal > 0 ? ('Perputaran Omzet: ' + (getRecapSummary().revenue / getRecapSummary().modalAwal).toFixed(2) + 'x modal') : 'Klik Ubah untuk set modal awal'"></p>
                        </div>

                        <div class="bg-white p-6 rounded-2xl border border-slate-200/70 shadow-xs">
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 block">Total HPP Terjual</span>
                            <p class="text-2xl font-black text-amber-700 tabular-nums mt-2" x-text="'Rp ' + Number(getRecapSummary().totalHpp).toLocaleString('id-ID')"></p>
                            <p class="text-xs text-slate-500 mt-2 tabular-nums" x-text="'Dari ' + getRecapSummary().totalItemsSold + ' pcs barang terjual'"></p>
                        </div>

                        <div class="bg-white p-6 rounded-2xl border border-slate-200/70 shadow-xs">
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 block">Laba Kotor (Gross Profit)</span>
                            <p class="text-2xl font-black tabular-nums mt-2"
                               :class="getRecapSummary().grossProfit >= 0 ? 'text-emerald-600' : 'text-rose-600'"
                               x-text="(getRecapSummary().grossProfit < 0 ? '-Rp ' : 'Rp ') + Number(Math.abs(getRecapSummary().grossProfit)).toLocaleString('id-ID')"></p>
                            <p class="text-xs text-slate-500 mt-2 tabular-nums" x-text="'Margin Kotor: ' + getRecapSummary().grossMarginPct + '%'"></p>
                        </div>
                    </div>

                    <!-- KPI Baris 2: Transaksi, Terjual, AOV, Modal + Laba Kotor -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-6">
                        <div class="bg-white p-5 rounded-2xl border border-slate-200/70 shadow-xs">
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 block">Transaksi Terhitung</span>
                            <p class="text-2xl font-black text-slate-900 tabular-nums mt-1.5">
                                <span x-text="getRecapSummary().validCount"></span>
                                <span class="text-xs font-semibold text-slate-400">order</span>
                            </p>
                            <p class="text-xs text-slate-500 mt-1.5 tabular-nums">
                                Selesai: <strong class="text-emerald-600" x-text="getRecapSummary().completedCount"></strong> • Batal: <strong class="text-rose-600" x-text="getRecapSummary().cancelledCount"></strong>
                            </p>
                        </div>

                        <div class="bg-white p-5 rounded-2xl border border-slate-200/70 shadow-xs">
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 block">Produk Terjual</span>
                            <p class="text-2xl font-black text-slate-900 tabular-nums mt-1.5">
                                <span x-text="getRecapSummary().totalItemsSold"></span>
                                <span class="text-xs font-semibold text-slate-400">pcs</span>
                            </p>
                            <p class="text-xs text-slate-500 mt-1.5">Total kuantitas item keluar</p>
                        </div>

                        <div class="bg-white p-5 rounded-2xl border border-slate-200/70 shadow-xs">
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 block">Rata-Rata Nilai Order</span>
                            <p class="text-xl font-black text-red-600 tabular-nums mt-1.5" x-text="'Rp ' + Number(getRecapSummary().avgOrderValue).toLocaleString('id-ID')"></p>
                            <p class="text-xs text-slate-500 mt-1.5">Average Order Value (AOV)</p>
                        </div>

                        <div class="bg-white p-5 rounded-2xl border border-slate-200/70 shadow-xs">
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 block">Modal Awal + Laba Kotor</span>
                            <p class="text-xl font-black text-slate-900 tabular-nums mt-1.5" x-text="'Rp ' + Number(getRecapSummary().modalPlusGrossProfit).toLocaleString('id-ID')"></p>
                            <p class="text-xs text-slate-500 mt-1.5">Akumulasi modal awal & laba kotor</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-6">
                        <div class="lg:col-span-7 bg-white rounded-2xl border border-slate-200/70 shadow-xs p-6">
                            <div class="mb-4">
                                <h4 class="text-sm font-black uppercase tracking-wider text-slate-900" x-text="'Grafik Omzet & Laba Kotor Harian — ' + getMonthName(recapMonth) + ' ' + recapYear"></h4>
                                <p class="text-xs text-slate-500 mt-0.5">Batang merah = omzet harian, batang hijau = laba kotor harian, garis hitam = frekuensi transaksi.</p>
                            </div>
                            <div class="h-72 relative w-full">
                                <canvas id="recapDailyChart"></canvas>
                            </div>
                        </div>

                        <div class="lg:col-span-5 bg-white rounded-2xl border border-slate-200/70 shadow-xs p-6">
                            <div class="mb-4">
                                <h4 class="text-sm font-black uppercase tracking-wider text-slate-900" x-text="'Perbandingan 12 Bulan (' + recapYear + ')'"></h4>
                                <p class="text-xs text-slate-500 mt-0.5">Bulan terpilih ditandai warna merah sebagai sorotan.</p>
                            </div>
                            <div class="h-72 relative w-full">
                                <canvas id="recapAnnualChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-6">
                        <div class="lg:col-span-7 bg-white rounded-2xl border border-slate-200/70 shadow-xs p-6">
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <h4 class="text-sm font-black uppercase tracking-wider text-slate-900">Top 5 Produk Terlaris Bulan Ini</h4>
                                    <p class="text-xs text-slate-500 mt-0.5">Urutan berdasarkan jumlah pcs terjual terbanyak.</p>
                                </div>
                                <span class="text-[10px] font-black uppercase px-2.5 py-1 rounded-full bg-red-50 text-red-600 border border-red-100">Best Seller</span>
                            </div>

                            <template x-if="getRecapSummary().topProducts.length > 0">
                                <div class="divide-y divide-slate-100">
                                    <template x-for="(prod, idx) in getRecapSummary().topProducts" :key="prod.name">
                                        <div class="py-3 flex items-center justify-between gap-4">
                                            <div class="flex items-center gap-3 min-w-0">
                                                <span class="w-7 h-7 rounded-lg font-black text-xs flex items-center justify-center shrink-0"
                                                      :class="idx === 0 ? 'bg-red-600 text-white' : 'bg-slate-100 text-slate-700'"
                                                      x-text="'#' + (idx + 1)"></span>
                                                <template x-if="prod.image">
                                                    <img :src="prod.image" :alt="prod.name" class="w-10 h-10 rounded-xl object-cover border border-slate-200 shrink-0">
                                                </template>
                                                <div class="min-w-0">
                                                    <p class="font-bold text-sm text-slate-900 truncate" x-text="prod.name"></p>
                                                    <p class="text-[11px] text-slate-500 tabular-nums" x-text="'Omzet: Rp ' + Number(prod.revenue).toLocaleString('id-ID') + ' • HPP: Rp ' + Number(prod.hpp).toLocaleString('id-ID') + ' • Laba: Rp ' + Number(prod.profit).toLocaleString('id-ID')"></p>
                                                </div>
                                            </div>
                                            <span class="bg-emerald-50 text-emerald-700 border border-emerald-200/70 text-xs font-black px-3 py-1 rounded-xl shrink-0 tabular-nums" x-text="prod.qty + ' pcs'"></span>
                                        </div>
                                    </template>
                                </div>
                            </template>

                            <template x-if="getRecapSummary().topProducts.length === 0">
                                <div class="p-8 text-center text-xs text-slate-400 bg-slate-50 rounded-2xl border border-dashed border-slate-200">
                                    Belum ada data produk terjual pada periode bulan ini.
                                </div>
                            </template>
                        </div>

                        <div class="lg:col-span-5 bg-white rounded-2xl border border-slate-200/70 shadow-xs p-6 flex flex-col justify-between">
                            <div>
                                <h4 class="text-sm font-black uppercase tracking-wider text-slate-900">Sebaran Metode Pembayaran</h4>
                                <p class="text-xs text-slate-500 mt-0.5 mb-4">Proporsi pilihan metode bayar pelanggan bulan ini.</p>
                            </div>
                            <div class="h-60 relative w-full">
                                <canvas id="recapPaymentChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-slate-200/70 shadow-xs overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                            <div>
                                <h4 class="text-sm font-black uppercase tracking-wider text-slate-900">Rincian Rekap Per Tanggal</h4>
                                <p class="text-xs text-slate-500 mt-0.5">Rekapitulasi transaksi aktif, HPP, dan laba kotor berdasarkan tanggal pada bulan terpilih.</p>
                            </div>
                            <span class="text-xs font-bold text-slate-500 tabular-nums" x-text="getRecapSummary().dailyRows.length + ' hari aktif'"></span>
                        </div>
                        <div class="overflow-x-auto custom-scrollbar">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-slate-50/80 border-b border-slate-200/70 text-[10px] uppercase tracking-widest text-slate-500 font-bold">
                                        <th class="px-6 py-3.5">Tanggal</th>
                                        <th class="px-6 py-3.5 text-center">Order Masuk</th>
                                        <th class="px-6 py-3.5 text-center">Terhitung</th>
                                        <th class="px-6 py-3.5 text-center">Dibatalkan</th>
                                        <th class="px-6 py-3.5 text-center">Terjual</th>
                                        <th class="px-6 py-3.5 text-right">Omzet (Rp)</th>
                                        <th class="px-6 py-3.5 text-right">HPP (Rp)</th>
                                        <th class="px-6 py-3.5 text-right">Laba Kotor (Rp)</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-xs tabular-nums">
                                    <template x-for="row in getRecapSummary().dailyRows" :key="row.day">
                                        <tr class="hover:bg-slate-50/80 transition">
                                            <td class="px-6 py-3.5 font-bold text-slate-900" x-text="row.dateLabel"></td>
                                            <td class="px-6 py-3.5 text-center font-semibold text-slate-700" x-text="row.totalTrx"></td>
                                            <td class="px-6 py-3.5 text-center font-bold text-emerald-700" x-text="row.validTrx"></td>
                                            <td class="px-6 py-3.5 text-center font-semibold text-rose-600" x-text="row.cancelTrx"></td>
                                            <td class="px-6 py-3.5 text-center font-bold text-slate-800" x-text="row.itemsSold + ' pcs'"></td>
                                            <td class="px-6 py-3.5 text-right font-black text-red-600" x-text="'Rp ' + Number(row.revenue).toLocaleString('id-ID')"></td>
                                            <td class="px-6 py-3.5 text-right font-bold text-amber-700" x-text="'Rp ' + Number(row.hpp).toLocaleString('id-ID')"></td>
                                            <td class="px-6 py-3.5 text-right font-black"
                                                :class="row.grossProfit >= 0 ? 'text-emerald-600' : 'text-rose-600'"
                                                x-text="(row.grossProfit < 0 ? '-Rp ' : 'Rp ') + Number(Math.abs(row.grossProfit)).toLocaleString('id-ID')"></td>
                                        </tr>
                                    </template>
                                    <template x-if="getRecapSummary().dailyRows.length === 0">
                                        <tr>
                                            <td colspan="8" class="px-6 py-10 text-center text-slate-400">
                                                Tidak ada transaksi tercatat pada bulan <span x-text="getMonthName(recapMonth) + ' ' + recapYear"></span>.
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <!-- =================================================================== -->
    <!-- MODAL QUICK PREVIEW KPI RINGKASAN PANEL                             -->
    <!-- =================================================================== -->
    <div x-show="dashModal.open"
         x-cloak
         class="fixed inset-0 z-[55] flex items-center justify-center p-4 bg-slate-950/75 backdrop-blur-xs print:hidden">
        <div @click.outside="dashModal.open = false"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95 translate-y-2"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             class="bg-white rounded-3xl max-w-2xl w-full overflow-hidden shadow-2xl ring-1 ring-slate-900/10">

            <div class="bg-slate-950 px-6 py-4 flex items-center justify-between text-white border-b border-slate-800">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-widest text-red-500 block" x-text="getDashboardStats().periodLabel"></span>
                    <h3 class="text-base font-black mt-0.5">
                        <span x-show="dashModal.type === 'orders'">Rincian Pesanan Masuk</span>
                        <span x-show="dashModal.type === 'revenue'">Rincian Omzet & Arus Kas</span>
                        <span x-show="dashModal.type === 'products'">Ringkasan Stok Katalog Produk</span>
                        <span x-show="dashModal.type === 'customers'" x-text="dashPeriodMode === 'all' ? 'Daftar Pelanggan Terdaftar' : 'Pelanggan Baru (' + getDashboardStats().periodLabel + ')'"></span>
                    </h3>
                </div>
                <button type="button" @click="dashModal.open = false" class="p-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 transition">✕</button>
            </div>

            <div class="p-6 max-h-[75vh] overflow-y-auto custom-scrollbar text-xs">
                <template x-if="dashModal.type === 'orders'">
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center tabular-nums">
                            <div class="bg-amber-50 border border-amber-200 rounded-xl p-3">
                                <span class="text-[10px] font-black uppercase text-amber-700 block">Pending</span>
                                <span class="text-lg font-black text-amber-900" x-text="getDashboardStats().pending"></span>
                            </div>
                            <div class="bg-blue-50 border border-blue-200 rounded-xl p-3">
                                <span class="text-[10px] font-black uppercase text-blue-700 block">Diproses</span>
                                <span class="text-lg font-black text-blue-900" x-text="getDashboardStats().diproses"></span>
                            </div>
                            <div class="bg-purple-50 border border-purple-200 rounded-xl p-3">
                                <span class="text-[10px] font-black uppercase text-purple-700 block">Dikirim</span>
                                <span class="text-lg font-black text-purple-900" x-text="getDashboardStats().dikirim"></span>
                            </div>
                            <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-3">
                                <span class="text-[10px] font-black uppercase text-emerald-700 block">Selesai</span>
                                <span class="text-lg font-black text-emerald-900" x-text="getDashboardStats().selesai"></span>
                            </div>
                        </div>

                        <div class="border border-slate-200 rounded-2xl divide-y divide-slate-100">
                            <template x-for="ord in getDashboardStats().filtered.slice(0, 8)" :key="ord.id">
                                <div class="px-4 py-3 flex items-center justify-between gap-3 hover:bg-slate-50">
                                    <div class="min-w-0">
                                        <p class="font-black text-slate-900">
                                            <span x-text="'#' + ord.code"></span> • <span class="font-bold text-slate-700" x-text="ord.customer"></span>
                                        </p>
                                        <p class="text-[11px] text-slate-500 mt-0.5 truncate" x-text="ord.date + ' • ' + ord.first_item_summary"></p>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <span class="font-black text-red-600 tabular-nums" x-text="'Rp ' + Number(ord.total).toLocaleString('id-ID')"></span>
                                        <button type="button"
                                                @click="dashModal.open = false; openOrderModal(ord.id)"
                                                class="px-2.5 py-1 rounded-lg bg-slate-900 text-white text-[10px] font-bold hover:bg-red-600 transition">
                                            Buka
                                        </button>
                                    </div>
                                </div>
                            </template>
                            <template x-if="getDashboardStats().filtered.length === 0">
                                <div class="p-8 text-center text-slate-400">Tidak ada pesanan pada periode ini.</div>
                            </template>
                        </div>

                        <div class="flex justify-end gap-2 pt-2">
                            <button type="button" @click="dashModal.open = false; setTab('pesanan')" class="bg-slate-900 hover:bg-red-600 text-white font-black uppercase tracking-wider px-4 py-2.5 rounded-xl transition">
                                Buka Tab Kelola Pesanan →
                            </button>
                        </div>
                    </div>
                </template>

                <template x-if="dashModal.type === 'revenue'">
                    <div class="space-y-4 tabular-nums">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="bg-slate-950 text-white rounded-2xl p-4">
                                <span class="text-[10px] font-black uppercase text-slate-400 block">Omzet Pesanan Selesai</span>
                                <span class="text-xl font-black text-white mt-1 block" x-text="'Rp ' + Number(getDashboardStats().realizedRevenue).toLocaleString('id-ID')"></span>
                                <span class="text-[11px] text-emerald-400 mt-1 block" x-text="getDashboardStats().selesai + ' transaksi selesai'"></span>
                            </div>
                            <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-4">
                                <span class="text-[10px] font-black uppercase text-emerald-700 block">Total Lunas Terverifikasi</span>
                                <span class="text-xl font-black text-emerald-900 mt-1 block" x-text="'Rp ' + Number(getDashboardStats().paidRevenue).toLocaleString('id-ID')"></span>
                                <span class="text-[11px] text-emerald-700 mt-1 block">Termasuk yang sedang diproses/dikirim</span>
                            </div>
                            <div class="bg-sky-50 border border-sky-200 rounded-2xl p-4">
                                <span class="text-[10px] font-black uppercase text-sky-700 block">Omzet Lunas Hari Ini</span>
                                <span class="text-lg font-black text-sky-900 mt-1 block" x-text="'Rp ' + Number(getDashboardStats().todayRevenue).toLocaleString('id-ID')"></span>
                            </div>
                            <div class="bg-amber-50 border border-amber-200 rounded-2xl p-4">
                                <span class="text-[10px] font-black uppercase text-amber-700 block">Potensi Pipeline Aktif</span>
                                <span class="text-lg font-black text-amber-900 mt-1 block" x-text="'Rp ' + Number(getDashboardStats().pipelineRevenue).toLocaleString('id-ID')"></span>
                            </div>
                        </div>

                        <div class="flex justify-end gap-2 pt-2">
                            <button type="button" @click="dashModal.open = false; recapMonth = dashMonth; recapYear = dashYear; setTab('rekap')" class="bg-red-600 hover:bg-red-700 text-white font-black uppercase tracking-wider px-4 py-2.5 rounded-xl transition">
                                Lihat Grafik & Rekap Bulanan →
                            </button>
                        </div>
                    </div>
                </template>

                <template x-if="dashModal.type === 'products'">
                    <div class="space-y-4">
                        <div class="grid grid-cols-3 gap-3 text-center tabular-nums">
                            <div class="bg-slate-50 border border-slate-200 rounded-xl p-3">
                                <span class="text-[10px] font-black uppercase text-slate-500 block">Total Katalog</span>
                                <span class="text-lg font-black text-slate-900">{{ $products->count() }}</span>
                            </div>
                            <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-3">
                                <span class="text-[10px] font-black uppercase text-emerald-700 block">Total Stok Fisik</span>
                                <span class="text-lg font-black text-emerald-900">{{ $totalStockAll }} pcs</span>
                            </div>
                            <div class="bg-rose-50 border border-rose-200 rounded-xl p-3">
                                <span class="text-[10px] font-black uppercase text-rose-700 block">Stok Menipis</span>
                                <span class="text-lg font-black text-rose-900">{{ $lowStockProducts->count() }}</span>
                            </div>
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <a href="{{ route('admin.produk.create') }}" class="bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold px-4 py-2.5 rounded-xl transition">+ Tambah Produk</a>
                            <button type="button" @click="dashModal.open = false; setTab('produk')" class="bg-slate-900 hover:bg-red-600 text-white font-black uppercase tracking-wider px-4 py-2.5 rounded-xl transition">
                                Kelola Semua Produk →
                            </button>
                        </div>
                    </div>
                </template>

                <template x-if="dashModal.type === 'customers'">
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-3 text-center tabular-nums">
                            <div class="bg-blue-50 border border-blue-200 rounded-xl p-3.5">
                                <span class="text-[10px] font-black uppercase text-blue-700 block" x-text="dashPeriodMode === 'all' ? 'Total Member Aktif' : 'Member Bergabung Bulan Ini'"></span>
                                <span class="text-xl font-black text-blue-900 mt-0.5 block" x-text="getDashboardStats().customerCount + ' akun'"></span>
                            </div>
                            <div class="bg-slate-50 border border-slate-200 rounded-xl p-3.5">
                                <span class="text-[10px] font-black uppercase text-slate-500 block">Total Seluruh Pelanggan</span>
                                <span class="text-xl font-black text-slate-900 mt-0.5 block">{{ $customers->count() }} akun</span>
                            </div>
                        </div>

                        <div class="border border-slate-200 rounded-2xl divide-y divide-slate-100">
                            <template x-for="cust in getDashboardStats().filteredCustomers.slice(0, 8)" :key="cust.id">
                                <div class="px-4 py-3 flex items-center justify-between gap-3 hover:bg-slate-50">
                                    <div class="min-w-0">
                                        <p class="font-bold text-slate-900 truncate" x-text="cust.name"></p>
                                        <p class="text-[11px] text-slate-500 truncate" x-text="cust.email"></p>
                                    </div>
                                    <div class="text-right shrink-0 tabular-nums">
                                        <span class="bg-slate-100 text-slate-800 text-[10px] font-bold px-2 py-0.5 rounded-md block" x-text="cust.total_orders + 'x order'"></span>
                                        <span class="text-[10px] text-slate-400 mt-0.5 block" x-text="'Gabung: ' + cust.joined_label"></span>
                                    </div>
                                </div>
                            </template>
                            <template x-if="getDashboardStats().filteredCustomers.length === 0">
                                <div class="p-8 text-center text-slate-400">Tidak ada pelanggan baru yang mendaftar pada periode ini.</div>
                            </template>
                        </div>

                        <div class="flex justify-end gap-2 pt-2">
                            <button type="button" @click="dashModal.open = false; setTab('pelanggan')" class="bg-slate-900 hover:bg-red-600 text-white font-black uppercase tracking-wider px-4 py-2.5 rounded-xl transition">
                                Buka Data Pelanggan →
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- =================================================================== -->
    <!-- MODAL DETAIL PESANAN, VERIFIKASI PEMBAYARAN & INPUT RESI            -->
    <!-- =================================================================== -->
    <div x-show="selectedOrder"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/75 backdrop-blur-xs print:hidden">
        <div @click.outside="selectedOrder = null"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95 translate-y-2"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             class="bg-white rounded-3xl max-w-2xl w-full overflow-hidden shadow-2xl ring-1 ring-slate-900/10">

            <template x-if="selectedOrder">
                <div>
                    <div class="bg-slate-950 px-6 py-4 flex items-center justify-between text-white border-b border-slate-800">
                        <div>
                            <span class="text-[10px] font-black uppercase tracking-widest text-red-500 block">Detail & Verifikasi Transaksi</span>
                            <div class="flex items-center gap-2 mt-0.5">
                                <h3 class="text-base font-black" x-text="'#' + selectedOrder.code"></h3>
                                <button type="button"
                                        @click="copyText(selectedOrder.code, 'Kode order disalin')"
                                        class="text-[10px] font-bold bg-slate-800 hover:bg-slate-700 text-slate-300 px-2 py-0.5 rounded-md transition">
                                    Salin Kode
                                </button>
                            </div>
                        </div>
                        <button type="button" @click="selectedOrder = null" class="p-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 transition">
                            ✕
                        </button>
                    </div>

                    <div class="p-6 max-h-[82vh] overflow-y-auto custom-scrollbar space-y-5 text-xs">

                        <div class="rounded-2xl border p-4"
                             :class="{
                                 'bg-amber-50/90 border-amber-200': selectedOrder.payment_status === 'waiting_verification' && selectedOrder.status !== 'dibatalkan',
                                 'bg-emerald-50/80 border-emerald-200': selectedOrder.payment_status === 'paid',
                                 'bg-rose-50/80 border-rose-200': selectedOrder.payment_status === 'rejected',
                                 'bg-slate-50 border-slate-200/80': selectedOrder.payment_status === 'unpaid'
                             }">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-3">
                                <div>
                                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-500 block">Status Pembayaran (<span x-text="selectedOrder.payment"></span>)</span>
                                    <div class="flex flex-wrap items-center gap-2 mt-1">
                                        <span x-show="selectedOrder.payment_status === 'waiting_verification' && selectedOrder.status !== 'dibatalkan'"
                                              class="text-[11px] font-black uppercase px-2.5 py-0.5 rounded-md bg-amber-600 text-white">
                                            ⏳ Menunggu Verifikasi Admin
                                        </span>
                                        <span x-show="selectedOrder.payment_status === 'paid'"
                                              class="text-[11px] font-black uppercase px-2.5 py-0.5 rounded-md bg-emerald-600 text-white">
                                            ✓ Lunas / Terverifikasi
                                        </span>
                                        <span x-show="selectedOrder.payment_status === 'rejected'"
                                              class="text-[11px] font-black uppercase px-2.5 py-0.5 rounded-md bg-rose-600 text-white">
                                            ✕ Bukti Pembayaran Ditolak
                                        </span>
                                        <span x-show="selectedOrder.payment_status === 'unpaid'"
                                              class="text-[11px] font-black uppercase px-2.5 py-0.5 rounded-md bg-slate-700 text-white">
                                            Belum Unggah Bukti Bayar
                                        </span>
                                        <span x-show="selectedOrder.payment_verified_at"
                                              class="text-[10px] font-semibold text-emerald-800"
                                              x-text="'Diverifikasi: ' + selectedOrder.payment_verified_at"></span>
                                    </div>
                                </div>

                                <div class="text-left sm:text-right">
                                    <span class="text-[10px] font-bold uppercase text-slate-500 block">Tagihan Wajib Cocok</span>
                                    <span class="text-base font-black text-red-600 tabular-nums" x-text="'Rp ' + Number(selectedOrder.total).toLocaleString('id-ID')"></span>
                                </div>
                            </div>

                            <template x-if="selectedOrder.payment_proof_url">
                                <div class="bg-white rounded-xl border border-slate-200/80 p-3 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                                    <div class="flex items-center gap-3">
                                        <img :src="selectedOrder.payment_proof_url"
                                             @click="openProofLightbox(selectedOrder.payment_proof_url, selectedOrder.code, selectedOrder.id)"
                                             alt="Bukti Pembayaran"
                                             class="w-16 h-16 rounded-xl object-cover border border-slate-200 cursor-zoom-in hover:opacity-90 transition shrink-0">
                                        <div>
                                            <p class="font-black text-slate-900 text-xs">Bukti Transfer / QRIS Pelanggan</p>
                                            <p class="text-[11px] text-slate-500 mt-0.5">Klik foto untuk memperbesar & memeriksa nominal transfer.</p>
                                            <button type="button"
                                                    @click="openProofLightbox(selectedOrder.payment_proof_url, selectedOrder.code, selectedOrder.id)"
                                                    class="text-[11px] font-bold text-blue-600 hover:underline mt-1 inline-block">
                                                🔍 Perbesar Foto Bukti
                                            </button>
                                        </div>
                                    </div>

                                    <template x-if="selectedOrder.status !== 'dibatalkan'">
                                        <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                                            <form :action="selectedOrder.verify_url" method="POST" class="flex-1 sm:flex-initial">
                                                @csrf
                                                <template x-if="!selectedOrder.has_verify_route">
                                                    <input type="hidden" name="_method" value="PUT">
                                                </template>
                                                <input type="hidden" name="action" value="approve">
                                                <input type="hidden" name="payment_status" value="paid">
                                                <input type="hidden" name="status" :value="selectedOrder.status === 'pending' ? 'diproses' : selectedOrder.status">
                                                <button type="submit"
                                                        class="w-full sm:w-auto bg-emerald-600 hover:bg-emerald-700 text-white font-black uppercase tracking-wider text-[11px] px-3.5 py-2.5 rounded-xl transition shadow-xs">
                                                    ✓ Terima & Proses
                                                </button>
                                            </form>

                                            <button type="button"
                                                    @click="rejectFormOpen = !rejectFormOpen"
                                                    class="bg-rose-50 hover:bg-rose-600 text-rose-600 hover:text-white border border-rose-200 font-black uppercase tracking-wider text-[11px] px-3 py-2.5 rounded-xl transition">
                                                ✕ Tolak Bukti
                                            </button>
                                        </div>
                                    </template>
                                </div>
                            </template>

                            <template x-if="!selectedOrder.payment_proof_url">
                                <div class="bg-white/80 rounded-xl border border-dashed border-slate-300 p-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-slate-500">
                                    <span>Pelanggan belum mengunggah foto bukti pembayaran.</span>
                                    <template x-if="selectedOrder.payment_status !== 'paid' && selectedOrder.status !== 'dibatalkan'">
                                        <form :action="selectedOrder.verify_url" method="POST">
                                            @csrf
                                            <template x-if="!selectedOrder.has_verify_route">
                                                <input type="hidden" name="_method" value="PUT">
                                            </template>
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="payment_status" value="paid">
                                            <input type="hidden" name="status" :value="selectedOrder.status === 'pending' ? 'diproses' : selectedOrder.status">
                                            <button type="submit" class="text-[11px] font-black text-emerald-700 hover:underline">
                                                Tandai Lunas Manual →
                                            </button>
                                        </form>
                                    </template>
                                </div>
                            </template>

                            <div x-show="rejectFormOpen" x-transition class="mt-3 bg-white rounded-xl border border-rose-200 p-3.5">
                                <form :action="selectedOrder.verify_url" method="POST" class="space-y-2.5">
                                    @csrf
                                    <template x-if="!selectedOrder.has_verify_route">
                                        <input type="hidden" name="_method" value="PUT">
                                    </template>
                                    <input type="hidden" name="action" value="reject">
                                    <input type="hidden" name="payment_status" value="rejected">
                                    <input type="hidden" name="status" value="pending">
                                    <label class="block text-[10px] font-black uppercase tracking-wider text-rose-700">Alasan Penolakan Bukti Pembayaran *</label>
                                    <input type="text"
                                           name="payment_rejected_reason"
                                           x-model="rejectReasonInput"
                                           required
                                           placeholder="Contoh: Nominal transfer kurang Rp 15.000 atau foto struk buram."
                                           class="w-full bg-rose-50/50 border border-rose-200 rounded-lg px-3 py-2 text-xs font-semibold text-slate-900 focus:ring-2 focus:ring-rose-500 outline-none">
                                    <div class="flex justify-end gap-2">
                                        <button type="button" @click="rejectFormOpen = false" class="px-3 py-1.5 rounded-lg text-xs font-bold text-slate-500 hover:bg-slate-100">Batal</button>
                                        <button type="submit" class="px-3.5 py-1.5 rounded-lg text-xs font-black uppercase bg-rose-600 hover:bg-rose-700 text-white">Kirim Penolakan</button>
                                    </div>
                                </form>
                            </div>

                            <template x-if="selectedOrder.payment_status === 'rejected' && selectedOrder.payment_rejected_reason && !rejectFormOpen">
                                <p class="mt-2.5 text-[11px] font-bold text-rose-700">
                                    Catatan Penolakan: <span class="font-medium" x-text="selectedOrder.payment_rejected_reason"></span>
                                </p>
                            </template>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-slate-50 p-4 rounded-2xl border border-slate-200/70">
                            <div>
                                <span class="text-[10px] font-bold uppercase text-slate-400 block">Penerima & Kontak</span>
                                <p class="font-bold text-slate-900 text-sm mt-0.5" x-text="selectedOrder.customer"></p>
                                <div class="flex items-center gap-2 mt-1">
                                    <p class="text-slate-600 font-medium" x-text="selectedOrder.phone"></p>
                                    <template x-if="selectedOrder.wa_link">
                                        <a :href="selectedOrder.wa_link" target="_blank" class="inline-flex items-center gap-1 bg-emerald-600 hover:bg-emerald-700 text-white text-[10px] font-black px-2 py-0.5 rounded-md transition">
                                            Chat WA
                                        </a>
                                    </template>
                                </div>
                            </div>
                            <div>
                                <span class="text-[10px] font-bold uppercase text-slate-400 block">Pembayaran & Pengiriman</span>
                                <p class="font-bold text-slate-900 mt-0.5" x-text="selectedOrder.payment + ' • ' + selectedOrder.shipping_method"></p>
                                <p class="text-slate-500 mt-0.5" x-text="'Order: ' + selectedOrder.date"></p>
                            </div>
                            <div class="sm:col-span-2 pt-2 border-t border-slate-200/70 flex items-start justify-between gap-3">
                                <div>
                                    <span class="text-[10px] font-bold uppercase text-slate-400 block">Alamat Pengiriman</span>
                                    <p class="text-slate-700 font-medium mt-0.5" x-text="selectedOrder.address + (selectedOrder.city && selectedOrder.city !== '-' ? ', ' + selectedOrder.city : '')"></p>
                                </div>
                                <button type="button"
                                        @click="copyShippingInfo(selectedOrder)"
                                        class="shrink-0 text-[10px] font-bold bg-white hover:bg-slate-900 text-slate-700 hover:text-white border border-slate-200 px-2.5 py-1.5 rounded-lg transition">
                                    Salin Alamat
                                </button>
                            </div>
                        </div>

                        <div>
                            <h4 class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Daftar Produk Dipesan</h4>
                            <div class="border border-slate-200/80 rounded-2xl divide-y divide-slate-100">
                                <template x-for="(item, idx) in selectedOrder.items" :key="idx">
                                    <div class="p-3.5 flex items-center justify-between gap-3">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <template x-if="item.image">
                                                <img :src="item.image" :alt="item.name" class="w-11 h-11 rounded-xl object-cover border border-slate-200 shrink-0">
                                            </template>
                                            <template x-if="!item.image">
                                                <div class="w-11 h-11 rounded-xl bg-slate-100 border border-slate-200 flex items-center justify-center text-base shrink-0">👕</div>
                                            </template>
                                            <div class="min-w-0">
                                                <p class="font-bold text-slate-900 truncate" x-text="item.name"></p>
                                                <p class="text-[11px] text-slate-500 mt-0.5">
                                                    Ukuran: <strong x-text="item.size"></strong>
                                                    <span x-show="item.color && item.color !== '-'" x-text="' • Warna: ' + item.color"></span>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="text-right tabular-nums shrink-0">
                                            <p class="text-slate-500" x-text="item.qty + ' x Rp ' + Number(item.price).toLocaleString('id-ID')"></p>
                                            <p class="font-black text-slate-900" x-text="'Rp ' + Number(item.subtotal).toLocaleString('id-ID')"></p>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200/70 space-y-1.5 tabular-nums">
                            <div class="flex justify-between text-slate-500">
                                <span>Subtotal Produk</span>
                                <span x-text="'Rp ' + Number(selectedOrder.subtotal || selectedOrder.total).toLocaleString('id-ID')"></span>
                            </div>
                            <div class="flex justify-between text-slate-500">
                                <span>Ongkos Kirim</span>
                                <span x-text="'Rp ' + Number(selectedOrder.shipping_cost || 0).toLocaleString('id-ID')"></span>
                            </div>
                            <div class="flex justify-between text-sm font-black text-slate-900 pt-2 border-t border-slate-200">
                                <span>Total Pembayaran</span>
                                <span class="text-red-600" x-text="'Rp ' + Number(selectedOrder.total).toLocaleString('id-ID')"></span>
                            </div>
                        </div>

                        <form :action="selectedOrder.update_url" method="POST" class="border-t border-slate-200 pt-4 space-y-3">
                            @csrf
                            <input type="hidden" name="_method" value="PUT">

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">Status Pesanan</label>
                                    <select name="status" x-model="selectedOrder.status" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-red-500 outline-none">
                                        <option value="pending">Pending</option>
                                        <option value="diproses">Diproses (Sedang Dikemas)</option>
                                        <option value="dikirim">Dikirim (Dalam Perjalanan)</option>
                                        <option value="selesai">Selesai</option>
                                        <option value="dibatalkan">Dibatalkan (Kembalikan Stok)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">Nomor Resi Pengiriman</label>
                                    <input type="text" name="tracking_number" x-model="selectedOrder.tracking_number" placeholder="Contoh: JP1234567890" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-red-500 outline-none">
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center justify-between gap-2 pt-2">
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" @click="triggerPrint(selectedOrder, 'invoice')" class="bg-slate-900 hover:bg-slate-800 text-white px-3.5 py-2.5 rounded-xl font-bold text-xs flex items-center gap-1.5 transition">
                                        Cetak Invoice A4
                                    </button>
                                    <button type="button" @click="triggerPrint(selectedOrder, 'struk')" class="bg-slate-100 hover:bg-slate-200 text-slate-800 px-3.5 py-2.5 rounded-xl font-bold text-xs transition">
                                        Cetak Struk 80mm
                                    </button>
                                    <button type="button" @click="triggerPrint(selectedOrder, 'label')" class="bg-purple-50 hover:bg-purple-600 text-purple-700 hover:text-white border border-purple-200 px-3.5 py-2.5 rounded-xl font-bold text-xs transition">
                                        Label Pengiriman
                                    </button>
                                </div>

                                <button type="submit" class="bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-700 hover:to-rose-700 text-white px-5 py-2.5 rounded-xl font-black text-xs uppercase tracking-wider shadow-md shadow-red-600/20 transition">
                                    Simpan Perubahan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- =================================================================== -->
    <!-- MODAL LIGHTBOX BUKTI PEMBAYARAN                                     -->
    <!-- =================================================================== -->
    <div x-show="proofLightbox.open"
         x-cloak
         class="fixed inset-0 z-[65] flex items-center justify-center p-4 bg-slate-950/85 backdrop-blur-xs print:hidden">
        <div @click.outside="proofLightbox.open = false"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             class="bg-slate-900 border border-slate-700 rounded-3xl max-w-xl w-full overflow-hidden shadow-2xl">
            <div class="px-5 py-3.5 border-b border-slate-800 flex items-center justify-between text-white">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-widest text-amber-400 block">Bukti Pembayaran</span>
                    <p class="text-xs font-black" x-text="proofLightbox.code ? 'Pesanan #' + proofLightbox.code : 'Lampiran Transfer'"></p>
                </div>
                <div class="flex items-center gap-2">
                    <a :href="proofLightbox.url" target="_blank" class="text-[11px] font-bold bg-slate-800 hover:bg-slate-700 text-slate-200 px-3 py-1.5 rounded-lg transition">
                        Tab Baru ↗
                    </a>
                    <button type="button" @click="proofLightbox.open = false" class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition">✕</button>
                </div>
            </div>
            <div class="p-4 bg-slate-950 flex items-center justify-center max-h-[75vh] overflow-auto">
                <img :src="proofLightbox.url" alt="Bukti Pembayaran" class="max-h-[68vh] w-auto rounded-xl object-contain shadow-lg">
            </div>
            <div class="px-5 py-3 bg-slate-900 border-t border-slate-800 flex items-center justify-between">
                <span class="text-[11px] text-slate-400">Periksa nominal & tanggal transaksi pada bukti transfer.</span>
                <template x-if="proofLightbox.orderId">
                    <button type="button"
                            @click="const id = proofLightbox.orderId; proofLightbox.open = false; openOrderModal(id)"
                            class="bg-amber-600 hover:bg-amber-500 text-white text-xs font-black uppercase tracking-wider px-4 py-2 rounded-xl transition">
                        Buka Panel Verifikasi →
                    </button>
                </template>
            </div>
        </div>
    </div>

    <!-- =================================================================== -->
    <!-- MODAL DIALOG KONFIRMASI (LOGOUT, HAPUS PRODUK, BATALKAN PESANAN)    -->
    <!-- =================================================================== -->
    <div x-show="confirmDialog.open"
         x-cloak
         class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-slate-950/75 backdrop-blur-xs print:hidden">
        <div @click.outside="closeConfirm()"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95 translate-y-2"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl ring-1 ring-slate-900/10">
            <div class="flex items-start gap-4">
                <div class="w-11 h-11 rounded-2xl bg-rose-50 text-rose-600 border border-rose-100 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                </div>
                <div class="flex-1">
                    <h3 class="text-base font-black text-slate-900" x-text="confirmDialog.title"></h3>
                    <p class="text-xs text-slate-500 mt-1.5 leading-relaxed" x-text="confirmDialog.message"></p>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2.5 mt-6 pt-4 border-t border-slate-100">
                <button type="button"
                        @click="closeConfirm()"
                        class="px-4 py-2.5 rounded-xl text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 transition">
                    Batal
                </button>
                <button type="button"
                        @click="executeConfirm()"
                        class="px-5 py-2.5 rounded-xl text-xs font-black uppercase tracking-wider text-white bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-700 hover:to-rose-700 shadow-md shadow-red-600/20 transition"
                        x-text="confirmDialog.confirmText">
                </button>
            </div>
        </div>
    </div>

    <!-- =================================================================== -->
    <!-- AREA CETAK INVOICE, STRUK 80MM & LABEL PENGIRIMAN (HANYA PRINT)     -->
    <!-- =================================================================== -->
    <div x-show="printOrder" x-cloak class="hidden print:block bg-white text-black">
        <template x-if="printOrder">
            <div>
                <div x-show="printMode === 'invoice'" class="max-w-3xl mx-auto p-6">
                    <div class="flex justify-between items-start border-b-2 border-black pb-5 mb-6">
                        <div>
                            <h1 class="text-2xl font-black tracking-tight uppercase italic">SPORT MANIA DISTRO</h1>
                            <p class="text-xs text-gray-600 mt-1">Pusat Pakaian, Sepatu & Perlengkapan Olahraga</p>
                            <p class="text-xs text-gray-600">Kota Malang, Jawa Timur • WhatsApp Admin: 0812-3456-7890</p>
                        </div>
                        <div class="text-right">
                            <span class="inline-block border-2 border-black px-3 py-1 text-xs font-black uppercase tracking-widest">INVOICE / BUKTI PEMBAYARAN</span>
                            <p class="text-sm font-black mt-2" x-text="'#' + printOrder.code"></p>
                            <p class="text-xs text-gray-600" x-text="'Tanggal: ' + printOrder.date"></p>
                            <p class="text-xs font-bold uppercase mt-1" x-text="'Status: ' + printOrder.status"></p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-6 mb-6 text-xs">
                        <div class="border border-gray-300 p-4 rounded-lg">
                            <p class="font-black uppercase text-gray-500 text-[10px] mb-1">Ditagihkan Kepada:</p>
                            <p class="font-bold text-sm text-black" x-text="printOrder.customer"></p>
                            <p class="text-gray-700 mt-0.5" x-text="'Telp/WA: ' + printOrder.phone"></p>
                            <p class="text-gray-700 mt-0.5" x-text="printOrder.address + (printOrder.city && printOrder.city !== '-' ? ', ' + printOrder.city : '')"></p>
                        </div>
                        <div class="border border-gray-300 p-4 rounded-lg">
                            <p class="font-black uppercase text-gray-500 text-[10px] mb-1">Informasi Pengiriman & Pembayaran:</p>
                            <p class="text-gray-700">Metode Bayar: <strong x-text="printOrder.payment"></strong></p>
                            <p class="text-gray-700 mt-0.5">Ekspedisi: <strong x-text="printOrder.shipping_method"></strong></p>
                            <p class="text-gray-700 mt-0.5" x-show="printOrder.tracking_number">No. Resi: <strong x-text="printOrder.tracking_number"></strong></p>
                        </div>
                    </div>

                    <table class="w-full text-left border-collapse text-xs mb-6">
                        <thead>
                            <tr class="border-y-2 border-black bg-gray-100 uppercase text-[10px] font-black">
                                <th class="py-2.5 px-3">No</th>
                                <th class="py-2.5 px-3">Nama Produk</th>
                                <th class="py-2.5 px-3">Varian</th>
                                <th class="py-2.5 px-3 text-center">Qty</th>
                                <th class="py-2.5 px-3 text-right">Harga Satuan</th>
                                <th class="py-2.5 px-3 text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-300">
                            <template x-for="(item, i) in printOrder.items" :key="i">
                                <tr>
                                    <td class="py-2.5 px-3" x-text="i + 1"></td>
                                    <td class="py-2.5 px-3 font-bold" x-text="item.name"></td>
                                    <td class="py-2.5 px-3" x-text="item.size + (item.color && item.color !== '-' ? ' / ' + item.color : '')"></td>
                                    <td class="py-2.5 px-3 text-center font-bold" x-text="item.qty"></td>
                                    <td class="py-2.5 px-3 text-right tabular-nums" x-text="'Rp ' + Number(item.price).toLocaleString('id-ID')"></td>
                                    <td class="py-2.5 px-3 text-right font-bold tabular-nums" x-text="'Rp ' + Number(item.subtotal).toLocaleString('id-ID')"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>

                    <div class="flex justify-end mb-8">
                        <div class="w-64 text-xs space-y-1.5 tabular-nums">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Subtotal Produk:</span>
                                <span class="font-bold" x-text="'Rp ' + Number(printOrder.subtotal || printOrder.total).toLocaleString('id-ID')"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Ongkos Kirim:</span>
                                <span class="font-bold" x-text="'Rp ' + Number(printOrder.shipping_cost || 0).toLocaleString('id-ID')"></span>
                            </div>
                            <div class="flex justify-between text-sm font-black border-t-2 border-black pt-2">
                                <span>TOTAL BAYAR:</span>
                                <span x-text="'Rp ' + Number(printOrder.total).toLocaleString('id-ID')"></span>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-gray-300 pt-4 text-center text-[11px] text-gray-600">
                        <p class="font-bold text-black">Terima kasih telah berbelanja di Sport Mania Distro!</p>
                        <p>Simpan invoice ini sebagai bukti transaksi dan garansi penukaran ukuran (maksimal 3 hari setelah barang diterima).</p>
                    </div>
                </div>

                <div x-show="printMode === 'struk'" class="w-[80mm] mx-auto font-mono text-[11px] leading-snug p-2">
                    <div class="text-center border-b border-dashed border-black pb-2 mb-2">
                        <p class="font-black text-sm tracking-tight">SPORT MANIA DISTRO</p>
                        <p class="text-[10px]">Kota Malang, Jawa Timur</p>
                        <p class="text-[10px]">WA: 0812-3456-7890</p>
                    </div>
                    <div class="border-b border-dashed border-black pb-2 mb-2 text-[10px] space-y-0.5">
                        <p x-text="'No   : #' + printOrder.code"></p>
                        <p x-text="'Tgl  : ' + printOrder.date"></p>
                        <p x-text="'Cust : ' + printOrder.customer"></p>
                        <p x-text="'Bayar: ' + printOrder.payment + ' (' + printOrder.status.toUpperCase() + ')'"></p>
                        <p x-show="printOrder.tracking_number" x-text="'Resi : ' + printOrder.tracking_number"></p>
                    </div>
                    <div class="border-b border-dashed border-black pb-2 mb-2 space-y-1.5">
                        <template x-for="(item, i) in printOrder.items" :key="i">
                            <div>
                                <p class="font-bold" x-text="item.name + ' (' + item.size + ')'"></p>
                                <div class="flex justify-between tabular-nums">
                                    <span x-text="item.qty + ' x ' + Number(item.price).toLocaleString('id-ID')"></span>
                                    <span class="font-bold" x-text="Number(item.subtotal).toLocaleString('id-ID')"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                    <div class="space-y-0.5 text-[11px] tabular-nums border-b border-dashed border-black pb-2 mb-2">
                        <div class="flex justify-between">
                            <span>Subtotal</span>
                            <span x-text="'Rp ' + Number(printOrder.subtotal || printOrder.total).toLocaleString('id-ID')"></span>
                        </div>
                        <div class="flex justify-between">
                            <span>Ongkir</span>
                            <span x-text="'Rp ' + Number(printOrder.shipping_cost || 0).toLocaleString('id-ID')"></span>
                        </div>
                        <div class="flex justify-between font-black text-xs pt-1">
                            <span>TOTAL</span>
                            <span x-text="'Rp ' + Number(printOrder.total).toLocaleString('id-ID')"></span>
                        </div>
                    </div>
                    <div class="text-center text-[10px]">
                        <p class="font-bold">TERIMA KASIH ATAS KUNJUNGAN ANDA</p>
                        <p>Barang yang sudah dibeli tidak dapat dikembalikan kecuali cacat produksi.</p>
                    </div>
                </div>

                <div x-show="printMode === 'label'" class="w-[105mm] mx-auto border-2 border-black p-4 font-sans text-xs">
                    <div class="flex items-center justify-between border-b-2 border-black pb-2.5 mb-3">
                        <div>
                            <p class="font-black text-base italic tracking-tight">SPORT MANIA DISTRO</p>
                            <p class="text-[10px] text-gray-600">Kota Malang • WA: 0812-3456-7890</p>
                        </div>
                        <div class="text-right">
                            <span class="border border-black px-2 py-0.5 font-black text-[10px] uppercase" x-text="printOrder.shipping_method"></span>
                            <p class="font-black text-xs mt-1" x-text="'#' + printOrder.code"></p>
                        </div>
                    </div>

                    <div class="border-b border-dashed border-black pb-3 mb-3">
                        <p class="text-[10px] font-black uppercase text-gray-500">PENERIMA PAKET:</p>
                        <p class="text-sm font-black mt-0.5" x-text="printOrder.customer"></p>
                        <p class="font-bold text-xs mt-0.5" x-text="'Telp/WA: ' + printOrder.phone"></p>
                        <p class="text-xs mt-1 leading-relaxed" x-text="printOrder.address + (printOrder.city && printOrder.city !== '-' ? ', ' + printOrder.city : '')"></p>
                    </div>

                    <div x-show="printOrder.tracking_number" class="border border-black bg-gray-100 px-3 py-1.5 mb-3 text-center">
                        <span class="text-[10px] uppercase font-bold block">NOMOR RESI PENGIRIMAN</span>
                        <span class="font-black text-sm tracking-widest" x-text="printOrder.tracking_number"></span>
                    </div>

                    <div>
                        <p class="text-[10px] font-black uppercase text-gray-500 mb-1">ISI PAKET (<span x-text="printOrder.items.length"></span> ITEM):</p>
                        <ul class="space-y-1 text-[11px]">
                            <template x-for="(item, i) in printOrder.items" :key="i">
                                <li class="flex justify-between">
                                    <span x-text="'• ' + item.name + ' (' + item.size + (item.color && item.color !== '-' ? '/' + item.color : '') + ')'"></span>
                                    <strong x-text="'x' + item.qty"></strong>
                                </li>
                            </template>
                        </ul>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <script>
        window.__SM_ORDERS__ = @json($ordersMap);
        window.__SM_CUSTOMERS__ = @json($customersList);
        window.__SM_POS_PRODUCTS__ = @json($posProductsList);
        window.__SM_RECAP_CHARTS__ = { daily: null, annual: null, payment: null };

        document.addEventListener('alpine:init', () => {
            Alpine.data('adminPanel', () => ({
                sidebarOpen: false,
                activeTab: sessionStorage.getItem('sm_admin_tab') || 'dashboard',
                searchProduct: '',
                filterCategory: 'semua',
                searchCustomer: '',
                searchOrder: '',
                orderFilter: 'semua',
                orderTimeFilter: 'semua',
                orderDateFrom: '',
                orderDateUntil: '',
                searchHistory: '',
                historyFilter: 'semua',
                historyTimeFilter: 'semua',
                historyDateFrom: '',
                historyDateUntil: '',
                selectedOrder: null,
                rejectFormOpen: false,
                rejectReasonInput: '',
                proofLightbox: {
                    open: false,
                    url: '',
                    code: '',
                    orderId: null
                },
                printOrder: null,
                printMode: 'invoice',
                toastMessage: '',

                // ============================================================
                // STATE KASIR (POS OFFLINE TOKO)
                // ============================================================
                posSearch: '',
                posCategory: 'semua',
                posSelectedVariants: {},
                posCart: [],
                posCustomerName: 'Pelanggan Walk-in',
                posCustomerPhone: '',
                posPaymentMethod: 'TUNAI',
                posDiscount: 0,
                posCashReceived: 0,
                posNotes: '',

                // ============================================================
                // STATE REKAP BULANAN + MODAL AWAL + HPP + LABA KOTOR
                // ============================================================
                recapMonth: new Date().getMonth() + 1,
                recapYear: new Date().getFullYear(),
                recapBasis: 'realized',
                modalAwalOpen: false,
                modalAwalDraft: 0,
                modalAwalByPeriod: JSON.parse(localStorage.getItem('sm_modal_awal_v1') || '{}'),
                defaultHppRatio: 0.65,

                // State Filter Bulan & Modal di Ringkasan Panel
                dashPeriodMode: 'all',
                dashMonth: new Date().getMonth() + 1,
                dashYear: new Date().getFullYear(),
                dashModal: {
                    open: false,
                    type: ''
                },

                promoFormOpen: false,
                editingPromo: null,
                openNewPromo() {
                    this.editingPromo = null;
                    this.promoFormOpen = true;
                },
                openEditPromo(promoObj) {
                    this.editingPromo = JSON.parse(JSON.stringify(promoObj));
                    this.promoFormOpen = true;
                },

                voucherFormOpen: false,
                voucherType: 'percent',

                confirmDialog: {
                    open: false,
                    title: '',
                    message: '',
                    confirmText: 'Ya, Lanjutkan',
                    variant: 'danger',
                    formId: '',
                    onCancel: null
                },

                init() {
                    (window.__SM_POS_PRODUCTS__ || []).forEach(p => {
                        if (p.has_variants && p.variants.length > 0) {
                            const firstInStock = p.variants.find(v => v.stock > 0) || p.variants[0];
                            this.posSelectedVariants[p.id] = firstInStock.id;
                        }
                    });

                    this.$watch('activeTab', (val) => {
                        if (val === 'rekap') {
                            this.$nextTick(() => setTimeout(() => this.renderRecapCharts(), 80));
                        }
                    });
                    this.$watch('recapMonth', () => {
                        this.modalAwalDraft = this.getCurrentModalAwal();
                        if (this.activeTab === 'rekap') this.$nextTick(() => this.renderRecapCharts());
                    });
                    this.$watch('recapYear', () => {
                        this.modalAwalDraft = this.getCurrentModalAwal();
                        if (this.activeTab === 'rekap') this.$nextTick(() => this.renderRecapCharts());
                    });
                    this.$watch('recapBasis', () => {
                        if (this.activeTab === 'rekap') this.$nextTick(() => this.renderRecapCharts());
                    });
                },

                setTab(tab) {
                    this.activeTab = tab;
                    sessionStorage.setItem('sm_admin_tab', tab);
                    this.sidebarOpen = false;
                    if (tab === 'rekap') {
                        this.$nextTick(() => setTimeout(() => this.renderRecapCharts(), 80));
                    }
                },

                showToast(msg) {
                    this.toastMessage = msg;
                    clearTimeout(this._toastTimer);
                    this._toastTimer = setTimeout(() => {
                        this.toastMessage = '';
                    }, 2600);
                },

                // ============================================================
                // ENGINE KASIR (POS OFFLINE TOKO)
                // ============================================================
                getPosFilteredProducts() {
                    const list = Array.isArray(window.__SM_POS_PRODUCTS__) ? window.__SM_POS_PRODUCTS__ : [];
                    const q = (this.posSearch || '').toLowerCase().trim();
                    return list.filter(p => {
                        const matchCat = this.posCategory === 'semua' || p.category_slug === this.posCategory;
                        const matchName = !q || p.name.toLowerCase().includes(q);
                        return matchCat && matchName;
                    });
                },

                getPosSelectedVariantObj(product) {
                    if (!product || !product.has_variants || !product.variants.length) return null;
                    const selectedId = Number(this.posSelectedVariants[product.id]);
                    return product.variants.find(v => Number(v.id) === selectedId) || product.variants[0];
                },

                getPosAvailableStock(product) {
                    if (!product) return 0;
                    if (product.has_variants) {
                        const v = this.getPosSelectedVariantObj(product);
                        return v ? Number(v.stock || 0) : 0;
                    }
                    return Number(product.stock || 0);
                },

                addToPosCart(product) {
                    if (!product.has_variants || !product.variants || product.variants.length === 0) {
                        this.showToast('Produk ini belum memiliki varian stok!');
                        return;
                    }

                    const variant = this.getPosSelectedVariantObj(product);
                    const maxStock = this.getPosAvailableStock(product);

                    if (!variant || maxStock <= 0) {
                        this.showToast('Stok produk/varian ini habis!');
                        return;
                    }

                    const key = product.id + '_' + variant.id;
                    const existing = this.posCart.find(item => item.key === key);

                    if (existing) {
                        if (existing.qty >= maxStock) {
                            this.showToast('Jumlah maksimal sesuai stok tersedia (' + maxStock + ' pcs)');
                            return;
                        }
                        existing.qty += 1;
                    } else {
                        this.posCart.push({
                            key,
                            product_id: product.id,
                            variant_id: variant.id,
                            name: product.name,
                            size: variant.size,
                            color: variant.color,
                            price: Number(product.price || 0),
                            hpp: Number(product.hpp || 0),
                            qty: 1,
                            max_stock: maxStock,
                            image: product.image
                        });
                    }
                    this.showToast('+ ' + product.name + ' masuk keranjang kasir');
                },

                updatePosCartQty(index, delta) {
                    const item = this.posCart[index];
                    if (!item) return;
                    const nextQty = item.qty + delta;
                    if (nextQty <= 0) {
                        this.posCart.splice(index, 1);
                        return;
                    }
                    if (nextQty > item.max_stock) {
                        this.showToast('Stok maksimal tersisa ' + item.max_stock + ' pcs');
                        return;
                    }
                    item.qty = nextQty;
                },

                removePosCartItem(index) {
                    this.posCart.splice(index, 1);
                },

                clearPosCart() {
                    this.posCart = [];
                    this.posDiscount = 0;
                    this.posCashReceived = 0;
                    this.posNotes = '';
                    this.posCustomerName = 'Pelanggan Walk-in';
                    this.posCustomerPhone = '';
                },

                getPosSubtotal() {
                    return this.posCart.reduce((sum, it) => sum + (Number(it.price) * Number(it.qty)), 0);
                },

                getPosTotal() {
                    const sub = this.getPosSubtotal();
                    const disc = Math.max(0, Number(this.posDiscount || 0));
                    return Math.max(0, sub - disc);
                },

                getPosChange() {
                    if (this.posPaymentMethod !== 'TUNAI') return 0;
                    const paid = Number(this.posCashReceived || 0);
                    const total = this.getPosTotal();
                    return Math.max(0, paid - total);
                },

                canSubmitPosCheckout() {
                    if (this.posCart.length === 0) return false;
                    if (this.posPaymentMethod === 'TUNAI') {
                        return Number(this.posCashReceived || 0) >= this.getPosTotal();
                    }
                    return true;
                },

                previewPosReceipt() {
                    if (this.posCart.length === 0) {
                        this.showToast('Keranjang kasir masih kosong');
                        return;
                    }
                    const now = new Date();
                    const dateStr = now.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) + ', ' + now.toTimeString().slice(0, 5);
                    const mockOrder = {
                        code: 'POS-' + now.getFullYear() + String(now.getMonth() + 1).padStart(2, '0') + String(now.getDate()).padStart(2, '0') + '-' + String(now.getHours()).padStart(2, '0') + String(now.getMinutes()).padStart(2, '0'),
                        date: dateStr,
                        status: 'selesai',
                        customer: this.posCustomerName || 'Pelanggan Walk-in',
                        phone: this.posCustomerPhone || '-',
                        payment: this.posPaymentMethod,
                        shipping_method: 'KASIR TOKO (POS)',
                        address: 'Transaksi Langsung di Store Malang',
                        city: 'Malang',
                        tracking_number: '',
                        subtotal: this.getPosSubtotal(),
                        shipping_cost: 0,
                        total: this.getPosTotal(),
                        items: this.posCart.map(it => ({
                            name: it.name,
                            size: it.size,
                            color: it.color,
                            qty: it.qty,
                            price: it.price,
                            hpp: it.hpp,
                            subtotal: it.price * it.qty
                        }))
                    };
                    this.triggerPrint(mockOrder, 'struk');
                },

                // ============================================================
                // HELPER FILTER BULAN & MODAL RINGKASAN PANEL
                // ============================================================
                getDashboardStats() {
                    const all = Object.values(window.__SM_ORDERS__ || {});
                    const allCustomers = Array.isArray(window.__SM_CUSTOMERS__) ? window.__SM_CUSTOMERS__ : [];
                    const isMonthly = this.dashPeriodMode === 'monthly';
                    const targetM = Number(this.dashMonth);
                    const targetY = Number(this.dashYear);

                    const filtered = !isMonthly
                        ? all
                        : all.filter(o => Number(o.created_month) === targetM && Number(o.created_year) === targetY);

                    const filteredCustomers = !isMonthly
                        ? allCustomers
                        : allCustomers.filter(c => Number(c.created_month) === targetM && Number(c.created_year) === targetY);

                    const pending = filtered.filter(o => o.status === 'pending').length;
                    const diproses = filtered.filter(o => o.status === 'diproses').length;
                    const dikirim = filtered.filter(o => o.status === 'dikirim').length;
                    const selesaiList = filtered.filter(o => o.status === 'selesai');
                    const dibatalkan = filtered.filter(o => o.status === 'dibatalkan').length;
                    const waitingVerify = filtered.filter(o => o.status !== 'dibatalkan' && o.payment_status === 'waiting_verification').length;

                    const realizedRevenue = selesaiList.reduce((s, o) => s + Number(o.total || 0), 0);
                    const paidRevenue = filtered
                        .filter(o => o.status !== 'dibatalkan' && o.payment_status === 'paid')
                        .reduce((s, o) => s + Number(o.total || 0), 0);
                    const todayRevenue = filtered
                        .filter(o => o.is_today && o.status !== 'dibatalkan' && o.payment_status === 'paid')
                        .reduce((s, o) => s + Number(o.total || 0), 0);
                    const pipelineRevenue = filtered
                        .filter(o => ['pending', 'diproses', 'dikirim'].includes(o.status))
                        .reduce((s, o) => s + Number(o.total || 0), 0);

                    return {
                        filtered,
                        filteredCustomers,
                        customerCount: filteredCustomers.length,
                        totalOrders: filtered.length,
                        needProcess: pending + diproses,
                        waitingVerify,
                        pending,
                        diproses,
                        dikirim,
                        selesai: selesaiList.length,
                        dibatalkan,
                        realizedRevenue,
                        paidRevenue,
                        todayRevenue,
                        pipelineRevenue,
                        periodLabel: !isMonthly
                            ? 'Semua Waktu'
                            : `${this.getMonthName(this.dashMonth)} ${this.dashYear}`
                    };
                },

                // ============================================================
                // ENGINE REKAP BULANAN, MODAL AWAL, HPP, LABA KOTOR & GRAFIK
                // ============================================================
                getAvailableRecapYears() {
                    const currentY = new Date().getFullYear();
                    const years = new Set([currentY, currentY - 1]);
                    Object.values(window.__SM_ORDERS__ || {}).forEach(o => {
                        if (o.created_year) years.add(Number(o.created_year));
                    });
                    return Array.from(years).sort((a, b) => b - a);
                },

                getMonthName(m) {
                    const names = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
                    return names[Number(m) - 1] || '-';
                },

                getModalAwalKey(y = this.recapYear, m = this.recapMonth) {
                    return `${Number(y)}-${String(Number(m)).padStart(2, '0')}`;
                },

                getCurrentModalAwal(y = this.recapYear, m = this.recapMonth) {
                    const key = this.getModalAwalKey(y, m);
                    return Math.max(0, Number(this.modalAwalByPeriod?.[key] || 0));
                },

                openModalAwalEditor() {
                    this.modalAwalDraft = this.getCurrentModalAwal();
                    this.modalAwalOpen = true;
                },

                saveCurrentModalAwal() {
                    const key = this.getModalAwalKey();
                    const nominal = Math.max(0, Number(this.modalAwalDraft || 0));
                    this.modalAwalByPeriod = {
                        ...(this.modalAwalByPeriod || {}),
                        [key]: nominal
                    };
                    localStorage.setItem('sm_modal_awal_v1', JSON.stringify(this.modalAwalByPeriod));
                    this.modalAwalOpen = false;
                    this.showToast(`Modal awal ${this.getMonthName(this.recapMonth)} ${this.recapYear} disimpan`);
                },

                estimateItemHpp(item) {
                    const qty = Math.max(1, Number(item?.qty || 1));
                    const unitPrice = Number(item?.price || 0);
                    const subtotal = Number(item?.subtotal ?? (unitPrice * qty));

                    const rawUnitHpp = item?.hpp ?? item?.cost_price ?? item?.unit_cost ?? null;
                    if (rawUnitHpp !== null && rawUnitHpp !== '' && !Number.isNaN(Number(rawUnitHpp)) && Number(rawUnitHpp) > 0) {
                        return Math.max(0, Number(rawUnitHpp) * qty);
                    }

                    const rawTotalHpp = item?.total_hpp ?? item?.hpp_subtotal ?? null;
                    if (rawTotalHpp !== null && rawTotalHpp !== '' && !Number.isNaN(Number(rawTotalHpp)) && Number(rawTotalHpp) > 0) {
                        return Math.max(0, Number(rawTotalHpp));
                    }

                    const posList = Array.isArray(window.__SM_POS_PRODUCTS__) ? window.__SM_POS_PRODUCTS__ : [];
                    const matchedProd = posList.find(p =>
                        (item?.product_id && Number(p.id) === Number(item.product_id)) ||
                        (item?.name && String(p.name || '').toLowerCase() === String(item.name).toLowerCase())
                    );
                    if (matchedProd && Number(matchedProd.hpp || 0) > 0) {
                        return Math.max(0, Number(matchedProd.hpp) * qty);
                    }

                    return Math.round(subtotal * this.defaultHppRatio);
                },

                estimateOrderHpp(order) {
                    const items = Array.isArray(order?.items) ? order.items : [];
                    if (items.length > 0) {
                        return items.reduce((sum, it) => sum + this.estimateItemHpp(it), 0);
                    }
                    return Math.round(Number(order?.total || 0) * this.defaultHppRatio);
                },

                isOrderCountedInRecap(o) {
                    if (o.status === 'dibatalkan') return false;
                    if (this.recapBasis === 'realized') {
                        return o.status === 'selesai' || o.payment_status === 'paid';
                    }
                    return true;
                },

                getRecapSummary() {
                    const all = Object.values(window.__SM_ORDERS__ || {});
                    const m = Number(this.recapMonth);
                    const y = Number(this.recapYear);

                    const prevM = m === 1 ? 12 : m - 1;
                    const prevY = m === 1 ? y - 1 : y;

                    const monthOrders = all.filter(o => Number(o.created_year) === y && Number(o.created_month) === m);
                    const prevMonthOrders = all.filter(o => Number(o.created_year) === prevY && Number(o.created_month) === prevM);

                    const validOrders = monthOrders.filter(o => this.isOrderCountedInRecap(o));
                    const prevValidOrders = prevMonthOrders.filter(o => this.isOrderCountedInRecap(o));

                    const revenue = validOrders.reduce((s, o) => s + Number(o.total || 0), 0);
                    const prevRevenue = prevValidOrders.reduce((s, o) => s + Number(o.total || 0), 0);

                    const totalHpp = validOrders.reduce((s, o) => s + this.estimateOrderHpp(o), 0);
                    const grossProfit = revenue - totalHpp;
                    const grossMarginPct = revenue > 0 ? ((grossProfit / revenue) * 100).toFixed(1) : '0.0';
                    const modalAwal = this.getCurrentModalAwal(y, m);
                    const modalPlusGrossProfit = modalAwal + grossProfit;

                    const growthPct = prevRevenue > 0
                        ? Math.round(((revenue - prevRevenue) / prevRevenue) * 100)
                        : (revenue > 0 ? 100 : 0);

                    const completedCount = monthOrders.filter(o => o.status === 'selesai').length;
                    const cancelledCount = monthOrders.filter(o => o.status === 'dibatalkan').length;
                    const activeCount = monthOrders.filter(o => ['pending', 'diproses', 'dikirim'].includes(o.status)).length;

                    let totalItemsSold = 0;
                    const productMap = {};
                    const paymentMap = {};

                    validOrders.forEach(o => {
                        const payKey = o.payment || 'LAINNYA';
                        paymentMap[payKey] = (paymentMap[payKey] || 0) + 1;

                        (o.items || []).forEach(it => {
                            const q = Number(it.qty || 1);
                            const sub = Number(it.subtotal || (Number(it.price || 0) * q));
                            const itemHpp = this.estimateItemHpp(it);
                            const itemProfit = sub - itemHpp;
                            totalItemsSold += q;

                            if (!productMap[it.name]) {
                                productMap[it.name] = { name: it.name, image: it.image, qty: 0, revenue: 0, hpp: 0, profit: 0 };
                            }
                            productMap[it.name].qty += q;
                            productMap[it.name].revenue += sub;
                            productMap[it.name].hpp += itemHpp;
                            productMap[it.name].profit += itemProfit;
                        });
                    });

                    const topProducts = Object.values(productMap)
                        .sort((a, b) => b.qty - a.qty || b.revenue - a.revenue)
                        .slice(0, 5);

                    const avgOrderValue = validOrders.length > 0 ? Math.round(revenue / validOrders.length) : 0;

                    const daysInMonth = new Date(y, m, 0).getDate();
                    const dailyRows = [];
                    for (let d = 1; d <= daysInMonth; d++) {
                        const dayAll = monthOrders.filter(o => Number(o.created_day) === d);
                        const dayValid = dayAll.filter(o => this.isOrderCountedInRecap(o));
                        const dayCancel = dayAll.filter(o => o.status === 'dibatalkan').length;
                        const dayRev = dayValid.reduce((s, o) => s + Number(o.total || 0), 0);
                        const dayHpp = dayValid.reduce((s, o) => s + this.estimateOrderHpp(o), 0);
                        const dayGrossProfit = dayRev - dayHpp;
                        let dayItems = 0;
                        dayValid.forEach(o => (o.items || []).forEach(it => dayItems += Number(it.qty || 1)));

                        if (dayAll.length > 0) {
                            const dateLabel = `${String(d).padStart(2, '0')} ${this.getMonthName(m)} ${y}`;
                            dailyRows.push({
                                day: d,
                                dateLabel,
                                totalTrx: dayAll.length,
                                validTrx: dayValid.length,
                                cancelTrx: dayCancel,
                                itemsSold: dayItems,
                                revenue: dayRev,
                                hpp: dayHpp,
                                grossProfit: dayGrossProfit
                            });
                        }
                    }

                    return {
                        totalAllOrders: monthOrders.length,
                        validCount: validOrders.length,
                        completedCount,
                        activeCount,
                        cancelledCount,
                        revenue,
                        prevRevenue,
                        prevMonthLabel: `${this.getMonthName(prevM)} ${prevY}`,
                        growthPct,
                        modalAwal,
                        totalHpp,
                        grossProfit,
                        grossMarginPct,
                        modalPlusGrossProfit,
                        totalItemsSold,
                        avgOrderValue,
                        topProducts,
                        paymentMap,
                        dailyRows,
                        daysInMonth
                    };
                },

                renderRecapCharts() {
                    if (typeof Chart === 'undefined') return;

                    const all = Object.values(window.__SM_ORDERS__ || {});
                    const m = Number(this.recapMonth);
                    const y = Number(this.recapYear);
                    const summary = this.getRecapSummary();

                    const dailyCanvas = document.getElementById('recapDailyChart');
                    if (dailyCanvas) {
                        const labels = [];
                        const revData = [];
                        const profitData = [];
                        const countData = [];

                        for (let d = 1; d <= summary.daysInMonth; d++) {
                            labels.push(String(d).padStart(2, '0'));
                            const dayOrders = all.filter(o =>
                                Number(o.created_year) === y &&
                                Number(o.created_month) === m &&
                                Number(o.created_day) === d &&
                                this.isOrderCountedInRecap(o)
                            );
                            const dRev = dayOrders.reduce((s, o) => s + Number(o.total || 0), 0);
                            const dHpp = dayOrders.reduce((s, o) => s + this.estimateOrderHpp(o), 0);
                            revData.push(dRev);
                            profitData.push(dRev - dHpp);
                            countData.push(dayOrders.length);
                        }

                        if (window.__SM_RECAP_CHARTS__.daily) {
                            window.__SM_RECAP_CHARTS__.daily.destroy();
                        }

                        window.__SM_RECAP_CHARTS__.daily = new Chart(dailyCanvas, {
                            type: 'bar',
                            data: {
                                labels,
                                datasets: [
                                    {
                                        label: 'Omzet Harian (Rp)',
                                        data: revData,
                                        backgroundColor: 'rgba(220, 38, 38, 0.85)',
                                        hoverBackgroundColor: 'rgba(185, 28, 28, 1)',
                                        borderRadius: 6,
                                        yAxisID: 'y'
                                    },
                                    {
                                        label: 'Laba Kotor Harian (Rp)',
                                        data: profitData,
                                        backgroundColor: 'rgba(16, 185, 129, 0.82)',
                                        hoverBackgroundColor: 'rgba(5, 150, 105, 1)',
                                        borderRadius: 6,
                                        yAxisID: 'y'
                                    },
                                    {
                                        type: 'line',
                                        label: 'Jumlah Transaksi',
                                        data: countData,
                                        borderColor: '#0f172a',
                                        backgroundColor: '#0f172a',
                                        borderWidth: 2,
                                        tension: 0.35,
                                        pointRadius: 3,
                                        yAxisID: 'y1'
                                    }
                                ]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                interaction: { mode: 'index', intersect: false },
                                plugins: {
                                    legend: { position: 'top', labels: { font: { size: 11, weight: 'bold' } } },
                                    tooltip: {
                                        callbacks: {
                                            label: (ctx) => {
                                                if (ctx.datasetIndex === 0) {
                                                    return ' Omzet: Rp ' + Number(ctx.raw || 0).toLocaleString('id-ID');
                                                }
                                                if (ctx.datasetIndex === 1) {
                                                    return ' Laba Kotor: Rp ' + Number(ctx.raw || 0).toLocaleString('id-ID');
                                                }
                                                return ' Transaksi: ' + ctx.raw + ' order';
                                            }
                                        }
                                    }
                                },
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        grid: { color: '#f1f5f9' },
                                        ticks: {
                                            font: { size: 10 },
                                            callback: (v) => 'Rp ' + (v >= 1000000 ? (v / 1000000) + 'jt' : (v >= 1000 ? (v / 1000) + 'rb' : v))
                                        }
                                    },
                                    y1: {
                                        beginAtZero: true,
                                        position: 'right',
                                        grid: { drawOnChartArea: false },
                                        ticks: { stepSize: 1, font: { size: 10 } }
                                    },
                                    x: {
                                        grid: { display: false },
                                        ticks: { font: { size: 10 } }
                                    }
                                }
                            }
                        });
                    }

                    const annualCanvas = document.getElementById('recapAnnualChart');
                    if (annualCanvas) {
                        const monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
                        const monthlyRevs = [];
                        const bgColors = [];

                        for (let idx = 1; idx <= 12; idx++) {
                            const mOrders = all.filter(o =>
                                Number(o.created_year) === y &&
                                Number(o.created_month) === idx &&
                                this.isOrderCountedInRecap(o)
                            );
                            monthlyRevs.push(mOrders.reduce((s, o) => s + Number(o.total || 0), 0));
                            bgColors.push(idx === m ? '#dc2626' : '#cbd5e1');
                        }

                        if (window.__SM_RECAP_CHARTS__.annual) {
                            window.__SM_RECAP_CHARTS__.annual.destroy();
                        }

                        window.__SM_RECAP_CHARTS__.annual = new Chart(annualCanvas, {
                            type: 'bar',
                            data: {
                                labels: monthLabels,
                                datasets: [{
                                    label: 'Omzet Bulanan ' + y,
                                    data: monthlyRevs,
                                    backgroundColor: bgColors,
                                    borderRadius: 8
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: { display: false },
                                    tooltip: {
                                        callbacks: {
                                            label: (ctx) => ' Omzet: Rp ' + Number(ctx.raw || 0).toLocaleString('id-ID')
                                        }
                                    }
                                },
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        grid: { color: '#f1f5f9' },
                                        ticks: {
                                            font: { size: 10 },
                                            callback: (v) => 'Rp ' + (v >= 1000000 ? (v / 1000000) + 'jt' : (v >= 1000 ? (v / 1000) + 'rb' : v))
                                        }
                                    },
                                    x: { grid: { display: false }, ticks: { font: { size: 11, weight: 'bold' } } }
                                }
                            }
                        });
                    }

                    const payCanvas = document.getElementById('recapPaymentChart');
                    if (payCanvas) {
                        const payLabels = Object.keys(summary.paymentMap);
                        const payValues = Object.values(summary.paymentMap);

                        if (window.__SM_RECAP_CHARTS__.payment) {
                            window.__SM_RECAP_CHARTS__.payment.destroy();
                        }

                        window.__SM_RECAP_CHARTS__.payment = new Chart(payCanvas, {
                            type: 'doughnut',
                            data: {
                                labels: payLabels.length ? payLabels : ['Belum ada data'],
                                datasets: [{
                                    data: payValues.length ? payValues : [1],
                                    backgroundColor: payLabels.length
                                        ? ['#0f172a', '#dc2626', '#2563eb', '#10b981', '#f59e0b', '#8b5cf6']
                                        : ['#e2e8f0'],
                                    borderWidth: 2,
                                    borderColor: '#ffffff'
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                cutout: '66%',
                                plugins: {
                                    legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11, weight: 'bold' } } }
                                }
                            }
                        });
                    }
                },

                exportMonthlyRecapCsv() {
                    const summary = this.getRecapSummary();
                    if (!summary.dailyRows.length) {
                        this.showToast('Tidak ada data transaksi pada bulan ini untuk diekspor');
                        return;
                    }

                    const headers = [
                        'Tanggal',
                        'Total Order Masuk',
                        'Order Valid / Lunas',
                        'Order Dibatalkan',
                        'Total Produk Terjual (Pcs)',
                        'Total Omzet (Rp)',
                        'Total HPP (Rp)',
                        'Laba Kotor (Rp)'
                    ];
                    const esc = (v) => '"' + String(v ?? '-').replace(/"/g, '""') + '"';

                    const rows = summary.dailyRows.map(r => [
                        esc(r.dateLabel),
                        esc(r.totalTrx),
                        esc(r.validTrx),
                        esc(r.cancelTrx),
                        esc(r.itemsSold),
                        esc(Math.round(r.revenue).toLocaleString('id-ID')),
                        esc(Math.round(r.hpp).toLocaleString('id-ID')),
                        esc(Math.round(r.grossProfit).toLocaleString('id-ID'))
                    ].join(';'));

                    rows.push([
                        esc('MODAL AWAL ' + this.getMonthName(this.recapMonth).toUpperCase() + ' ' + this.recapYear),
                        esc('-'),
                        esc('-'),
                        esc('-'),
                        esc('-'),
                        esc(Math.round(summary.modalAwal).toLocaleString('id-ID')),
                        esc('-'),
                        esc('-')
                    ].join(';'));

                    rows.push([
                        esc('TOTAL BULAN ' + this.getMonthName(this.recapMonth).toUpperCase() + ' ' + this.recapYear),
                        esc(summary.totalAllOrders),
                        esc(summary.validCount),
                        esc(summary.cancelledCount),
                        esc(summary.totalItemsSold),
                        esc(Math.round(summary.revenue).toLocaleString('id-ID')),
                        esc(Math.round(summary.totalHpp).toLocaleString('id-ID')),
                        esc(Math.round(summary.grossProfit).toLocaleString('id-ID'))
                    ].join(';'));

                    const csvContent = '\uFEFFsep=;\n' + [headers.map(esc).join(';'), ...rows].join('\n');
                    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                    const url = URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = `Rekap_Bulanan_SportMania_${this.getMonthName(this.recapMonth)}_${this.recapYear}.csv`;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    URL.revokeObjectURL(url);
                    this.showToast(`Rekap ${this.getMonthName(this.recapMonth)} ${this.recapYear} berhasil diunduh`);
                },

                parseOrderDate(dateStr) {
                    if (!dateStr || dateStr === '-') return null;
                    const months = {
                        jan: 0, januari: 0,
                        feb: 1, februari: 1,
                        mar: 2, maret: 2,
                        apr: 3, april: 3,
                        mei: 4, may: 4,
                        jun: 5, juni: 5,
                        jul: 6, juli: 6,
                        agu: 7, agustus: 7, aug: 7,
                        sep: 8, september: 8,
                        okt: 9, oktober: 9, oct: 9,
                        nov: 10, november: 10,
                        des: 11, desember: 11, dec: 11
                    };
                    const match = String(dateStr).trim().match(/^(\d{1,2})\s+([a-zA-Z]+)\s+(\d{4})(?:,\s*(\d{1,2}):(\d{2}))?/);
                    if (!match) return null;
                    const day = parseInt(match[1], 10);
                    const month = months[match[2].toLowerCase()];
                    const year = parseInt(match[3], 10);
                    if (month === undefined) return null;
                    const hour = match[4] ? parseInt(match[4], 10) : 0;
                    const minute = match[5] ? parseInt(match[5], 10) : 0;
                    return new Date(year, month, day, hour, minute, 0);
                },

                getTimeLabelText(dateStr) {
                    const dt = this.parseOrderDate(dateStr);
                    if (!dt || isNaN(dt.getTime())) return dateStr || '-';

                    const now = new Date();
                    const todayStart = new Date(now.getFullYear(), now.getMonth(), now.getDate());
                    const yesterdayStart = new Date(todayStart);
                    yesterdayStart.setDate(yesterdayStart.getDate() - 1);
                    const itemStart = new Date(dt.getFullYear(), dt.getMonth(), dt.getDate());

                    if (itemStart.getTime() === todayStart.getTime()) return 'Hari Ini';
                    if (itemStart.getTime() === yesterdayStart.getTime()) return 'Kemarin';
                    return dt.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
                },

                matchTimeFilter(dateStr, preset, dateFrom, dateUntil) {
                    const dt = this.parseOrderDate(dateStr);
                    if (!dt || isNaN(dt.getTime())) return true;

                    const now = new Date();
                    const todayStart = new Date(now.getFullYear(), now.getMonth(), now.getDate());
                    const todayEnd = new Date(now.getFullYear(), now.getMonth(), now.getDate(), 23, 59, 59, 999);
                    const yesterdayStart = new Date(todayStart);
                    yesterdayStart.setDate(yesterdayStart.getDate() - 1);
                    const yesterdayEnd = new Date(yesterdayStart.getFullYear(), yesterdayStart.getMonth(), yesterdayStart.getDate(), 23, 59, 59, 999);

                    if (preset === 'today') {
                        if (dt < todayStart || dt > todayEnd) return false;
                    } else if (preset === 'yesterday') {
                        if (dt < yesterdayStart || dt > yesterdayEnd) return false;
                    } else if (preset === '7days') {
                        const sevenDaysAgo = new Date(todayStart);
                        sevenDaysAgo.setDate(sevenDaysAgo.getDate() - 6);
                        if (dt < sevenDaysAgo || dt > todayEnd) return false;
                    } else if (preset === 'this_month') {
                        const monthStart = new Date(now.getFullYear(), now.getMonth(), 1);
                        if (dt < monthStart || dt > todayEnd) return false;
                    }

                    if (dateFrom) {
                        const [y, m, d] = dateFrom.split('-').map(Number);
                        const fromDt = new Date(y, m - 1, d, 0, 0, 0, 0);
                        if (dt < fromDt) return false;
                    }

                    if (dateUntil) {
                        const [y, m, d] = dateUntil.split('-').map(Number);
                        const untilDt = new Date(y, m - 1, d, 23, 59, 59, 999);
                        if (dt > untilDt) return false;
                    }

                    return true;
                },

                getFilteredOrdersList() {
                    const allOrders = Object.values(window.__SM_ORDERS__ || {});
                    const q = (this.searchOrder || '').toLowerCase().trim();

                    return allOrders.filter(o => {
                        const matchStatus = this.orderFilter === 'semua'
                            || (this.orderFilter === 'verifikasi' && o.payment_status === 'waiting_verification' && o.status !== 'dibatalkan')
                            || o.status === this.orderFilter;

                        const matchSearch = !q || String(o.order_haystack || '').includes(q);
                        const matchTime = this.matchTimeFilter(o.date, this.orderTimeFilter, this.orderDateFrom, this.orderDateUntil);

                        return matchStatus && matchSearch && matchTime;
                    });
                },

                getFilteredHistoryList() {
                    const allOrders = Object.values(window.__SM_ORDERS__ || {});
                    const finalized = allOrders.filter(o => ['selesai', 'dibatalkan'].includes(o.status));
                    const q = (this.searchHistory || '').toLowerCase().trim();

                    return finalized.filter(o => {
                        const matchStatus = this.historyFilter === 'semua' || o.status === this.historyFilter;
                        const matchSearch = !q || String(o.history_haystack || '').includes(q);
                        const matchTime = this.matchTimeFilter(o.updated_date || o.date, this.historyTimeFilter, this.historyDateFrom, this.historyDateUntil);

                        return matchStatus && matchSearch && matchTime;
                    });
                },

                sumOrdersNominal(list) {
                    return (list || []).reduce((acc, item) => acc + Number(item.total || 0), 0);
                },

                openConfirm({ title, message, confirmText = 'Ya, Lanjutkan', variant = 'danger', formId, onCancel = null }) {
                    this.confirmDialog = {
                        open: true,
                        title,
                        message,
                        confirmText,
                        variant,
                        formId,
                        onCancel
                    };
                },

                closeConfirm() {
                    if (typeof this.confirmDialog.onCancel === 'function') {
                        this.confirmDialog.onCancel();
                    }
                    this.confirmDialog.open = false;
                    this.confirmDialog.onCancel = null;
                },

                executeConfirm() {
                    const targetForm = document.getElementById(this.confirmDialog.formId);
                    this.confirmDialog.open = false;
                    this.confirmDialog.onCancel = null;
                    if (targetForm) {
                        if (this.confirmDialog.formId === 'admin-logout-form') {
                            sessionStorage.removeItem('sm_admin_tab');
                        }
                        targetForm.submit();
                    }
                },

                handleQuickStatusChange(selectEl, orderId, currentStatus) {
                    const nextVal = selectEl.value;
                    if (nextVal === 'dibatalkan') {
                        this.openConfirm({
                            title: 'Batalkan Pesanan Ini?',
                            message: 'Pesanan akan dibatalkan dan stok varian produk akan otomatis dikembalikan ke etalase.',
                            confirmText: 'Ya, Batalkan Pesanan',
                            variant: 'danger',
                            formId: 'quick-status-form-' + orderId,
                            onCancel: () => {
                                selectEl.value = currentStatus;
                            }
                        });
                        return;
                    }
                    selectEl.form.submit();
                },

                openOrderModal(orderId) {
                    const data = window.__SM_ORDERS__[orderId];
                    if (data) {
                        this.rejectFormOpen = false;
                        this.rejectReasonInput = data.payment_rejected_reason || '';
                        this.selectedOrder = JSON.parse(JSON.stringify(data));
                    }
                },

                openProofLightbox(url, code, orderId = null) {
                    if (!url) return;
                    this.proofLightbox = {
                        open: true,
                        url: url,
                        code: code || '',
                        orderId: orderId
                    };
                },

                copyText(text, label = 'Tersalin ke clipboard') {
                    if (!text || text === '-') return;
                    navigator.clipboard.writeText(text);
                    this.showToast(label);
                },

                copyShippingInfo(order) {
                    if (!order) return;
                    const itemsText = (order.items || []).map(i => `- ${i.name} (${i.size}${i.color && i.color !== '-' ? '/' + i.color : ''}) x${i.qty}`).join('\n');
                    const formatted = [
                        `ORDER #${order.code} (${order.shipping_method})`,
                        `Penerima: ${order.customer}`,
                        `No. HP: ${order.phone}`,
                        `Alamat: ${order.address}${order.city && order.city !== '-' ? ', ' + order.city : ''}`,
                        itemsText ? `Item:\n${itemsText}` : ''
                    ].filter(Boolean).join('\n');
                    this.copyText(formatted, 'Format alamat & resi berhasil disalin');
                },

                exportOrdersCsv(onlyFinalized = false) {
                    const filtered = onlyFinalized
                        ? this.getFilteredHistoryList()
                        : this.getFilteredOrdersList();

                    if (filtered.length === 0) {
                        this.showToast('Tidak ada data transaksi sesuai filter untuk diekspor');
                        return;
                    }

                    const cleanCell = (val) => String(val ?? '-')
                        .replace(/[\r\n]+/g, ' ')
                        .replace(/\s+/g, ' ')
                        .trim() || '-';

                    const formatPhoneForExcel = (phone) => {
                        const cleaned = cleanCell(phone);
                        if (cleaned === '-') return '-';
                        return `="${cleaned.replace(/"/g, '""')}"`;
                    };

                    const formatPaymentStatusLabel = (status) => {
                        const st = String(status || 'unpaid').toLowerCase();
                        if (st === 'paid') return 'Lunas';
                        if (st === 'waiting_verification') return 'Menunggu Verifikasi';
                        if (st === 'rejected') return 'Bukti Ditolak';
                        return 'Belum Bayar';
                    };

                    const formatNominal = (amount) => {
                        const num = Math.round(Number(amount || 0));
                        return num.toLocaleString('id-ID');
                    };

                    const extractCityFromAddress = (addressText) => {
                        if (!addressText || addressText === '-') return '-';

                        const knownRegions = [
                            'NTB', 'NTT', 'Malang', 'Bojonegoro', 'Surabaya', 'Batu', 'Kediri',
                            'Blitar', 'Jember', 'Banyuwangi', 'Sidoarjo', 'Pasuruan', 'Probolinggo',
                            'Madiun', 'Mojokerto', 'Tuban', 'Lamongan', 'Nganjuk', 'Tulungagung',
                            'Trenggalek', 'Ponorogo', 'Ngawi', 'Magetan', 'Pacitan', 'Lumajang',
                            'Bondowoso', 'Situbondo', 'Gresik', 'Bangkalan', 'Sampang', 'Pamekasan',
                            'Sumenep', 'Jakarta', 'Bandung', 'Yogyakarta', 'Semarang', 'Solo',
                            'Surakarta', 'Denpasar', 'Bali', 'Medan', 'Makassar', 'Balikpapan'
                        ];

                        for (const region of knownRegions) {
                            const regex = new RegExp(`\\b${region.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}\\b`, 'i');
                            if (regex.test(addressText)) {
                                return region === region.toUpperCase() ? region.toUpperCase() : region;
                            }
                        }

                        const regencyMatch = addressText.match(/\b(?:kota|kabupaten|kab\.?)\s+([a-zA-Z\s]{3,25})(?:,|;|\d|$)/i);
                        if (regencyMatch && regencyMatch[1]) {
                            return regencyMatch[1].trim();
                        }

                        return '-';
                    };

                    const resolveCityAndAddress = (order) => {
                        const rawAddress = cleanCell(order.address);
                        const rawCity = cleanCell(order.city);
                        const shipRaw = String(order.shipping_raw || '').toLowerCase();
                        const shipMethod = String(order.shipping_method || '').toUpperCase();

                        const isPickup = shipRaw === 'pickup'
                            || shipMethod.includes('STORE')
                            || /\bambil\s*langsung\b/i.test(rawAddress);

                        if (isPickup) {
                            const kota = rawCity !== '-' ? rawCity : 'Malang';
                            const isDefaultPickupText = rawAddress === '-'
                                || /^ambil\s*langsung(\s*di\s*store(\s*malang)?)?$/i.test(rawAddress);

                            const pengambilan = isDefaultPickupText
                                ? 'Ambil Langsung di Store Malang'
                                : `Ambil Langsung di Store Malang (${rawAddress})`;

                            return {
                                kota,
                                alamatPengambilan: pengambilan
                            };
                        }

                        const resolvedCity = rawCity !== '-' ? rawCity : extractCityFromAddress(rawAddress);

                        return {
                            kota: resolvedCity,
                            alamatPengambilan: rawAddress
                        };
                    };

                    const headers = [
                        'Kode Order',
                        'Tanggal Order',
                        'Tanggal Update',
                        'Pelanggan',
                        'Telepon',
                        'Kota',
                        'Alamat / Pengambilan',
                        'Metode Bayar',
                        'Status Bayar',
                        'Ekspedisi',
                        'No Resi',
                        'Status Pesanan',
                        'Total Pembayaran (Rp)'
                    ];

                    const escapeRawCsv = (val) => '"' + cleanCell(val).replace(/"/g, '""') + '"';

                    const rows = filtered.map(o => {
                        const { kota, alamatPengambilan } = resolveCityAndAddress(o);
                        const phoneCell = formatPhoneForExcel(o.phone);
                        const formattedPhone = phoneCell.startsWith('="')
                            ? '"' + phoneCell.replace(/"/g, '""') + '"'
                            : escapeRawCsv(phoneCell);

                        const cells = [
                            escapeRawCsv(o.code),
                            escapeRawCsv(o.date || '-'),
                            escapeRawCsv(o.updated_date || o.date || '-'),
                            escapeRawCsv(o.customer),
                            formattedPhone,
                            escapeRawCsv(kota),
                            escapeRawCsv(alamatPengambilan),
                            escapeRawCsv(String(o.payment || '-').toUpperCase()),
                            escapeRawCsv(formatPaymentStatusLabel(o.payment_status)),
                            escapeRawCsv(String(o.shipping_method || '-').toUpperCase()),
                            escapeRawCsv(o.tracking_number || '-'),
                            escapeRawCsv(String(o.status || '-').toUpperCase()),
                            escapeRawCsv(formatNominal(o.total))
                        ];

                        return cells.join(';');
                    });

                    const csvContent = '\uFEFFsep=;\n' + [headers.map(escapeRawCsv).join(';'), ...rows].join('\n');
                    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                    const url = URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    const dateTag = new Date().toISOString().slice(0, 10);
                    link.href = url;
                    link.download = `SportMania_${onlyFinalized ? 'Riwayat_Transaksi' : 'Laporan_Pesanan'}_${dateTag}.csv`;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    URL.revokeObjectURL(url);
                    this.showToast(`Berhasil mengunduh ${filtered.length} data pesanan sesuai filter (CSV)`);
                },

                triggerPrint(orderObj, mode = 'invoice') {
                    if (!orderObj) return;
                    this.printOrder = JSON.parse(JSON.stringify(orderObj));
                    this.printMode = mode;
                    this.$nextTick(() => {
                        setTimeout(() => window.print(), 100);
                    });
                },

                printOrderById(orderId, mode = 'invoice') {
                    const data = window.__SM_ORDERS__[orderId];
                    if (data) {
                        this.triggerPrint(data, mode);
                    }
                }
            }));
        });
    </script>
</body>
</html>