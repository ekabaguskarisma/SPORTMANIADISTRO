<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Sport Mania Distro</title>
    
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
        .tabular-nums { font-variant-numeric: tabular-nums; }
        
        .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 9999px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        @media print {
            @page { margin: 10mm; }
            body { background: #ffffff !important; color: #000000 !important; }
        }
    </style>
</head>

<body class="bg-slate-50/90 text-slate-800 antialiased selection:bg-red-600 selection:text-white" 
      x-data="adminPanel()"
      @afterprint.window="printOrder = null"
      @keydown.escape.window="if (proofLightbox.open) { proofLightbox.open = false; } else if (dashModal.open) { dashModal.open = false; } else if (selectedOrder) { selectedOrder = null; } else if (confirmDialog.open) { confirmDialog.open = false; }">

@php
    $products = $products ?? (\Illuminate\Support\Facades\Schema::hasTable('products')
        ? \App\Models\Product::with(['category', 'variants'])->latest()->get()
        : collect());

    $customers = $customers ?? \App\Models\User::where(function ($q) {
        $q->whereNotIn('role', ['admin', 'owner'])->orWhereNull('role');
    })->latest()->get();

    $orders = $orders ?? (\Illuminate\Support\Facades\Schema::hasTable('orders')
        ? \App\Models\Order::with(['items.product', 'user'])->latest()->get()
        : collect());
        
    $promos = $promos ?? (\Illuminate\Support\Facades\Schema::hasTable('promo_banners')
        ? \App\Models\PromoBanner::orderBy('placement')->orderBy('sort_order')->latest()->get()
        : collect());

    $vouchers = $vouchers ?? (\Illuminate\Support\Facades\Schema::hasTable('vouchers')
        ? \App\Models\Voucher::latest()->get()
        : collect());

    $usersById = \App\Models\User::pluck('name', 'id');

    $availableCategories = $products
        ->map(fn($p) => $p->category)
        ->filter()
        ->unique('id')
        ->values();

    // Data produk siap pakai untuk Modul Kasir (POS)
    $posProductsList = $products->map(function ($p) {
        $imgUrl = filled($p->image)
            ? (filter_var($p->image, FILTER_VALIDATE_URL) ? $p->image : asset('storage/' . ltrim($p->image, '/')))
            : null;
        $effectivePrice = ($p->discount_price !== null && (float) $p->discount_price > 0)
            ? (float) $p->discount_price
            : (float) ($p->price ?? 0);
        $hasVariants = $p->variants && $p->variants->count() > 0;

        return [
            'id'            => $p->id,
            'name'          => $p->name,
            'category_slug' => strtolower(optional($p->category)->slug ?? 'umum'),
            'category_name' => optional($p->category)->name ?? 'Umum',
            'price'         => $effectivePrice,
            'original_price'=> (float) ($p->price ?? 0),
            'image'         => $imgUrl,
            'has_variants'  => $hasVariants,
            'stock'         => $hasVariants ? (int) $p->variants->sum('stock') : 0,
            'variants'      => $hasVariants ? $p->variants->map(fn($v) => [
                'id'    => $v->id,
                'size'  => filled($v->size) ? $v->size : 'All Size',
                'color' => filled($v->color) ? $v->color : '-',
                'stock' => (int) ($v->stock ?? 0),
            ])->values()->toArray() : [],
        ];
    })->values();

    $hasPosRoute = \Illuminate\Support\Facades\Route::has('admin.pos.store');
    $posActionUrl = $hasPosRoute ? route('admin.pos.store') : url('/admin/pos');

    $totalStockAll = $products->sum(fn($p) => ($p->variants && $p->variants->count() > 0) ? $p->variants->sum('stock') : ($p->stock ?? 0));
    $lowStockProducts = $products->filter(function ($p) {
        $hasVariants = $p->variants && $p->variants->count() > 0;
        $total = $hasVariants ? $p->variants->sum('stock') : ($p->stock ?? 0);
        $hasCriticalVariant = $hasVariants && $p->variants->contains(fn($v) => (int) $v->stock <= 3);
        return $total <= 10 || $hasCriticalVariant;
    });

    $resolvePaymentStatus = function ($order) {
        $statusRaw = strtolower($order->status ?? 'pending');
        $statusNorm = $statusRaw === 'batal' ? 'dibatalkan' : $statusRaw;
        $proofRaw = $order->payment_proof ?? null;
        $hasProof = filled($proofRaw);
        $payStatus = strtolower($order->payment_status ?? '');

        if ($payStatus === 'paid' || in_array($statusNorm, ['diproses', 'dikirim', 'selesai'], true)) {
            return 'paid';
        }
        if ($payStatus === 'rejected') {
            return 'rejected';
        }
        if ($hasProof || $payStatus === 'waiting_verification') {
            return 'waiting_verification';
        }
        return 'unpaid';
    };

    $statusCounts = [
        'pending'    => $orders->filter(fn($o) => strtolower($o->status ?? 'pending') === 'pending')->count(),
        'diproses'   => $orders->filter(fn($o) => strtolower($o->status ?? '') === 'diproses')->count(),
        'dikirim'    => $orders->filter(fn($o) => strtolower($o->status ?? '') === 'dikirim')->count(),
        'selesai'    => $orders->filter(fn($o) => strtolower($o->status ?? '') === 'selesai')->count(),
        'dibatalkan' => $orders->filter(fn($o) => in_array(strtolower($o->status ?? ''), ['dibatalkan', 'batal'], true))->count(),
    ];

    $pendingOrdersCount = $statusCounts['pending'] + $statusCounts['diproses'];

    $waitingVerificationOrders = $orders->filter(function ($o) use ($resolvePaymentStatus) {
        $st = strtolower($o->status ?? 'pending');
        if (in_array($st, ['dibatalkan', 'batal'], true)) return false;
        return $resolvePaymentStatus($o) === 'waiting_verification';
    });
    $waitingVerificationCount = $waitingVerificationOrders->count();

    $completedOrders = $orders->filter(fn($o) => strtolower($o->status ?? '') === 'selesai');
    $activeOrders = $orders->filter(fn($o) => in_array(strtolower($o->status ?? 'pending'), ['pending', 'diproses', 'dikirim'], true));
    $finalizedOrders = $orders->filter(fn($o) => in_array(strtolower($o->status ?? ''), ['selesai', 'dibatalkan', 'batal'], true));

    $hasVerifyRoute = \Illuminate\Support\Facades\Route::has('admin.orders.verify-payment');

    $customersList = $customers->map(function ($cust) use ($orders) {
        return [
            'id'            => $cust->id,
            'name'          => $cust->name,
            'email'         => $cust->email,
            'created_year'  => optional($cust->created_at)->year ?? (int) date('Y'),
            'created_month' => optional($cust->created_at)->month ?? (int) date('n'),
            'joined_label'  => optional($cust->created_at)->format('d M Y') ?? '-',
            'total_orders'  => $orders->where('user_id', $cust->id)->count(),
        ];
    })->values();

    $ordersMap = $orders->mapWithKeys(function ($order) use ($usersById, $resolvePaymentStatus, $hasVerifyRoute) {
        $code = filled($order->order_number ?? null)
            ? $order->order_number
            : (filled($order->invoice_number ?? null) ? $order->invoice_number : ('ORD-' . str_pad($order->id, 4, '0', STR_PAD_LEFT)));

        $customerName = filled($order->recipient_name ?? null) ? $order->recipient_name
            : (filled($order->customer_name ?? null) ? $order->customer_name
            : (filled($order->name ?? null) ? $order->name
            : (optional($order->user)->name ?? $usersById[$order->user_id ?? 0] ?? 'Pelanggan Setia')));

        $phoneRaw = filled($order->phone ?? null) ? $order->phone
            : (filled($order->whatsapp ?? null) ? $order->whatsapp
            : (filled($order->no_hp ?? null) ? $order->no_hp : (optional($order->user)->phone ?? '-')));

        $waClean = preg_replace('/[^0-9]/', '', $phoneRaw);
        if (str_starts_with($waClean, '0')) {
            $waClean = '62' . substr($waClean, 1);
        }

        $cityVal = filled($order->city ?? null) ? $order->city
            : (filled($order->kota ?? null) ? $order->kota : '-');

        $addressVal = filled($order->shipping_address ?? null) ? $order->shipping_address
            : (filled($order->address ?? null) ? $order->address
            : (filled($order->alamat ?? null) ? $order->alamat : 'Ambil langsung di Store Malang'));

        $paymentVal = filled($order->payment_method ?? null) ? $order->payment_method
            : (filled($order->metode_pembayaran ?? null) ? $order->metode_pembayaran : 'TRANSFER');

        $shippingRaw = strtolower($order->shipping_method ?? '');
        $shippingLabel = match ($shippingRaw) {
            'pickup'  => 'AMBIL DI STORE',
            'pos'     => 'KASIR TOKO (POS)',
            'jne'     => 'JNE REG',
            'sicepat' => 'SICEPAT',
            default   => filled($shippingRaw) ? strtoupper($shippingRaw) : 'KURIR',
        };

        $resiVal = filled($order->tracking_number ?? null) ? $order->tracking_number
            : (filled($order->no_resi ?? null) ? $order->no_resi
            : (filled($order->resi ?? null) ? $order->resi : ''));

        $notesVal = filled($order->notes ?? null) ? $order->notes
            : (filled($order->catatan ?? null) ? $order->catatan : '-');

        $statusRaw = strtolower($order->status ?? 'pending');
        $statusNormalized = $statusRaw === 'batal' ? 'dibatalkan' : $statusRaw;

        $paymentStatus = $resolvePaymentStatus($order);
        $proofRaw = $order->payment_proof ?? null;
        $proofUrl = filled($proofRaw)
            ? (filter_var($proofRaw, FILTER_VALIDATE_URL) ? $proofRaw : asset('storage/' . ltrim($proofRaw, '/')))
            : null;

        $verifiedAtFormatted = filled($order->payment_verified_at ?? null)
            ? \Illuminate\Support\Carbon::parse($order->payment_verified_at)->format('d M Y, H:i')
            : null;

        $subtotalPrice = (float) ($order->subtotal ?? 0);
        $shippingCost = (float) ($order->shipping_cost ?? 0);
        $totalOrderPrice = (float) ($order->total_price ?? $order->total_amount ?? $order->total ?? 0);

        $createdDateFormatted = optional($order->created_at)->format('d M Y, H:i') ?? '-';
        $createdDateDisplay = optional($order->created_at)->format('d F Y, H:i') ?? '-';
        $updatedDateFormatted = optional($order->updated_at ?? $order->created_at)->format('d M Y, H:i') ?? '-';
        $updatedDateDisplay = optional($order->updated_at ?? $order->created_at)->format('d F Y, H:i') ?? '-';

        $firstItem = $order->items ? $order->items->first() : null;
        $firstItemName = $firstItem
            ? (filled($firstItem->product_name ?? null) ? $firstItem->product_name : (optional($firstItem->product)->name ?? 'Produk Terhapus'))
            : '';
        $extraItemCount = ($order->items && $order->items->count() > 1) ? (' +' . ($order->items->count() - 1) . ' produk') : '';

        $orderHaystack = strtolower('#' . $code . ' ' . $customerName . ' ' . $resiVal . ' ' . strtoupper($paymentVal) . ' ' . $createdDateDisplay . ' ' . $firstItemName);
        $historyHaystack = strtolower('#' . $code . ' ' . $customerName . ' ' . $updatedDateDisplay . ' ' . $createdDateDisplay);

        $waText = rawurlencode("Halo Kak {$customerName}, kami dari Admin Sport Mania Distro mengonfirmasi pesanan *#{$code}* (Total: Rp " . number_format($totalOrderPrice, 0, ',', '.') . ") dengan status *" . strtoupper($statusNormalized) . "*.");

        return [
            $order->id => [
                'id'                      => $order->id,
                'user_id'                 => $order->user_id,
                'code'                    => $code,
                'customer'                => $customerName,
                'phone'                   => $phoneRaw,
                'wa_link'                 => strlen($waClean) >= 9 ? 'https://wa.me/' . $waClean . '?text=' . $waText : null,
                'city'                    => $cityVal,
                'address'                 => $addressVal,
                'payment'                 => strtoupper($paymentVal),
                'payment_raw'             => strtolower($paymentVal),
                'payment_status'          => $paymentStatus,
                'payment_proof_url'       => $proofUrl,
                'payment_rejected_reason' => $order->payment_rejected_reason ?? '',
                'payment_verified_at'     => $verifiedAtFormatted,
                'shipping_raw'            => $shippingRaw,
                'shipping_method'         => $shippingLabel,
                'tracking_number'         => $resiVal,
                'notes'                   => $notesVal,
                'status'                  => $statusNormalized,
                'created_iso'             => optional($order->created_at)->format('Y-m-d') ?? null,
                'created_year'            => optional($order->created_at)->year ?? (int) date('Y'),
                'created_month'           => optional($order->created_at)->month ?? (int) date('n'),
                'created_day'             => optional($order->created_at)->day ?? (int) date('j'),
                'is_today'                => optional($order->created_at)->isToday() ?? false,
                'date'                    => $createdDateFormatted,
                'date_display'            => $createdDateDisplay,
                'updated_date'            => $updatedDateFormatted,
                'updated_date_display'    => $updatedDateDisplay,
                'first_item_summary'      => trim($firstItemName . $extraItemCount),
                'order_haystack'          => $orderHaystack,
                'history_haystack'        => $historyHaystack,
                'subtotal'                => $subtotalPrice,
                'shipping_cost'           => $shippingCost,
                'total'                   => $totalOrderPrice,
                'update_url'              => route('admin.orders.update', $order->id),
                'verify_url'              => $hasVerifyRoute ? route('admin.orders.verify-payment', $order->id) : route('admin.orders.update', $order->id),
                'has_verify_route'        => $hasVerifyRoute,
                'items'                   => $order->items ? $order->items->map(function ($item) {
                    $qty = (int) ($item->quantity ?? $item->qty ?? 1);
                    $price = (float) ($item->price ?? 0);
                    $img = optional($item->product)->image;
                    $imgUrl = filled($img)
                        ? (filter_var($img, FILTER_VALIDATE_URL) ? $img : asset('storage/' . ltrim($img, '/')))
                        : null;
                    $itemName = filled($item->product_name ?? null)
                        ? $item->product_name
                        : (optional($item->product)->name ?? 'Produk Terhapus');
                    $itemSize = $item->variant_size ?? $item->size ?? null;
                    $itemColor = $item->variant_color ?? $item->color ?? null;

                    return [
                        'name'     => $itemName,
                        'image'    => $imgUrl,
                        'size'     => filled($itemSize) ? $itemSize : 'All Size',
                        'color'    => filled($itemColor) ? $itemColor : '-',
                        'qty'      => $qty,
                        'price'    => $price,
                        'subtotal' => $price * $qty,
                    ];
                })->values()->toArray() : [],
            ],
        ];
    });
@endphp

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

            // State Rekap Bulanan
            recapMonth: new Date().getMonth() + 1,
            recapYear: new Date().getFullYear(),
            recapBasis: 'realized',

            // State Filter Bulan & Modal di Ringkasan Panel
            dashPeriodMode: 'all', // 'all' atau 'monthly'
            dashMonth: new Date().getMonth() + 1,
            dashYear: new Date().getFullYear(),
            dashModal: {
                open: false,
                type: '' // 'orders' | 'revenue' | 'products' | 'customers'
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
                // Default varian pertama untuk setiap produk di kasir
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
                    if (this.activeTab === 'rekap') this.$nextTick(() => this.renderRecapCharts());
                });
                this.$watch('recapYear', () => {
                    if (this.activeTab === 'rekap') this.$nextTick(() => this.renderRecapCharts());
                });
                this.$watch('recapBasis', () => {
                    if (this.activeTab === 'rekap') this.$nextTick(() => this.renderRecapCharts());
                });

                if (this.activeTab === 'rekap') {
                    this.$nextTick(() => setTimeout(() => this.renderRecapCharts(), 120));
                }

                // Auto cetak struk POS jika baru saja sukses checkout kasir
                @if(session('pos_print_order_id'))
                    this.$nextTick(() => {
                        setTimeout(() => this.printOrderById({{ (int) session('pos_print_order_id') }}, 'struk'), 300);
                    });
                @endif
            },

            setTab(tab) {
                this.activeTab = tab;
                sessionStorage.setItem('sm_admin_tab', tab);
                this.sidebarOpen = false;
            },

            showToast(msg) {
                this.toastMessage = msg;
                setTimeout(() => { this.toastMessage = ''; }, 2600);
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
            // ENGINE REKAP BULANAN & GRAFIK
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
                        const sub = Number(it.subtotal || 0);
                        totalItemsSold += q;

                        if (!productMap[it.name]) {
                            productMap[it.name] = { name: it.name, image: it.image, qty: 0, revenue: 0 };
                        }
                        productMap[it.name].qty += q;
                        productMap[it.name].revenue += sub;
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
                            revenue: dayRev
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
                    const countData = [];

                    for (let d = 1; d <= summary.daysInMonth; d++) {
                        labels.push(String(d).padStart(2, '0'));
                        const dayOrders = all.filter(o =>
                            Number(o.created_year) === y &&
                            Number(o.created_month) === m &&
                            Number(o.created_day) === d &&
                            this.isOrderCountedInRecap(o)
                        );
                        revData.push(dayOrders.reduce((s, o) => s + Number(o.total || 0), 0));
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

                const headers = ['Tanggal', 'Total Order Masuk', 'Order Valid / Lunas', 'Order Dibatalkan', 'Total Produk Terjual (Pcs)', 'Total Omzet (Rp)'];
                const esc = (v) => '"' + String(v ?? '-').replace(/"/g, '""') + '"';

                const rows = summary.dailyRows.map(r => [
                    esc(r.dateLabel),
                    esc(r.totalTrx),
                    esc(r.validTrx),
                    esc(r.cancelTrx),
                    esc(r.itemsSold),
                    esc(Math.round(r.revenue).toLocaleString('id-ID'))
                ].join(';'));

                rows.push([
                    esc('TOTAL BULAN ' + this.getMonthName(this.recapMonth).toUpperCase() + ' ' + this.recapYear),
                    esc(summary.totalAllOrders),
                    esc(summary.validCount),
                    esc(summary.cancelledCount),
                    esc(summary.totalItemsSold),
                    esc(Math.round(summary.revenue).toLocaleString('id-ID'))
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

    <!-- ========================================================= -->
    <!-- FLOATING TOAST NOTIFICATION (SUKSES / ERROR / VALIDASI)   -->
    <!-- ========================================================= -->
    <div class="fixed top-5 right-5 z-[70] flex flex-col gap-2.5 max-w-sm w-full pointer-events-none print:hidden">
        @if(session('success'))
            <div x-data="{ show: true }"
                 x-init="setTimeout(() => show = false, 4500)"
                 x-show="show"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-x-6"
                 x-transition:enter-end="opacity-100 translate-x-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-x-0"
                 x-transition:leave-end="opacity-0 translate-x-6"
                 class="pointer-events-auto bg-slate-950 text-white border border-slate-800 rounded-2xl p-4 shadow-2xl flex items-start gap-3">
                <div class="w-8 h-8 rounded-xl bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-[11px] font-black uppercase tracking-wider text-emerald-400">Berhasil</p>
                    <p class="text-xs text-slate-200 font-medium mt-0.5 leading-relaxed">{{ session('success') }}</p>
                </div>
                <button @click="show = false" class="text-slate-500 hover:text-white text-xs p-1">✕</button>
            </div>
        @endif

        @if(session('error') || $errors->any())
            <div x-data="{ show: true }"
                 x-init="setTimeout(() => show = false, 5500)"
                 x-show="show"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-x-6"
                 x-transition:enter-end="opacity-100 translate-x-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-x-0"
                 x-transition:leave-end="opacity-0 translate-x-6"
                 class="pointer-events-auto bg-slate-950 text-white border border-rose-500/40 rounded-2xl p-4 shadow-2xl flex items-start gap-3">
                <div class="w-8 h-8 rounded-xl bg-rose-500/20 text-rose-400 border border-rose-500/30 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-[11px] font-black uppercase tracking-wider text-rose-400">Terjadi Kendala</p>
                    <p class="text-xs text-slate-200 font-medium mt-0.5 leading-relaxed">
                        {{ session('error') ?? $errors->first() }}
                    </p>
                </div>
                <button @click="show = false" class="text-slate-500 hover:text-white text-xs p-1">✕</button>
            </div>
        @endif

        <div x-show="toastMessage !== ''"
             x-cloak
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 translate-y-2"
             class="pointer-events-auto bg-slate-900 text-white border border-slate-700 rounded-2xl px-4 py-3 shadow-xl flex items-center gap-2.5 self-end">
            <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
            <span class="text-xs font-bold" x-text="toastMessage"></span>
        </div>
    </div>

    <!-- ========================================================= -->
    <!-- AREA TAMPILAN ADMIN DASHBOARD (TERSEMBUNYI SAAT DICETAK)  -->
    <!-- ========================================================= -->
    <div class="flex h-screen overflow-hidden print:hidden">

        <!-- Sidebar (Desktop & Mobile) -->
        <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'" 
               class="fixed inset-y-0 left-0 z-50 w-64 bg-slate-950 text-slate-400 transition-transform duration-300 ease-in-out lg:static lg:translate-x-0 flex flex-col border-r border-slate-800/90 shadow-2xl shrink-0">
            
            <!-- Logo Area -->
            <div class="flex items-center justify-between px-5 h-20 border-b border-slate-800/80 bg-slate-950 shrink-0">
                <a href="/" class="flex items-center gap-3 group">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-red-600 to-rose-700 flex items-center justify-center text-white font-black italic text-sm tracking-tighter shadow-lg shadow-red-600/30 ring-1 ring-white/15 shrink-0">
                        SM
                    </div>
                    <div class="leading-none">
                        <span class="block text-lg font-black tracking-tight uppercase italic">
                            <span class="text-red-500">SPORT</span><span class="text-white">MANIA</span>
                        </span>
                        <span class="block text-[9px] font-bold uppercase tracking-[0.2em] text-slate-500 mt-1">
                            Distro Admin
                        </span>
                    </div>
                </a>
                <button @click="sidebarOpen = false" class="lg:hidden p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-900">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <!-- Navigation Links -->
            <nav class="flex-1 px-3.5 py-6 space-y-1.5 overflow-y-auto custom-scrollbar">
                <p class="px-3.5 pb-2 text-[10px] font-bold uppercase tracking-widest text-slate-500">Menu Utama</p>

                <button @click="setTab('dashboard')" 
                        :class="activeTab === 'dashboard' ? 'bg-gradient-to-r from-red-600 to-rose-600 text-white shadow-lg shadow-red-600/25' : 'hover:bg-slate-900 hover:text-slate-100'"
                        class="flex items-center w-full gap-3 px-3.5 py-2.5 rounded-xl font-semibold text-sm transition-all duration-200">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                    <span>Ringkasan</span>
                </button>

                <!-- MENU BARU: KASIR (POS OFFLINE TOKO) -->
                <button @click="setTab('kasir')" 
                        :class="activeTab === 'kasir' ? 'bg-gradient-to-r from-red-600 to-rose-600 text-white shadow-lg shadow-red-600/25' : 'hover:bg-slate-900 hover:text-slate-100'"
                        class="flex items-center justify-between w-full px-3.5 py-2.5 rounded-xl font-semibold text-sm transition-all duration-200">
                    <span class="flex items-center gap-3">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                        <span>Kasir (POS)</span>
                    </span>
                    <span :class="activeTab === 'kasir' ? 'bg-white/20 text-white' : 'bg-red-500/20 text-red-400'" class="text-[9px] font-black uppercase px-2 py-0.5 rounded-full">Toko</span>
                </button>

                <button @click="setTab('produk')" 
                        :class="activeTab === 'produk' ? 'bg-gradient-to-r from-red-600 to-rose-600 text-white shadow-lg shadow-red-600/25' : 'hover:bg-slate-900 hover:text-slate-100'"
                        class="flex items-center justify-between w-full px-3.5 py-2.5 rounded-xl font-semibold text-sm transition-all duration-200">
                    <span class="flex items-center gap-3">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                        Manajemen Produk
                    </span>
                    <span :class="activeTab === 'produk' ? 'bg-white/20 text-white' : 'bg-slate-800/90 text-slate-300'" class="text-[10px] font-bold px-2 py-0.5 rounded-full tabular-nums">{{ $products->count() }}</span>
                </button>

                <button @click="setTab('promo')" 
                        :class="activeTab === 'promo' ? 'bg-gradient-to-r from-red-600 to-rose-600 text-white shadow-lg shadow-red-600/25' : 'hover:bg-slate-900 hover:text-slate-100'"
                        class="flex items-center justify-between w-full px-3.5 py-2.5 rounded-xl font-semibold text-sm transition-all duration-200">
                    <span class="flex items-center gap-3">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path></svg>
                        Promo & Banner
                    </span>
                    <span :class="activeTab === 'promo' ? 'bg-white/20 text-white' : 'bg-slate-800/90 text-slate-300'" class="text-[10px] font-bold px-2 py-0.5 rounded-full tabular-nums">{{ $promos->count() }}</span>
                </button>

                <button @click="setTab('voucher')" 
                        :class="activeTab === 'voucher' ? 'bg-gradient-to-r from-red-600 to-rose-600 text-white shadow-lg shadow-red-600/25' : 'hover:bg-slate-900 hover:text-slate-100'"
                        class="flex items-center justify-between w-full px-3.5 py-2.5 rounded-xl font-semibold text-sm transition-all duration-200">
                    <span class="flex items-center gap-3">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"></path></svg>
                        Kode Voucher
                    </span>
                    <span :class="activeTab === 'voucher' ? 'bg-white/20 text-white' : 'bg-slate-800/90 text-slate-300'" class="text-[10px] font-bold px-2 py-0.5 rounded-full tabular-nums">{{ $vouchers->count() }}</span>
                </button>

                <button @click="setTab('pesanan')" 
                        :class="activeTab === 'pesanan' ? 'bg-gradient-to-r from-red-600 to-rose-600 text-white shadow-lg shadow-red-600/25' : 'hover:bg-slate-900 hover:text-slate-100'"
                        class="flex items-center justify-between w-full px-3.5 py-2.5 rounded-xl font-semibold text-sm transition-all duration-200">
                    <span class="flex items-center gap-3">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                        Kelola Pesanan
                    </span>
                    <div class="flex items-center gap-1">
                        @if($waitingVerificationCount > 0)
                            <span title="{{ $waitingVerificationCount }} Bukti Bayar Perlu Verifikasi"
                                  :class="activeTab === 'pesanan' ? 'bg-rose-200 text-slate-900' : 'bg-rose-600 text-white'"
                                  class="text-[10px] font-black px-1.5 py-0.5 rounded-full tabular-nums animate-pulse">
                                💳{{ $waitingVerificationCount }}
                            </span>
                        @endif
                        @if($pendingOrdersCount > 0)
                            <span :class="activeTab === 'pesanan' ? 'bg-white text-red-600' : 'bg-red-500 text-white'" class="text-[10px] font-black px-2 py-0.5 rounded-full tabular-nums">{{ $pendingOrdersCount }}</span>
                        @endif
                    </div>
                </button>

                <button @click="setTab('pelanggan')" 
                        :class="activeTab === 'pelanggan' ? 'bg-gradient-to-r from-red-600 to-rose-600 text-white shadow-lg shadow-red-600/25' : 'hover:bg-slate-900 hover:text-slate-100'"
                        class="flex items-center justify-between w-full px-3.5 py-2.5 rounded-xl font-semibold text-sm transition-all duration-200">
                    <span class="flex items-center gap-3">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                        Data Pelanggan
                    </span>
                    <span :class="activeTab === 'pelanggan' ? 'bg-white/20 text-white' : 'bg-slate-800/90 text-slate-300'" class="text-[10px] font-bold px-2 py-0.5 rounded-full tabular-nums">{{ $customers->count() }}</span>
                </button>

                <button @click="setTab('riwayat')" 
                        :class="activeTab === 'riwayat' ? 'bg-gradient-to-r from-red-600 to-rose-600 text-white shadow-lg shadow-red-600/25' : 'hover:bg-slate-900 hover:text-slate-100'"
                        class="flex items-center justify-between w-full px-3.5 py-2.5 rounded-xl font-semibold text-sm transition-all duration-200">
                    <span class="flex items-center gap-3">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span>Riwayat & Log</span>
                    </span>
                    <span :class="activeTab === 'riwayat' ? 'bg-white/20 text-white' : 'bg-slate-800/90 text-slate-300'" class="text-[10px] font-bold px-2 py-0.5 rounded-full tabular-nums">{{ $finalizedOrders->count() }}</span>
                </button>

                <button @click="setTab('rekap')" 
                        :class="activeTab === 'rekap' ? 'bg-gradient-to-r from-red-600 to-rose-600 text-white shadow-lg shadow-red-600/25' : 'hover:bg-slate-900 hover:text-slate-100'"
                        class="flex items-center justify-between w-full px-3.5 py-2.5 rounded-xl font-semibold text-sm transition-all duration-200">
                    <span class="flex items-center gap-3">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                        <span>Rekap Bulanan</span>
                    </span>
                    <span :class="activeTab === 'rekap' ? 'bg-white/20 text-white' : 'bg-emerald-500/15 text-emerald-400'" class="text-[9px] font-black uppercase px-2 py-0.5 rounded-full">Grafik</span>
                </button>
            </nav>

            <!-- Bottom Profile / Storefront / Logout -->
            <div class="p-4 border-t border-slate-800/80 bg-slate-950 space-y-2">
                <a href="/" target="_blank" class="sm:hidden flex items-center justify-center w-full gap-2 px-4 py-2 rounded-xl font-bold text-xs text-slate-300 bg-slate-900 hover:bg-slate-800 border border-slate-800 transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                    Lihat Web Toko
                </a>

                <form id="admin-logout-form" method="POST" action="{{ route('logout') }}" class="hidden">
                    @csrf
                </form>
                <button type="button"
                        @click="openConfirm({
                            title: 'Keluar dari Admin Panel?',
                            message: 'Sesi login Anda akan diakhiri dan Anda harus masuk kembali untuk mengelola toko.',
                            confirmText: 'Ya, Log Out',
                            variant: 'danger',
                            formId: 'admin-logout-form'
                        })"
                        class="flex items-center justify-center w-full gap-2.5 px-4 py-2.5 rounded-xl font-bold text-xs uppercase tracking-wider text-rose-400 bg-rose-500/10 hover:bg-rose-600 hover:text-white border border-rose-500/20 transition-all duration-200">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                    Log Out
                </button>
            </div>
        </aside>

        <!-- Overlay Mobile -->
        <div x-show="sidebarOpen" @click="sidebarOpen = false" class="fixed inset-0 z-40 bg-slate-950/60 backdrop-blur-sm lg:hidden" x-cloak></div>

        <!-- Main Content Area -->
        <main class="flex-1 flex flex-col h-screen overflow-hidden min-w-0">
            
            <!-- Top Header -->
            <header class="h-20 bg-white/90 backdrop-blur-md border-b border-slate-200/80 flex items-center justify-between px-6 lg:px-8 shrink-0 z-30">
                <div class="flex items-center gap-3.5 min-w-0">
                    <button @click="sidebarOpen = true" class="lg:hidden p-2 rounded-xl text-slate-600 hover:bg-slate-100 focus:outline-none shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    </button>
                    <div class="truncate">
                        <h2 class="text-lg sm:text-xl font-black text-slate-900 tracking-tight leading-tight truncate">
                            <span x-show="activeTab === 'dashboard'">Ringkasan Panel</span>
                            <span x-show="activeTab === 'kasir'" x-cloak>Kasir Toko (Point of Sale)</span>
                            <span x-show="activeTab === 'produk'" x-cloak>Manajemen Produk</span>
                            <span x-show="activeTab === 'promo'" x-cloak>Promo & Banner Beranda</span>
                            <span x-show="activeTab === 'voucher'" x-cloak>Manajemen Kode Voucher</span>
                            <span x-show="activeTab === 'pesanan'" x-cloak>Kelola Pesanan</span>
                            <span x-show="activeTab === 'pelanggan'" x-cloak>Data Pelanggan</span>
                            <span x-show="activeTab === 'riwayat'" x-cloak>Riwayat & Log Toko</span>
                            <span x-show="activeTab === 'rekap'" x-cloak>Rekap & Grafik Bulanan</span>
                        </h2>
                        <p class="text-[11px] text-slate-400 font-medium hidden sm:block mt-0.5">Sport Mania Distro • Store Management System</p>
                    </div>
                </div>
                
                <div class="flex items-center gap-3 sm:gap-4 shrink-0">
                    <button type="button"
                            @click="setTab('kasir')"
                            class="hidden md:inline-flex items-center gap-1.5 bg-red-600 hover:bg-red-700 text-white px-3.5 py-2 rounded-xl font-black text-xs uppercase tracking-wider transition shadow-sm">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                        Buka Kasir
                    </button>
                    <a href="/" target="_blank" class="hidden sm:inline-flex items-center gap-2 bg-slate-100/90 hover:bg-slate-900 text-slate-700 hover:text-white px-3.5 py-2 rounded-xl font-bold text-xs transition border border-slate-200/80">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                        Lihat Web
                    </a>
                    <div class="flex items-center gap-3 pl-3 sm:pl-4 border-l border-slate-200">
                        <div class="text-right hidden md:block leading-tight">
                            <p class="text-xs font-bold text-slate-900">{{ Auth::user()->name ?? 'Admin' }}</p>
                            <p class="text-[10px] font-black text-red-600 uppercase tracking-wider mt-0.5">{{ Auth::user()->role ?? 'Administrator' }}</p>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-slate-800 to-slate-950 text-white flex items-center justify-center font-black text-sm ring-2 ring-red-500/80 shadow-sm shrink-0">
                            {{ strtoupper(substr(Auth::user()->name ?? 'A', 0, 1)) }}
                        </div>
                    </div>
                </div>
            </header>

            <!-- Dynamic Content (Scrollable) -->
            <div class="flex-1 overflow-y-auto p-6 lg:p-8 custom-scrollbar relative">
                
                <!-- TAB 1: DASHBOARD RINGKASAN -->
                <div x-show="activeTab === 'dashboard'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                    <div class="mb-6 flex flex-col xl:flex-row xl:items-center justify-between gap-4">
                        <div>
                            <h3 class="text-base font-bold text-slate-800">Selamat datang kembali, {{ Auth::user()->name ?? 'Admin' }} 👋</h3>
                            <p class="text-xs text-slate-500 mt-0.5">Pantau performa penjualan, pesanan aktif, dan ketersediaan stok Sport Mania Distro secara real-time.</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 self-start xl:self-auto">
                            <!-- Filter Periode Bulan & Tahun -->
                            <div class="inline-flex items-center gap-1.5 bg-white border border-slate-200/80 rounded-xl p-1 shadow-sm">
                                <select x-model="dashPeriodMode" class="bg-transparent text-xs font-black text-slate-800 px-2.5 py-1.5 rounded-lg focus:outline-none cursor-pointer">
                                    <option value="all">Semua Waktu</option>
                                    <option value="monthly">Filter Per Bulan</option>
                                </select>

                                <template x-if="dashPeriodMode === 'monthly'">
                                    <div class="flex items-center gap-1 border-l border-slate-200 pl-1.5">
                                        <select x-model="dashMonth" class="bg-slate-50 text-xs font-bold text-red-600 px-2 py-1.5 rounded-lg focus:outline-none cursor-pointer">
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
                                        <select x-model="dashYear" class="bg-slate-50 text-xs font-bold text-slate-800 px-2 py-1.5 rounded-lg focus:outline-none cursor-pointer">
                                            <template x-for="yr in getAvailableRecapYears()" :key="yr">
                                                <option :value="yr" x-text="yr" :selected="yr == dashYear"></option>
                                            </template>
                                        </select>
                                    </div>
                                </template>
                            </div>

                            <button type="button"
                                    @click="setTab('kasir')"
                                    class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white px-3.5 py-2 rounded-xl text-xs font-bold transition shadow-sm">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                                Transaksi Kasir
                            </button>
                            <button type="button"
                                    @click="recapMonth = dashMonth; recapYear = dashYear; setTab('rekap')"
                                    class="inline-flex items-center gap-2 bg-slate-900 hover:bg-slate-800 text-white px-3.5 py-2 rounded-xl text-xs font-bold transition shadow-sm">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                                Rekap & Grafik Bulanan
                            </button>
                            <button type="button"
                                    @click="exportOrdersCsv(false)"
                                    class="inline-flex items-center gap-2 bg-white hover:bg-slate-900 text-slate-700 hover:text-white border border-slate-200/80 px-3.5 py-2 rounded-xl text-xs font-bold transition shadow-sm">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                Unduh CSV
                            </button>
                        </div>
                    </div>

                    <!-- Banner Pengingat Verifikasi Bukti Pembayaran -->
                    @if($waitingVerificationCount > 0)
                        <div class="mb-6 bg-gradient-to-r from-amber-50 via-orange-50 to-red-50 border border-amber-200 rounded-2xl p-4 sm:p-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 shadow-sm">
                            <div class="flex items-start gap-3.5">
                                <div class="w-10 h-10 rounded-xl bg-amber-600 text-white flex items-center justify-center shrink-0 shadow-md shadow-amber-600/20 text-lg">
                                    💳
                                </div>
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h4 class="text-xs sm:text-sm font-black text-amber-950 uppercase tracking-tight">
                                            {{ $waitingVerificationCount }} Bukti Pembayaran Menunggu Verifikasi
                                        </h4>
                                        <span class="bg-amber-600 text-white text-[10px] font-black px-2 py-0.5 rounded-full uppercase animate-pulse">Perlu Dicek</span>
                                    </div>
                                    <p class="text-xs text-amber-900/80 mt-0.5">
                                        Pelanggan sudah mengunggah bukti transfer/QRIS. Periksa kecocokan nominal agar pesanan segera diproses.
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 shrink-0 w-full sm:w-auto">
                                @php $firstVerifyOrder = $waitingVerificationOrders->first(); @endphp
                                @if($firstVerifyOrder)
                                    <button type="button"
                                            @click="openOrderModal({{ $firstVerifyOrder->id }})"
                                            class="flex-1 sm:flex-initial bg-amber-600 hover:bg-amber-700 text-white text-xs font-black uppercase tracking-wider px-4 py-2.5 rounded-xl transition shadow-sm">
                                        Cek Bukti Terbaru →
                                    </button>
                                @endif
                                <button type="button"
                                        @click="setTab('pesanan'); orderFilter = 'verifikasi'"
                                        class="bg-white hover:bg-amber-100 text-amber-900 border border-amber-200 text-xs font-bold px-3.5 py-2.5 rounded-xl transition">
                                    Lihat Semua ({{ $waitingVerificationCount }})
                                </button>
                            </div>
                        </div>
                    @endif
                    
                    <!-- KPI Cards (Klik kartu untuk membuka Modal Quick Preview) -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-6">
                        <div @click="dashModal = { open: true, type: 'products' }"
                             class="cursor-pointer bg-white p-6 rounded-2xl border border-slate-200/70 shadow-sm hover:shadow-md hover:border-slate-300 transition-all group">
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider group-hover:text-slate-600">Total Produk</span>
                                <div class="w-9 h-9 rounded-xl bg-slate-100 text-slate-700 group-hover:bg-slate-900 group-hover:text-white flex items-center justify-center transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                                </div>
                            </div>
                            <p class="text-3xl font-black text-slate-900 tabular-nums">{{ $products->count() }} <span class="text-xs text-slate-400 font-semibold">katalog</span></p>
                            <p class="text-xs text-slate-500 mt-2 flex items-center justify-between gap-1">
                                <span>Stok: <strong class="text-slate-800 tabular-nums">{{ $totalStockAll }} pcs</strong> ({{ $availableCategories->count() }} kat.)</span>
                                <span class="text-[10px] font-bold text-red-600 opacity-0 group-hover:opacity-100 transition shrink-0">Rincian →</span>
                            </p>
                        </div>
                        
                        <div @click="dashModal = { open: true, type: 'orders' }"
                             class="cursor-pointer bg-white p-6 rounded-2xl border border-slate-200/70 shadow-sm hover:shadow-md hover:border-amber-300 transition-all group">
                            <div class="flex items-center justify-between mb-3">
                                <div>
                                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Pesanan Masuk</span>
                                    <span class="text-[10px] font-bold text-amber-600" x-text="getDashboardStats().periodLabel"></span>
                                </div>
                                <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 group-hover:bg-amber-500 group-hover:text-white flex items-center justify-center transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                                </div>
                            </div>
                            <p class="text-3xl font-black text-slate-900 tabular-nums">
                                <span x-text="getDashboardStats().totalOrders"></span>
                                <span class="text-xs text-emerald-600 font-semibold">transaksi</span>
                            </p>
                            <p class="text-xs text-slate-500 mt-2 flex items-center justify-between gap-1">
                                <span>
                                    Perlu diproses: <strong class="text-red-600 tabular-nums" x-text="getDashboardStats().needProcess"></strong>
                                    <template x-if="getDashboardStats().waitingVerify > 0">
                                        <span> • Cek: <strong class="text-amber-600 tabular-nums" x-text="getDashboardStats().waitingVerify"></strong></span>
                                    </template>
                                </span>
                                <span class="text-[10px] font-bold text-amber-600 opacity-0 group-hover:opacity-100 transition shrink-0">Daftar →</span>
                            </p>
                        </div>

                        <div @click="dashModal = { open: true, type: 'customers' }"
                             class="cursor-pointer bg-white p-6 rounded-2xl border border-slate-200/70 shadow-sm hover:shadow-md hover:border-blue-300 transition-all group">
                            <div class="flex items-center justify-between mb-3">
                                <div>
                                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block" x-text="dashPeriodMode === 'all' ? 'Total Pelanggan' : 'Pelanggan Baru'"></span>
                                    <span class="text-[10px] font-bold text-blue-600" x-text="getDashboardStats().periodLabel"></span>
                                </div>
                                <div class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 group-hover:bg-blue-600 group-hover:text-white flex items-center justify-center transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                </div>
                            </div>
                            <p class="text-3xl font-black text-slate-900 tabular-nums">
                                <span x-text="getDashboardStats().customerCount"></span>
                                <span class="text-xs text-blue-600 font-semibold" x-text="dashPeriodMode === 'all' ? 'akun aktif' : 'member baru'"></span>
                            </p>
                            <p class="text-xs text-slate-500 mt-2 flex items-center justify-between gap-1">
                                <span x-text="dashPeriodMode === 'all' ? 'Member terdaftar di sistem' : 'Total semua member: {{ $customers->count() }} akun'"></span>
                                <span class="text-[10px] font-bold text-blue-600 opacity-0 group-hover:opacity-100 transition shrink-0">Rincian →</span>
                            </p>
                        </div>

                        <div @click="dashModal = { open: true, type: 'revenue' }"
                             class="cursor-pointer bg-gradient-to-br from-slate-900 via-slate-950 to-slate-900 p-6 rounded-2xl border border-slate-800 hover:border-red-500/50 shadow-lg relative overflow-hidden transition-all group">
                            <div class="absolute -right-6 -top-6 w-24 h-24 rounded-full bg-red-600/20 blur-2xl"></div>
                            <div class="flex items-center justify-between mb-3 relative z-10">
                                <div>
                                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Omzet Selesai</span>
                                    <span class="text-[10px] font-bold text-emerald-400" x-text="getDashboardStats().periodLabel"></span>
                                </div>
                                <div class="w-9 h-9 rounded-xl bg-white/10 text-emerald-400 group-hover:bg-emerald-500 group-hover:text-white flex items-center justify-center transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                </div>
                            </div>
                            <p class="text-2xl xl:text-[26px] font-black text-white tabular-nums relative z-10" x-text="'Rp ' + Number(getDashboardStats().realizedRevenue).toLocaleString('id-ID')"></p>
                            <div class="text-[11px] text-slate-400 mt-2 font-medium relative z-10 tabular-nums space-y-0.5">
                                <p>Lunas Masuk: <span class="text-emerald-400 font-bold" x-text="'Rp ' + Number(getDashboardStats().paidRevenue).toLocaleString('id-ID')"></span> (<span x-text="getDashboardStats().selesai"></span> selesai)</p>
                                <p class="flex items-center justify-between">
                                    <span>Hari Ini: <span class="text-sky-300 font-bold" x-text="'Rp ' + Number(getDashboardStats().todayRevenue).toLocaleString('id-ID')"></span> • Pipeline: <span class="text-amber-300 font-bold" x-text="'Rp ' + Number(getDashboardStats().pipelineRevenue).toLocaleString('id-ID')"></span></span>
                                    <span class="text-[10px] font-bold text-white underline ml-1 shrink-0">Detail →</span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Ringkasan Pipeline Status Pesanan -->
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-6">
                        <button type="button" @click="setTab('pesanan'); orderFilter = 'pending'" class="bg-white hover:border-amber-300 border border-slate-200/70 rounded-2xl p-3.5 text-left transition shadow-sm">
                            <span class="text-[10px] font-black uppercase tracking-wider text-amber-600 block">Pending</span>
                            <span class="text-xl font-black text-slate-900 tabular-nums mt-0.5 block" x-text="getDashboardStats().pending"></span>
                        </button>
                        <button type="button" @click="setTab('pesanan'); orderFilter = 'diproses'" class="bg-white hover:border-blue-300 border border-slate-200/70 rounded-2xl p-3.5 text-left transition shadow-sm">
                            <span class="text-[10px] font-black uppercase tracking-wider text-blue-600 block">Diproses</span>
                            <span class="text-xl font-black text-slate-900 tabular-nums mt-0.5 block" x-text="getDashboardStats().diproses"></span>
                        </button>
                        <button type="button" @click="setTab('pesanan'); orderFilter = 'dikirim'" class="bg-white hover:border-purple-300 border border-slate-200/70 rounded-2xl p-3.5 text-left transition shadow-sm">
                            <span class="text-[10px] font-black uppercase tracking-wider text-purple-600 block">Dikirim</span>
                            <span class="text-xl font-black text-slate-900 tabular-nums mt-0.5 block" x-text="getDashboardStats().dikirim"></span>
                        </button>
                        <button type="button" @click="setTab('pesanan'); orderFilter = 'selesai'" class="bg-white hover:border-emerald-300 border border-slate-200/70 rounded-2xl p-3.5 text-left transition shadow-sm">
                            <span class="text-[10px] font-black uppercase tracking-wider text-emerald-600 block">Selesai</span>
                            <span class="text-xl font-black text-slate-900 tabular-nums mt-0.5 block" x-text="getDashboardStats().selesai"></span>
                        </button>
                        <button type="button" @click="setTab('riwayat')" class="col-span-2 sm:col-span-1 bg-white hover:border-rose-300 border border-slate-200/70 rounded-2xl p-3.5 text-left transition shadow-sm">
                            <span class="text-[10px] font-black uppercase tracking-wider text-rose-600 block">Dibatalkan</span>
                            <span class="text-xl font-black text-slate-900 tabular-nums mt-0.5 block" x-text="getDashboardStats().dibatalkan"></span>
                        </button>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                        <!-- Peringatan Stok Menipis -->
                        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200/70 shadow-sm p-6">
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <h3 class="font-black text-slate-900 text-sm uppercase tracking-wider">Peringatan Stok Menipis</h3>
                                    <p class="text-xs text-slate-500 mt-0.5">Produk dengan total stok ≤ 10 pcs atau memiliki varian stok ≤ 3 pcs.</p>
                                </div>
                                <span class="bg-red-50 text-red-600 text-xs font-black px-3 py-1 rounded-full border border-red-100 shrink-0 tabular-nums">
                                    {{ $lowStockProducts->count() }} Produk
                                </span>
                            </div>

                            @if($lowStockProducts->count() > 0)
                                <div class="divide-y divide-slate-100">
                                    @foreach($lowStockProducts as $lowProd)
                                        @php
                                            $hasVars = $lowProd->variants && $lowProd->variants->count() > 0;
                                            $lowTotal = $hasVars ? $lowProd->variants->sum('stock') : ($lowProd->stock ?? 0);
                                            $criticalVars = $hasVars ? $lowProd->variants->filter(fn($v) => (int) $v->stock <= 3) : collect();
                                        @endphp
                                        <div class="py-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                            <div class="flex items-start gap-3">
                                                <span class="w-9 h-9 rounded-xl bg-red-50 text-red-600 flex items-center justify-center font-black text-xs border border-red-100 shrink-0 mt-0.5 tabular-nums">
                                                    {{ $lowTotal }}
                                                </span>
                                                <div>
                                                    <p class="font-bold text-sm text-slate-900">{{ $lowProd->name }}</p>
                                                    <p class="text-xs text-slate-500">Kategori: {{ optional($lowProd->category)->name ?? 'Umum' }}</p>
                                                    @if($criticalVars->count() > 0)
                                                        <div class="flex flex-wrap gap-1 mt-1.5">
                                                            @foreach($criticalVars as $cv)
                                                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-md {{ $cv->stock == 0 ? 'bg-slate-900 text-white' : 'bg-amber-50 text-amber-700 border border-amber-200' }}">
                                                                    {{ $cv->size }} ({{ $cv->color }}): {{ $cv->stock == 0 ? 'Habis' : 'Sisa ' . $cv->stock }}
                                                                </span>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                            <a href="{{ route('admin.produk.edit', $lowProd->id) }}" class="text-xs font-bold text-blue-600 hover:text-blue-700 bg-blue-50 hover:bg-blue-100 px-3.5 py-2 rounded-xl transition self-start sm:self-center shrink-0">
                                                + Update Stok
                                            </a>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="p-6 text-center bg-emerald-50/60 rounded-2xl border border-emerald-100 text-emerald-700 text-xs font-bold">
                                    Semua produk dan varian memiliki stok aman.
                                </div>
                            @endif
                        </div>

                        <!-- Aksi Cepat Admin -->
                        <div class="bg-white rounded-2xl border border-slate-200/70 shadow-sm p-6 flex flex-col justify-between">
                            <div>
                                <h3 class="font-black text-slate-900 text-sm uppercase tracking-wider mb-1">Aksi Cepat Admin</h3>
                                <p class="text-xs text-slate-500 mb-4">Pintasan pengelolaan kasir, katalog, dan transaksi.</p>
                                <div class="space-y-2.5">
                                    <button @click="setTab('kasir')" class="flex items-center justify-between w-full p-3.5 rounded-xl bg-slate-900 hover:bg-red-600 text-white font-bold text-xs transition shadow-sm">
                                        <span>🧾 Buka Kasir Toko (POS Offline)</span>
                                        <span>→</span>
                                    </button>
                                    @if($waitingVerificationCount > 0)
                                        <button @click="setTab('pesanan'); orderFilter = 'verifikasi'" class="flex items-center justify-between w-full p-3.5 rounded-xl bg-amber-50 hover:bg-amber-100 text-amber-900 font-bold text-xs transition border border-amber-200">
                                            <span class="flex items-center gap-2">
                                                💳 Verifikasi Bukti Bayar
                                                <span class="bg-amber-600 text-white px-2 py-0.5 rounded-full text-[10px] tabular-nums">{{ $waitingVerificationCount }}</span>
                                            </span>
                                            <span>→</span>
                                        </button>
                                    @endif
                                    <a href="{{ route('admin.produk.create') }}" class="flex items-center justify-between w-full p-3.5 rounded-xl bg-red-50/80 hover:bg-red-100 text-red-700 font-bold text-xs transition border border-red-100">
                                        <span>+ Tambah Produk Baru</span>
                                        <span>→</span>
                                    </a>
                                    <button @click="setTab('voucher'); voucherFormOpen = true" class="flex items-center justify-between w-full p-3.5 rounded-xl bg-slate-50 hover:bg-slate-100 text-slate-700 font-bold text-xs transition border border-slate-200/60">
                                        <span>+ Buat Kode Voucher Diskon</span>
                                        <span>→</span>
                                    </button>
                                    <button @click="setTab('promo'); openNewPromo()" class="flex items-center justify-between w-full p-3.5 rounded-xl bg-slate-50 hover:bg-slate-100 text-slate-700 font-bold text-xs transition border border-slate-200/60">
                                        <span>+ Buat Postingan Promo Beranda</span>
                                        <span>→</span>
                                    </button>
                                </div>
                            </div>
                            <div class="mt-6 pt-4 border-t border-slate-100 text-[11px] text-slate-400 font-medium">
                                Sport Mania Distro • Malang, Jawa Timur
                            </div>
                        </div>
                    </div>

                    <!-- Pesanan Terbaru -->
                    <div class="bg-white rounded-2xl border border-slate-200/70 shadow-sm overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                            <div>
                                <h3 class="font-black text-slate-900 text-sm uppercase tracking-wider">Pesanan Terbaru</h3>
                                <p class="text-xs text-slate-500 mt-0.5">5 transaksi terakhir yang masuk ke toko (online & kasir).</p>
                            </div>
                            <button type="button" @click="setTab('pesanan'); orderFilter = 'semua'" class="text-xs font-bold text-red-600 hover:text-red-700">
                                Kelola Semua →
                            </button>
                        </div>
                        <div class="divide-y divide-slate-100">
                            @forelse($orders->take(5) as $recentOrder)
                                @php
                                    $rStatusRaw = strtolower($recentOrder->status ?? 'pending');
                                    $rStatus = $rStatusRaw === 'batal' ? 'dibatalkan' : $rStatusRaw;
                                    $rPayStatus = $resolvePaymentStatus($recentOrder);
                                    $rCode = '#' . (filled($recentOrder->order_number ?? null) ? $recentOrder->order_number : (filled($recentOrder->invoice_number ?? null) ? $recentOrder->invoice_number : ('ORD-' . str_pad($recentOrder->id, 4, '0', STR_PAD_LEFT))));
                                    $rCustomer = filled($recentOrder->recipient_name ?? null) ? $recentOrder->recipient_name
                                        : (filled($recentOrder->customer_name ?? null) ? $recentOrder->customer_name
                                        : (filled($recentOrder->name ?? null) ? $recentOrder->name
                                        : (optional($recentOrder->user)->name ?? $usersById[$recentOrder->user_id ?? 0] ?? 'Pelanggan Setia')));
                                    $rTotal = $recentOrder->total_price ?? $recentOrder->total_amount ?? $recentOrder->total ?? 0;
                                @endphp
                                <div class="px-6 py-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-slate-50/70 transition">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <span class="font-black text-xs text-slate-900 shrink-0">{{ $rCode }}</span>
                                        <span class="text-xs font-bold text-slate-700 truncate">{{ $rCustomer }}</span>
                                        <span class="text-[10px] text-slate-400 hidden md:inline tabular-nums">• {{ optional($recentOrder->created_at)->diffForHumans() ?? '-' }}</span>
                                    </div>
                                    <div class="flex items-center justify-between sm:justify-end gap-2.5">
                                        <span class="text-xs font-black text-slate-900 tabular-nums">Rp {{ number_format($rTotal, 0, ',', '.') }}</span>
                                        <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded-md border
                                            {{ $rStatus === 'pending' ? 'bg-amber-50 text-amber-700 border-amber-200' : '' }}
                                            {{ $rStatus === 'diproses' ? 'bg-blue-50 text-blue-700 border-blue-200' : '' }}
                                            {{ $rStatus === 'dikirim' ? 'bg-purple-50 text-purple-700 border-purple-200' : '' }}
                                            {{ $rStatus === 'selesai' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : '' }}
                                            {{ $rStatus === 'dibatalkan' ? 'bg-rose-50 text-rose-700 border-rose-200' : '' }}">
                                            {{ $rStatus }}
                                        </span>
                                        <button type="button"
                                                @click="openOrderModal({{ $recentOrder->id }})"
                                                class="text-[11px] font-bold px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-900 text-slate-700 hover:text-white transition">
                                            {{ $rPayStatus === 'waiting_verification' && $rStatus !== 'dibatalkan' ? 'Verifikasi' : 'Detail' }}
                                        </button>
                                    </div>
                                </div>
                            @empty
                                <div class="px-6 py-8 text-center text-xs text-slate-400">Belum ada pesanan terbaru.</div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <!-- ========================================================= -->
                <!-- TAB BARU: KASIR TOKO (POINT OF SALE / POS OFFLINE)        -->
                <!-- ========================================================= -->
                <div x-show="activeTab === 'kasir'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                    <div class="grid grid-cols-1 xl:grid-cols-12 gap-6">
                        
                        <!-- KIRI: KATALOG PRODUK KASIR (7 KOLOM) -->
                        <div class="xl:col-span-7 space-y-4">
                            <div class="bg-white rounded-2xl border border-slate-200/80 p-4 shadow-sm flex flex-col sm:flex-row gap-3">
                                <div class="relative flex-1">
                                    <input type="text"
                                           x-model="posSearch"
                                           placeholder="Cari produk di kasir..."
                                           class="w-full bg-slate-50 text-xs text-slate-900 rounded-xl pl-9 pr-4 py-2.5 border border-slate-200 focus:outline-none focus:ring-2 focus:ring-red-500 font-semibold">
                                    <svg class="w-4 h-4 absolute left-3 top-2.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                </div>
                                <select x-model="posCategory"
                                        class="bg-slate-50 text-xs font-bold text-slate-700 rounded-xl px-3.5 py-2.5 border border-slate-200 focus:outline-none focus:ring-2 focus:ring-red-500">
                                    <option value="semua">Semua Kategori</option>
                                    @foreach($availableCategories as $cat)
                                        <option value="{{ strtolower($cat->slug) }}">{{ $cat->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Grid Kartu Produk Kasir -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                <template x-for="prod in getPosFilteredProducts()" :key="prod.id">
                                    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden flex flex-col justify-between hover:border-red-300 transition">
                                        <div>
                                            <div class="relative h-36 bg-slate-100 overflow-hidden">
                                                <template x-if="prod.image">
                                                    <img :src="prod.image" :alt="prod.name" class="w-full h-full object-cover">
                                                </template>
                                                <template x-if="!prod.image">
                                                    <div class="w-full h-full flex items-center justify-center text-3xl">👕</div>
                                                </template>
                                                <span class="absolute top-2.5 left-2.5 bg-slate-950/80 text-white text-[9px] font-black uppercase px-2 py-0.5 rounded-md" x-text="prod.category_name"></span>
                                                <span class="absolute top-2.5 right-2.5 text-[10px] font-black px-2 py-0.5 rounded-md tabular-nums"
                                                      :class="getPosAvailableStock(prod) > 0 ? 'bg-emerald-500 text-white' : 'bg-rose-600 text-white'"
                                                      x-text="getPosAvailableStock(prod) > 0 ? 'Stok: ' + getPosAvailableStock(prod) : 'Habis'"></span>
                                            </div>

                                            <div class="p-3.5">
                                                <h4 class="font-bold text-slate-900 text-xs line-clamp-1" x-text="prod.name"></h4>
                                                <p class="font-black text-red-600 text-sm tabular-nums mt-1" x-text="'Rp ' + Number(prod.price).toLocaleString('id-ID')"></p>

                                                <template x-if="prod.has_variants && prod.variants.length > 0">
                                                    <div class="mt-2.5">
                                                        <label class="block text-[9px] font-black uppercase tracking-wider text-slate-400 mb-1">Pilih Varian</label>
                                                        <select x-model="posSelectedVariants[prod.id]"
                                                                class="w-full bg-slate-50 border border-slate-200 rounded-lg px-2 py-1.5 text-[11px] font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-red-500">
                                                            <template x-for="v in prod.variants" :key="v.id">
                                                                <option :value="v.id" x-text="v.size + (v.color !== '-' ? ' • ' + v.color : '') + ' (Sisa ' + v.stock + ')'"></option>
                                                            </template>
                                                        </select>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>

                                        <div class="px-3.5 pb-3.5">
                                            <button type="button"
                                                    @click="addToPosCart(prod)"
                                                    :disabled="getPosAvailableStock(prod) <= 0"
                                                    class="w-full py-2 rounded-xl font-black text-[11px] uppercase tracking-wider transition flex items-center justify-center gap-1.5"
                                                    :class="getPosAvailableStock(prod) > 0 ? 'bg-slate-900 hover:bg-red-600 text-white shadow-sm' : 'bg-slate-100 text-slate-400 cursor-not-allowed'">
                                                <span>+ Tambah ke Struk</span>
                                            </button>
                                        </div>
                                    </div>
                                </template>

                                <template x-if="getPosFilteredProducts().length === 0">
                                    <div class="col-span-full bg-white rounded-2xl border border-dashed border-slate-300 p-10 text-center text-xs text-slate-400">
                                        Produk tidak ditemukan pada pencarian kasir.
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- KANAN: KERANJANG & PEMBAYARAN KASIR (5 KOLOM) -->
                        <div class="xl:col-span-5">
                            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden sticky top-0">
                                <div class="bg-slate-950 px-5 py-4 text-white flex items-center justify-between">
                                    <div>
                                        <span class="text-[10px] font-black uppercase tracking-widest text-red-500 block">Point of Sale</span>
                                        <h3 class="text-sm font-black mt-0.5">Keranjang Transaksi Kasir</h3>
                                    </div>
                                    <button type="button"
                                            x-show="posCart.length > 0"
                                            @click="clearPosCart()"
                                            class="text-[11px] font-bold text-slate-400 hover:text-rose-400 transition">
                                        Kosongkan
                                    </button>
                                </div>

                                <div class="p-5 space-y-4">
                                    <!-- Daftar Item Keranjang -->
                                    <div class="max-h-64 overflow-y-auto custom-scrollbar divide-y divide-slate-100 border border-slate-200/80 rounded-xl">
                                        <template x-for="(item, idx) in posCart" :key="item.key">
                                            <div class="p-3 flex items-center justify-between gap-2.5 hover:bg-slate-50/70">
                                                <div class="min-w-0 flex-1">
                                                    <p class="font-bold text-xs text-slate-900 truncate" x-text="item.name"></p>
                                                    <p class="text-[10px] text-slate-500 mt-0.5">
                                                        <span x-text="item.size + (item.color !== '-' ? ' / ' + item.color : '')"></span>
                                                        • <span class="font-semibold text-slate-700 tabular-nums" x-text="'Rp ' + Number(item.price).toLocaleString('id-ID')"></span>
                                                    </p>
                                                </div>

                                                <div class="flex items-center gap-1.5 shrink-0">
                                                    <button type="button" @click="updatePosCartQty(idx, -1)" class="w-6 h-6 rounded-lg bg-slate-100 hover:bg-slate-200 font-black text-xs text-slate-700 flex items-center justify-center">-</button>
                                                    <span class="w-6 text-center text-xs font-black text-slate-900 tabular-nums" x-text="item.qty"></span>
                                                    <button type="button" @click="updatePosCartQty(idx, 1)" class="w-6 h-6 rounded-lg bg-slate-100 hover:bg-slate-200 font-black text-xs text-slate-700 flex items-center justify-center">+</button>
                                                    <button type="button" @click="removePosCartItem(idx)" class="ml-1 text-rose-500 hover:text-rose-700 text-xs font-bold p-1" title="Hapus item">✕</button>
                                                </div>
                                            </div>
                                        </template>

                                        <template x-if="posCart.length === 0">
                                            <div class="p-8 text-center text-xs text-slate-400">
                                                Belum ada item dipilih. Klik <strong>+ Tambah ke Struk</strong> pada daftar produk di sebelah kiri.
                                            </div>
                                        </template>
                                    </div>

                                    <!-- Form Checkout Kasir -->
                                    <form action="{{ $posActionUrl }}" method="POST" class="space-y-3.5 text-xs">
                                        @csrf
                                        <input type="hidden" name="items_json" :value="JSON.stringify(posCart)">
                                        <input type="hidden" name="subtotal" :value="getPosSubtotal()">
                                        <input type="hidden" name="discount" :value="posDiscount">
                                        <input type="hidden" name="total_price" :value="getPosTotal()">
                                        <input type="hidden" name="cash_received" :value="posCashReceived">
                                        <input type="hidden" name="cash_change" :value="getPosChange()">

                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            <div>
                                                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">Nama Pembeli</label>
                                                <input type="text" name="customer_name" x-model="posCustomerName" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 font-bold text-slate-900 focus:ring-2 focus:ring-red-500 outline-none">
                                            </div>
                                            <div>
                                                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">No. HP / WA (Opsional)</label>
                                                <input type="text" name="phone" x-model="posCustomerPhone" placeholder="08..." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 font-semibold text-slate-900 focus:ring-2 focus:ring-red-500 outline-none">
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            <div>
                                                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">Metode Pembayaran</label>
                                                <select name="payment_method" x-model="posPaymentMethod" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 font-black text-slate-900 focus:ring-2 focus:ring-red-500 outline-none">
                                                    <option value="TUNAI">TUNAI (CASH)</option>
                                                    <option value="QRIS">QRIS TOKO</option>
                                                    <option value="TRANSFER">TRANSFER / EDC</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">Potongan / Diskon (Rp)</label>
                                                <input type="number" min="0" x-model.number="posDiscount" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 font-bold text-slate-900 focus:ring-2 focus:ring-red-500 outline-none tabular-nums">
                                            </div>
                                        </div>

                                        <!-- Kalkulator Uang Tunai & Kembalian -->
                                        <div x-show="posPaymentMethod === 'TUNAI'" class="bg-slate-50 border border-slate-200/80 rounded-xl p-3 space-y-2.5">
                                            <div class="flex items-center justify-between">
                                                <label class="text-[10px] font-black uppercase tracking-wider text-slate-600">Uang Tunai Diterima (Rp)</label>
                                                <div class="flex gap-1">
                                                    <button type="button" @click="posCashReceived = getPosTotal()" class="text-[10px] font-bold bg-white hover:bg-slate-900 text-slate-700 hover:text-white border border-slate-200 px-2 py-0.5 rounded-md transition">Uang Pas</button>
                                                    <button type="button" @click="posCashReceived = 50000" class="text-[10px] font-bold bg-white hover:bg-slate-900 text-slate-700 hover:text-white border border-slate-200 px-2 py-0.5 rounded-md transition">50rb</button>
                                                    <button type="button" @click="posCashReceived = 100000" class="text-[10px] font-bold bg-white hover:bg-slate-900 text-slate-700 hover:text-white border border-slate-200 px-2 py-0.5 rounded-md transition">100rb</button>
                                                </div>
                                            </div>
                                            <input type="number" min="0" x-model.number="posCashReceived" placeholder="Masukkan nominal uang..." class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-sm font-black text-slate-900 focus:ring-2 focus:ring-red-500 outline-none tabular-nums">
                                            <div class="flex items-center justify-between pt-1 text-xs">
                                                <span class="font-bold text-slate-500">Kembalian:</span>
                                                <span class="font-black text-sm tabular-nums" :class="posCashReceived >= getPosTotal() ? 'text-emerald-600' : 'text-rose-600'" x-text="'Rp ' + Number(getPosChange()).toLocaleString('id-ID')"></span>
                                            </div>
                                        </div>

                                        <!-- Ringkasan Total -->
                                        <div class="border-t border-dashed border-slate-200 pt-3 space-y-1.5 tabular-nums">
                                            <div class="flex justify-between text-slate-500 font-semibold">
                                                <span>Subtotal (<span x-text="posCart.reduce((s, i) => s + i.qty, 0)"></span> item)</span>
                                                <span x-text="'Rp ' + Number(getPosSubtotal()).toLocaleString('id-ID')"></span>
                                            </div>
                                            <div x-show="posDiscount > 0" class="flex justify-between text-emerald-600 font-bold">
                                                <span>Diskon Kasir</span>
                                                <span x-text="'- Rp ' + Number(posDiscount || 0).toLocaleString('id-ID')"></span>
                                            </div>
                                            <div class="flex justify-between items-center pt-1.5 border-t border-slate-100">
                                                <span class="text-xs font-black uppercase tracking-wider text-slate-800">Total Bayar</span>
                                                <span class="text-xl font-black text-red-600" x-text="'Rp ' + Number(getPosTotal()).toLocaleString('id-ID')"></span>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-3 gap-2 pt-2">
                                            <button type="button"
                                                    @click="previewPosReceipt()"
                                                    :disabled="posCart.length === 0"
                                                    class="col-span-1 py-3 rounded-xl font-bold text-xs bg-slate-100 hover:bg-slate-200 text-slate-700 disabled:opacity-50 transition">
                                                🖨️ Preview
                                            </button>
                                            <button type="submit"
                                                    :disabled="!canSubmitPosCheckout()"
                                                    class="col-span-2 py-3 rounded-xl font-black text-xs uppercase tracking-wider text-white bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-700 hover:to-rose-700 disabled:opacity-50 disabled:cursor-not-allowed shadow-md shadow-red-600/20 transition">
                                                Selesaikan & Cetak Struk
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- TAB 2: MANAJEMEN PRODUK -->
                <div x-show="activeTab === 'produk'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
                        <div class="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
                            <div class="relative w-full sm:w-72">
                                <input type="text" x-model="searchProduct" placeholder="Cari nama produk..." class="w-full bg-white text-xs text-slate-900 rounded-xl pl-10 pr-4 py-2.5 border border-slate-200/80 focus:outline-none focus:ring-2 focus:ring-red-500 transition shadow-sm font-medium">
                                <svg class="w-4 h-4 absolute left-3.5 top-2.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                            </div>
                            <select x-model="filterCategory" class="bg-white text-xs text-slate-700 rounded-xl px-3.5 py-2.5 border border-slate-200/80 focus:outline-none focus:ring-2 focus:ring-red-500 shadow-sm font-bold">
                                <option value="semua">Semua Kategori</option>
                                @if($availableCategories->count() > 0)
                                    @foreach($availableCategories as $cat)
                                        <option value="{{ strtolower($cat->slug) }}">{{ $cat->name }}</option>
                                    @endforeach
                                @else
                                    <option value="pakaian">Pakaian</option>
                                    <option value="sepatu">Sepatu</option>
                                    <option value="tas">Tas</option>
                                    <option value="aksesoris">Aksesoris</option>
                                @endif
                            </select>
                            <button type="button"
                                    x-show="searchProduct !== '' || filterCategory !== 'semua'"
                                    x-cloak
                                    @click="searchProduct = ''; filterCategory = 'semua'"
                                    class="text-xs font-bold text-slate-500 hover:text-red-600 px-3 py-2 rounded-xl bg-white border border-slate-200/80 transition">
                                Reset Filter
                            </button>
                        </div>
                        
                        <a href="{{ route('admin.produk.create') }}" class="bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-700 hover:to-rose-700 text-white px-5 py-2.5 rounded-xl font-bold text-xs uppercase tracking-wider flex items-center gap-2 transition shadow-md shadow-red-600/20 whitespace-nowrap w-fit">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                            Tambah Produk
                        </a>
                    </div>

                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/70 overflow-hidden">
                        <div class="overflow-x-auto custom-scrollbar">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-slate-50/80 border-b border-slate-200/70 text-[10px] uppercase tracking-widest text-slate-500 font-bold">
                                        <th class="px-6 py-4">Info Produk</th>
                                        <th class="px-6 py-4">Kategori & Varian</th>
                                        <th class="px-6 py-4">Harga (Rp)</th>
                                        <th class="px-6 py-4 text-center">Total Stok</th>
                                        <th class="px-6 py-4 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse($products as $product)
                                        @php
                                            $catSlug = strtolower(optional($product->category)->slug ?? '');
                                            $prodNameLower = strtolower($product->name);
                                            $totalStock = ($product->variants && $product->variants->count() > 0)
                                                ? $product->variants->sum('stock')
                                                : ($product->stock ?? 0);
                                        @endphp
                                        <tr x-show="(filterCategory === 'semua' || filterCategory === @js($catSlug)) && (@js($prodNameLower).includes(searchProduct.toLowerCase().trim()))"
                                            class="hover:bg-slate-50/80 transition-colors group">
                                            <td class="px-6 py-4 flex items-center gap-4">
                                                <div class="w-12 h-12 bg-slate-100 rounded-xl overflow-hidden flex items-center justify-center shrink-0 border border-slate-200/80">
                                                    @if($product->image)
                                                        <img src="{{ filter_var($product->image, FILTER_VALIDATE_URL) ? $product->image : asset('storage/' . ltrim($product->image, '/')) }}" alt="{{ $product->name }}" class="w-full h-full object-cover">
                                                    @else
                                                        <span class="text-xl">
                                                            @if($catSlug == 'pakaian') 👕
                                                            @elseif($catSlug == 'sepatu') 👟
                                                            @elseif($catSlug == 'tas') 🎒
                                                            @else 🥊 
                                                            @endif
                                                        </span>
                                                    @endif
                                                </div>
                                                <div>
                                                    <div class="flex items-center gap-2">
                                                        <p class="font-bold text-slate-900 text-sm">{{ $product->name }}</p>
                                                        @if($product->is_featured)
                                                            <span class="bg-red-50 text-red-600 text-[10px] font-black px-2 py-0.5 rounded-md border border-red-100 uppercase">Pilihan</span>
                                                        @endif
                                                    </div>
                                                    <p class="text-xs text-slate-500 truncate max-w-[240px]">{{ $product->description }}</p>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <span class="bg-slate-100 text-slate-700 text-[10px] font-black px-2.5 py-1 rounded-md uppercase tracking-widest mb-1 inline-block">
                                                    {{ optional($product->category)->name ?? 'Tanpa Kategori' }}
                                                </span>
                                                @if($product->variants && $product->variants->count() > 0)
                                                    <div class="flex flex-wrap gap-1 mt-1">
                                                        @foreach($product->variants as $variant)
                                                            <span class="bg-white border border-slate-200 px-1.5 py-0.5 rounded text-[10px] text-slate-600 font-medium tabular-nums" title="Stok: {{ $variant->stock }}">
                                                                {{ $variant->size ?? 'All' }} ({{ $variant->color ?? '-' }}: <strong>{{ $variant->stock }}</strong>)
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 tabular-nums">
    @if($product->discount_price !== null && (float) $product->discount_price > 0)
        <p class="text-xs text-slate-400 line-through">Rp {{ number_format($product->price, 0, ',', '.') }}</p>
        <p class="font-bold text-red-600 text-sm">Rp {{ number_format($product->discount_price, 0, ',', '.') }}</p>
    @else
        <p class="font-bold text-slate-900 text-sm">Rp {{ number_format($product->price, 0, ',', '.') }}</p>
    @endif
</td>
                                            <td class="px-6 py-4 text-center tabular-nums">
                                                <span class="inline-flex items-center justify-center min-w-[2.25rem] h-6 px-2.5 rounded-lg font-bold text-xs border {{ $totalStock > 10 ? 'bg-emerald-50 text-emerald-700 border-emerald-200/70' : 'bg-rose-50 text-rose-700 border-rose-200/70' }}">
                                                    {{ $totalStock }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-right">
                                                <div class="flex justify-end gap-1.5">
                                                    <a href="{{ route('admin.produk.edit', $product->id) }}" class="p-2 text-blue-600 hover:bg-blue-50 rounded-xl transition-colors inline-block" title="Edit Produk">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                                    </a>

                                                    <form id="delete-product-{{ $product->id }}" action="{{ route('admin.produk.destroy', $product->id) }}" method="POST" class="hidden">
                                                        @csrf
                                                        @method('DELETE')
                                                    </form>
                                                    <button type="button"
                                                            @click="openConfirm({
                                                                title: 'Hapus Produk Ini?',
                                                                message: 'Produk ' + @js($product->name) + ' beserta seluruh data varian stoknya akan dihapus permanen dari katalog.',
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
                                            <td colspan="5" class="px-6 py-12 text-center text-slate-400 text-xs font-medium">
                                                Belum ada produk di katalog. Klik "Tambah Produk" untuk memulai.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- TAB PROMO & BANNER BERANDA -->
                <div x-show="activeTab === 'promo'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                        <div>
                            <h3 class="text-base font-black text-slate-900">Kelola Postingan & Kotak Promo Beranda</h3>
                            <p class="text-xs text-slate-500 mt-0.5">Atur konten <strong>Kotak Promo (Featured Showcase)</strong> dan <strong>Hero Slider Utama</strong> yang tampil di halaman depan toko.</p>
                        </div>
                        <button type="button"
                                @click="openNewPromo()"
                                class="bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-700 hover:to-rose-700 text-white px-5 py-2.5 rounded-xl font-bold text-xs uppercase tracking-wider flex items-center gap-2 transition shadow-md shadow-red-600/20">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                            Tambah Postingan Promo
                        </button>
                    </div>

                    <div x-show="promoFormOpen" x-transition class="mb-6 bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6">
                        <div class="flex items-center justify-between pb-4 mb-5 border-b border-slate-100">
                            <h4 class="text-sm font-black uppercase tracking-wider text-slate-900" x-text="editingPromo ? 'Edit Postingan Promo' : 'Buat Postingan Promo Baru'"></h4>
                            <button type="button" @click="promoFormOpen = false" class="text-xs font-bold text-slate-400 hover:text-rose-600">✕ Tutup Form</button>
                        </div>

                        <form :action="editingPromo ? '{{ url('/admin/promos') }}/' + editingPromo.id : '{{ \Illuminate\Support\Facades\Route::has('admin.promos.store') ? route('admin.promos.store') : url('/admin/promos') }}'"
                              method="POST"
                              enctype="multipart/form-data"
                              class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                            @csrf
                            <template x-if="editingPromo">
                                <input type="hidden" name="_method" value="PUT">
                            </template>

                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1.5">Posisi Tampil di Beranda *</label>
                                <select name="placement" :value="editingPromo ? editingPromo.placement : 'showcase'" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-bold text-slate-800 focus:ring-2 focus:ring-red-500 outline-none">
                                    <option value="showcase">Kotak Promo Beranda (Featured Showcase)</option>
                                    <option value="hero">Hero Banner Utama (Slider Atas)</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1.5">Label Badge Merah (Opsional)</label>
                                <input type="text" name="badge_text" :value="editingPromo ? editingPromo.badge_text : 'FEATURED SHOWCASE'" placeholder="Contoh: FEATURED SHOWCASE / PROMO SPESIAL" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-semibold text-slate-900 focus:ring-2 focus:ring-red-500 outline-none">
                            </div>

                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1.5">Judul Utama Promo *</label>
                                <input type="text" name="title" required :value="editingPromo ? editingPromo.title : ''" placeholder="Contoh: DISKON AKHIR BULAN HINGGA 40%" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-bold text-slate-900 focus:ring-2 focus:ring-red-500 outline-none">
                            </div>

                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1.5">Subjudul Kecil (Khusus Hero Slider)</label>
                                <input type="text" name="subtitle" :value="editingPromo ? editingPromo.subtitle : ''" placeholder="Contoh: PERFORMANCE APPAREL" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-semibold text-slate-900 focus:ring-2 focus:ring-red-500 outline-none">
                            </div>

                            <div class="md:col-span-2">
                                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1.5">Deskripsi Promo</label>
                                <textarea name="description" rows="2" x-text="editingPromo ? editingPromo.description : ''" placeholder="Tuliskan detail promo, kode voucher, atau deskripsi koleksi..." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-medium text-slate-900 focus:ring-2 focus:ring-red-500 outline-none"></textarea>
                            </div>

                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1.5">Teks Tombol CTA *</label>
                                <input type="text" name="button_text" required :value="editingPromo ? editingPromo.button_text : 'BELANJA SEKARANG'" placeholder="Contoh: KLAIM PROMO SEKARANG" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-bold text-slate-900 focus:ring-2 focus:ring-red-500 outline-none">
                            </div>

                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1.5">Link Tujuan Tombol *</label>
                                <input type="text" name="button_url" required :value="editingPromo ? editingPromo.button_url : '/katalog'" placeholder="Contoh: /katalog atau /kategori/women" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-semibold text-slate-900 focus:ring-2 focus:ring-red-500 outline-none">
                            </div>

                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1.5">Gambar Banner / Promo (JPG/PNG/WEBP, Maks 3MB)</label>
                                <input type="file" name="image" accept="image/*" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-700">
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1.5">Caption Pojok Gambar</label>
                                    <input type="text" name="image_caption" :value="editingPromo ? editingPromo.image_caption : ''" placeholder="Contoh: Limited Edition 2026" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-semibold text-slate-900 focus:ring-2 focus:ring-red-500 outline-none">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1.5">Urutan Tampil</label>
                                    <input type="number" min="1" name="sort_order" :value="editingPromo ? editingPromo.sort_order : 1" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-bold text-slate-900 focus:ring-2 focus:ring-red-500 outline-none">
                                </div>
                            </div>

                            <div class="md:col-span-2 flex items-center justify-between pt-2 border-t border-slate-100">
                                <label class="inline-flex items-center gap-2 cursor-pointer font-bold text-slate-700">
                                    <input type="checkbox" name="is_active" value="1" :checked="editingPromo ? editingPromo.is_active : true" class="rounded text-red-600 focus:ring-red-500">
                                    <span>Langsung tampilkan di halaman beranda</span>
                                </label>

                                <div class="flex items-center gap-2">
                                    <button type="button" @click="promoFormOpen = false" class="px-4 py-2.5 rounded-xl font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 transition">Batal</button>
                                    <button type="submit" class="px-5 py-2.5 rounded-xl font-black uppercase tracking-wider text-white bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-700 hover:to-rose-700 shadow-md shadow-red-600/20 transition" x-text="editingPromo ? 'Simpan Perubahan' : 'Publikasikan Promo'"></button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        @forelse($promos as $promo)
                            @php
                                $promoImgUrl = filled($promo->image)
                                    ? (filter_var($promo->image, FILTER_VALIDATE_URL) ? $promo->image : asset('storage/' . ltrim($promo->image, '/')))
                                    : null;
                            @endphp
                            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden flex flex-col justify-between">
                                <div>
                                    <div class="relative h-44 bg-slate-900 overflow-hidden">
                                        @if($promoImgUrl)
                                            <img src="{{ $promoImgUrl }}" alt="{{ $promo->title }}" class="w-full h-full object-cover opacity-85">
                                        @else
                                            <div class="w-full h-full flex items-center justify-center text-slate-500 text-xs font-bold">Tanpa Gambar Banner</div>
                                        @endif
                                        <div class="absolute top-3 left-3 flex flex-wrap gap-1.5">
                                            <span class="text-[10px] font-black uppercase px-2.5 py-1 rounded-lg bg-slate-950/85 text-white border border-white/15">
                                                {{ $promo->placement === 'hero' ? 'Hero Slider Atas' : 'Kotak Promo Beranda' }}
                                            </span>
                                            @if($promo->badge_text)
                                                <span class="text-[10px] font-black uppercase px-2.5 py-1 rounded-lg bg-red-600 text-white">
                                                    {{ $promo->badge_text }}
                                                </span>
                                            @endif
                                        </div>
                                        <div class="absolute top-3 right-3">
                                            <span class="text-[10px] font-black uppercase px-2.5 py-1 rounded-full {{ $promo->is_active ? 'bg-emerald-500 text-white' : 'bg-slate-700 text-slate-300' }}">
                                                {{ $promo->is_active ? 'Aktif' : 'Nonaktif' }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="p-5">
                                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Urutan #{{ $promo->sort_order }} • Link: {{ $promo->button_url }}</p>
                                        <h4 class="text-base font-black text-slate-900 mt-1">{{ $promo->title }}</h4>
                                        <p class="text-xs text-slate-500 mt-1.5 line-clamp-2">{{ $promo->description ?: 'Tidak ada deskripsi tambahan.' }}</p>
                                    </div>
                                </div>

                                <div class="px-5 py-3.5 bg-slate-50 border-t border-slate-100 flex items-center justify-between gap-2">
                                    @if(\Illuminate\Support\Facades\Route::has('admin.promos.toggle'))
                                        <form action="{{ route('admin.promos.toggle', $promo->id) }}" method="POST">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="text-[11px] font-bold px-3 py-1.5 rounded-lg border transition {{ $promo->is_active ? 'bg-white text-slate-700 border-slate-200 hover:bg-slate-100' : 'bg-emerald-600 text-white border-emerald-600 hover:bg-emerald-700' }}">
                                                {{ $promo->is_active ? 'Sembunyikan' : 'Tampilkan' }}
                                            </button>
                                        </form>
                                    @else
                                        <span></span>
                                    @endif

                                    <div class="flex items-center gap-1.5">
                                        <button type="button"
                                                @click="openEditPromo(@js($promo))"
                                                class="text-[11px] font-bold px-3 py-1.5 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white transition">
                                            Edit
                                        </button>

                                        <form id="delete-promo-{{ $promo->id }}" action="{{ \Illuminate\Support\Facades\Route::has('admin.promos.destroy') ? route('admin.promos.destroy', $promo->id) : url('/admin/promos/' . $promo->id) }}" method="POST" class="hidden">
                                            @csrf
                                            @method('DELETE')
                                        </form>
                                        <button type="button"
                                                @click="openConfirm({
                                                    title: 'Hapus Postingan Promo?',
                                                    message: 'Postingan promo ' + @js($promo->title) + ' akan dihapus permanen dari beranda.',
                                                    confirmText: 'Ya, Hapus',
                                                    variant: 'danger',
                                                    formId: 'delete-promo-{{ $promo->id }}'
                                                })"
                                                class="text-[11px] font-bold px-3 py-1.5 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white transition">
                                            Hapus
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="md:col-span-2 bg-white rounded-2xl border border-dashed border-slate-300 p-10 text-center">
                                <p class="text-sm font-bold text-slate-700">Belum ada postingan promo di beranda</p>
                                <p class="text-xs text-slate-400 mt-1">Klik tombol <strong>+ Tambah Postingan Promo</strong> di atas untuk mengisi Kotak Promo atau Hero Slider beranda.</p>
                            </div>
                        @endforelse
                    </div>
                </div>

                <!-- TAB KODE VOUCHER PROMO -->
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

                    <div x-show="voucherFormOpen" x-transition class="mb-6 bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6">
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

                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/70 overflow-hidden">
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

                <!-- TAB 3: KELOLA PESANAN -->
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
                                        class="inline-flex items-center gap-1.5 bg-white hover:bg-slate-900 text-slate-700 hover:text-white border border-slate-200/80 px-3.5 py-2 rounded-xl text-xs font-bold transition shadow-sm">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                    Export Filter CSV
                                </button>
                                <div class="flex flex-wrap gap-1 bg-white p-1 rounded-xl border border-slate-200/80 shadow-sm">
                                    <button @click="orderFilter = 'semua'" :class="orderFilter === 'semua' ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100'" class="px-2.5 py-1.5 rounded-lg text-[11px] font-bold uppercase transition">Semua</button>
                                    <button @click="orderFilter = 'verifikasi'" :class="orderFilter === 'verifikasi' ? 'bg-amber-600 text-white shadow-sm' : 'text-amber-800 hover:bg-amber-50'" class="px-2.5 py-1.5 rounded-lg text-[11px] font-black uppercase transition flex items-center gap-1">
                                        <span>Verifikasi</span>
                                        @if($waitingVerificationCount > 0)
                                            <span class="px-1.5 py-0.2 rounded-full text-[9px]" :class="orderFilter === 'verifikasi' ? 'bg-white text-amber-800' : 'bg-amber-600 text-white'">{{ $waitingVerificationCount }}</span>
                                        @endif
                                    </button>
                                    <button @click="orderFilter = 'pending'" :class="orderFilter === 'pending' ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100'" class="px-2.5 py-1.5 rounded-lg text-[11px] font-bold uppercase transition">Pending</button>
                                    <button @click="orderFilter = 'diproses'" :class="orderFilter === 'diproses' ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100'" class="px-2.5 py-1.5 rounded-lg text-[11px] font-bold uppercase transition">Diproses</button>
                                    <button @click="orderFilter = 'dikirim'" :class="orderFilter === 'dikirim' ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100'" class="px-2.5 py-1.5 rounded-lg text-[11px] font-bold uppercase transition">Dikirim</button>
                                    <button @click="orderFilter = 'selesai'" :class="orderFilter === 'selesai' ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100'" class="px-2.5 py-1.5 rounded-lg text-[11px] font-bold uppercase transition">Selesai</button>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <div class="relative w-full sm:w-64">
                                <input type="text" x-model="searchOrder" placeholder="Cari #ORD, pelanggan, resi, tanggal..." class="w-full bg-white text-xs text-slate-900 rounded-xl pl-9 pr-3 py-2.5 border border-slate-200/80 focus:outline-none focus:ring-2 focus:ring-red-500 font-medium shadow-sm">
                                <svg class="w-3.5 h-3.5 absolute left-3 top-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                            </div>

                            <select x-model="orderTimeFilter"
                                    class="bg-white text-xs font-bold text-slate-700 rounded-xl px-3 py-2.5 border border-slate-200/80 focus:outline-none focus:ring-2 focus:ring-red-500 shadow-sm">
                                <option value="semua">Semua Waktu</option>
                                <option value="today">Hari Ini</option>
                                <option value="yesterday">Kemarin</option>
                                <option value="7days">7 Hari Terakhir</option>
                                <option value="this_month">Bulan Ini</option>
                            </select>

                            <div class="flex items-center gap-1.5 bg-white border border-slate-200/80 rounded-xl px-2.5 py-1.5 text-xs shadow-sm">
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

                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/70 overflow-hidden">
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
                                                        <img src="{{ filter_var($firstItem->product->image, FILTER_VALIDATE_URL) ? $firstItem->product->image : asset('storage/' . ltrim($firstItem->product->image, '/')) }}" alt="Foto Produk" class="w-12 h-12 rounded-xl object-cover border border-slate-200/80 shrink-0 shadow-sm">
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
                                                                        class="text-[9px] font-black uppercase px-2 py-0.5 rounded bg-amber-600 text-white shadow-sm animate-pulse hover:bg-amber-700 transition">
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
                                                                class="p-2 rounded-xl {{ $payStatus === 'waiting_verification' ? 'bg-amber-600 text-white hover:bg-amber-700 shadow-sm' : 'bg-amber-50 text-amber-800 hover:bg-amber-600 hover:text-white' }} transition"
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
                                                                class="text-[11px] font-black uppercase tracking-wider px-3 py-2 rounded-xl border shadow-sm transition cursor-pointer outline-none focus:ring-2 focus:ring-red-500
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

                <!-- TAB 4: DATA PELANGGAN -->
                <div x-show="activeTab === 'pelanggan'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                        <div>
                            <h3 class="text-base font-black text-slate-900">Data Pelanggan Terdaftar</h3>
                            <p class="text-xs text-slate-500 mt-0.5">Daftar akun pembeli aktif beserta riwayat belanja di Sport Mania Distro.</p>
                        </div>
                        <div class="relative w-full sm:w-72">
                            <input type="text" x-model="searchCustomer" placeholder="Cari nama atau email pelanggan..." class="w-full bg-white text-xs text-slate-900 rounded-xl pl-10 pr-4 py-2.5 border border-slate-200/80 focus:outline-none focus:ring-2 focus:ring-red-500 transition shadow-sm font-medium">
                            <svg class="w-4 h-4 absolute left-3.5 top-2.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/70 overflow-hidden">
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

                <!-- TAB 5: RIWAYAT & LOG -->
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
                                    class="inline-flex items-center gap-1.5 bg-white hover:bg-slate-900 text-slate-700 hover:text-white border border-slate-200/80 px-3.5 py-2 rounded-xl text-xs font-bold transition shadow-sm">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                Export CSV
                            </button>
                            <div class="flex bg-white p-1 rounded-xl border border-slate-200/80 shadow-sm">
                                <button @click="subTab = 'transaksi'" 
                                        :class="subTab === 'transaksi' ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100'"
                                        class="px-4 py-2 rounded-lg text-xs font-bold uppercase transition">
                                    Riwayat Transaksi ({{ $finalizedOrders->count() }})
                                </button>
                                <button @click="subTab = 'log'" 
                                        :class="subTab === 'log' ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100'"
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
                                       class="w-full bg-white text-xs text-slate-900 rounded-xl pl-9 pr-4 py-2.5 border border-slate-200/80 focus:outline-none focus:ring-2 focus:ring-red-500 font-medium shadow-sm">
                                <svg class="w-3.5 h-3.5 absolute left-3 top-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                            </div>

                            <select x-model="historyTimeFilter"
                                    class="bg-white text-xs font-bold text-slate-700 rounded-xl px-3 py-2.5 border border-slate-200/80 focus:outline-none focus:ring-2 focus:ring-red-500 shadow-sm">
                                <option value="semua">Semua Waktu</option>
                                <option value="today">Hari Ini</option>
                                <option value="yesterday">Kemarin</option>
                                <option value="7days">7 Hari Terakhir</option>
                                <option value="this_month">Bulan Ini</option>
                            </select>

                            <div class="flex items-center gap-1.5 bg-white border border-slate-200/80 rounded-xl px-2.5 py-1.5 text-xs shadow-sm">
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

                        <div class="flex gap-1 bg-white p-1 rounded-xl border border-slate-200/80 shadow-sm self-start">
                            <button type="button" @click="historyFilter = 'semua'" :class="historyFilter === 'semua' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100'" class="px-3 py-1.5 rounded-lg text-[11px] font-bold uppercase transition">Semua</button>
                            <button type="button" @click="historyFilter = 'selesai'" :class="historyFilter === 'selesai' ? 'bg-emerald-600 text-white' : 'text-slate-600 hover:bg-slate-100'" class="px-3 py-1.5 rounded-lg text-[11px] font-bold uppercase transition">Selesai</button>
                            <button type="button" @click="historyFilter = 'dibatalkan'" :class="historyFilter === 'dibatalkan' ? 'bg-rose-600 text-white' : 'text-slate-600 hover:bg-slate-100'" class="px-3 py-1.5 rounded-lg text-[11px] font-bold uppercase transition">Dibatalkan</button>
                        </div>
                    </div>

                    <div x-show="subTab === 'transaksi'" class="bg-white rounded-2xl shadow-sm border border-slate-200/70 overflow-hidden">
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

                    <div x-show="subTab === 'log'" x-cloak class="bg-white rounded-2xl shadow-sm border border-slate-200/70 overflow-hidden">
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

                <!-- TAB 6: REKAP & GRAFIK BULANAN -->
                <div x-show="activeTab === 'rekap'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                    
                    <div class="bg-white rounded-2xl border border-slate-200/80 p-5 mb-6 shadow-sm flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-red-600"></span>
                                <h3 class="text-base font-black text-slate-900" x-text="'Laporan Performa Bulan ' + getMonthName(recapMonth) + ' ' + recapYear"></h3>
                            </div>
                            <p class="text-xs text-slate-500 mt-0.5">Analisis tren omzet harian, perbandingan tahunan, produk terlaris, dan metode pembayaran.</p>
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
                                    @click="exportMonthlyRecapCsv()"
                                    class="inline-flex items-center gap-1.5 bg-slate-900 hover:bg-red-600 text-white px-4 py-2.5 rounded-xl text-xs font-black uppercase tracking-wider transition shadow-sm">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                Export Rekap CSV
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-6">
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

                        <div class="bg-white p-6 rounded-2xl border border-slate-200/70 shadow-sm">
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 block">Transaksi Terhitung</span>
                            <p class="text-3xl font-black text-slate-900 tabular-nums mt-2">
                                <span x-text="getRecapSummary().validCount"></span>
                                <span class="text-xs font-semibold text-slate-400">order</span>
                            </p>
                            <p class="text-xs text-slate-500 mt-2 tabular-nums">
                                Selesai: <strong class="text-emerald-600" x-text="getRecapSummary().completedCount"></strong> • Batal: <strong class="text-rose-600" x-text="getRecapSummary().cancelledCount"></strong>
                            </p>
                        </div>

                        <div class="bg-white p-6 rounded-2xl border border-slate-200/70 shadow-sm">
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 block">Produk Terjual</span>
                            <p class="text-3xl font-black text-slate-900 tabular-nums mt-2">
                                <span x-text="getRecapSummary().totalItemsSold"></span>
                                <span class="text-xs font-semibold text-slate-400">pcs</span>
                            </p>
                            <p class="text-xs text-slate-500 mt-2">Total kuantitas item keluar</p>
                        </div>

                        <div class="bg-white p-6 rounded-2xl border border-slate-200/70 shadow-sm">
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 block">Rata-Rata Nilai Order</span>
                            <p class="text-2xl font-black text-red-600 tabular-nums mt-2" x-text="'Rp ' + Number(getRecapSummary().avgOrderValue).toLocaleString('id-ID')"></p>
                            <p class="text-xs text-slate-500 mt-2">Average Order Value (AOV)</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-6">
                        <div class="lg:col-span-7 bg-white rounded-2xl border border-slate-200/70 shadow-sm p-6">
                            <div class="mb-4">
                                <h4 class="text-sm font-black uppercase tracking-wider text-slate-900" x-text="'Grafik Penjualan Harian — ' + getMonthName(recapMonth) + ' ' + recapYear"></h4>
                                <p class="text-xs text-slate-500 mt-0.5">Batang merah menunjukkan nominal omzet harian, garis hitam menunjukkan frekuensi transaksi.</p>
                            </div>
                            <div class="h-72 relative w-full">
                                <canvas id="recapDailyChart"></canvas>
                            </div>
                        </div>

                        <div class="lg:col-span-5 bg-white rounded-2xl border border-slate-200/70 shadow-sm p-6">
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
                        <div class="lg:col-span-7 bg-white rounded-2xl border border-slate-200/70 shadow-sm p-6">
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
                                                    <p class="text-[11px] text-slate-500 tabular-nums" x-text="'Kontribusi omzet: Rp ' + Number(prod.revenue).toLocaleString('id-ID')"></p>
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

                        <div class="lg:col-span-5 bg-white rounded-2xl border border-slate-200/70 shadow-sm p-6 flex flex-col justify-between">
                            <div>
                                <h4 class="text-sm font-black uppercase tracking-wider text-slate-900">Sebaran Metode Pembayaran</h4>
                                <p class="text-xs text-slate-500 mt-0.5 mb-4">Proporsi pilihan metode bayar pelanggan bulan ini.</p>
                            </div>
                            <div class="h-60 relative w-full">
                                <canvas id="recapPaymentChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-slate-200/70 shadow-sm overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                            <div>
                                <h4 class="text-sm font-black uppercase tracking-wider text-slate-900">Rincian Rekap Per Tanggal</h4>
                                <p class="text-xs text-slate-500 mt-0.5">Rekapitulasi transaksi aktif berdasarkan tanggal pada bulan terpilih.</p>
                            </div>
                            <span class="text-xs font-bold text-slate-500 tabular-nums" x-text="getRecapSummary().dailyRows.length + ' hari aktif'"></span>
                        </div>
                        <div class="overflow-x-auto custom-scrollbar">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-slate-50/80 border-b border-slate-200/70 text-[10px] uppercase tracking-widest text-slate-500 font-bold">
                                        <th class="px-6 py-3.5">Tanggal</th>
                                        <th class="px-6 py-3.5 text-center">Order Masuk</th>
                                        <th class="px-6 py-3.5 text-center">Order Terhitung</th>
                                        <th class="px-6 py-3.5 text-center">Dibatalkan</th>
                                        <th class="px-6 py-3.5 text-center">Produk Terjual</th>
                                        <th class="px-6 py-3.5 text-right">Omzet Harian (Rp)</th>
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
                                        </tr>
                                    </template>
                                    <template x-if="getRecapSummary().dailyRows.length === 0">
                                        <tr>
                                            <td colspan="6" class="px-6 py-10 text-center text-slate-400">
                                                Tidak ada transaksi tercatat pada bulan <span x-text="getMonthName(recapMonth) + ' ' + recapYear"></span>.
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- =================================================================== -->
    <!-- MODAL QUICK PREVIEW KPI RINGKASAN PANEL                             -->
    <!-- =================================================================== -->
    <div x-show="dashModal.open"
         x-cloak
         class="fixed inset-0 z-[55] flex items-center justify-center p-4 bg-slate-950/75 backdrop-blur-sm print:hidden">
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
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/75 backdrop-blur-sm print:hidden">
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
                                                        class="w-full sm:w-auto bg-emerald-600 hover:bg-emerald-700 text-white font-black uppercase tracking-wider text-[11px] px-3.5 py-2.5 rounded-xl transition shadow-sm">
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
         class="fixed inset-0 z-[65] flex items-center justify-center p-4 bg-slate-950/85 backdrop-blur-sm print:hidden">
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
         class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-slate-950/75 backdrop-blur-sm print:hidden">
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

</body>
</html>