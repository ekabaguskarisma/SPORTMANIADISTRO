<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan Saya - Sport Mania Distro</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        [x-cloak] { display: none !important; }
        .tabular-nums { font-variant-numeric: tabular-nums; }

        @media print {
            @page { margin: 10mm; }
            body { background: #ffffff !important; color: #000000 !important; }
        }
    </style>

    @php
        $orders = $orders ?? \App\Models\Order::with(['items.product'])
            ->where('user_id', auth()->id())
            ->latest()
            ->get();

        // Ambil daftar ulasan milik user (adaptif terhadap ada/tidaknya kolom order_id)
        $userReviewsMap = collect();
        $hasReviewOrderId = false;
        if (\Illuminate\Support\Facades\Schema::hasTable('reviews') && $orders->isNotEmpty()) {
            $hasReviewOrderId = \Illuminate\Support\Facades\Schema::hasColumn('reviews', 'order_id');
            if ($hasReviewOrderId) {
                $userReviewsMap = \App\Models\Review::where('user_id', auth()->id())
                    ->whereIn('order_id', $orders->pluck('id'))
                    ->get()
                    ->keyBy(fn ($rev) => $rev->order_id . '-' . $rev->product_id);
            } else {
                $productIds = $orders->flatMap(fn ($o) => $o->items->pluck('product_id'))->filter()->unique();
                if ($productIds->isNotEmpty()) {
                    $userReviewsMap = \App\Models\Review::where('user_id', auth()->id())
                        ->whereIn('product_id', $productIds)
                        ->get()
                        ->keyBy(fn ($rev) => 'prod-' . $rev->product_id);
                }
            }
        }

        // Hitung jumlah pesanan per status untuk badge filter & empty state tab
        $statusCounts = [
            'semua'      => $orders->count(),
            'pending'    => $orders->filter(fn($o) => strtolower($o->status ?? 'pending') === 'pending')->count(),
            'diproses'   => $orders->filter(fn($o) => strtolower($o->status ?? '') === 'diproses')->count(),
            'dikirim'    => $orders->filter(fn($o) => strtolower($o->status ?? '') === 'dikirim')->count(),
            'selesai'    => $orders->filter(fn($o) => strtolower($o->status ?? '') === 'selesai')->count(),
            'dibatalkan' => $orders->filter(fn($o) => in_array(strtolower($o->status ?? ''), ['dibatalkan', 'batal'], true))->count(),
        ];

        // Siapkan payload pesanan untuk Cetak Invoice Mandiri Pelanggan
        $customerOrdersMap = $orders->mapWithKeys(function ($order) {
            $code = filled($order->order_number ?? null)
                ? $order->order_number
                : (filled($order->invoice_number ?? null) ? $order->invoice_number : ('ORD-' . str_pad($order->id, 4, '0', STR_PAD_LEFT)));

            $customerName = filled($order->recipient_name ?? null) ? $order->recipient_name
                : (filled($order->name ?? null) ? $order->name : (auth()->user()->name ?? 'Pelanggan'));

            $phoneRaw = filled($order->phone ?? null) ? $order->phone : (auth()->user()->phone ?? '-');
            $cityVal = filled($order->city ?? null) ? $order->city : '-';
            $addressVal = filled($order->shipping_address ?? null) ? $order->shipping_address
                : (filled($order->address ?? null) ? $order->address : 'Ambil langsung di Store Malang');
            $paymentVal = filled($order->payment_method ?? null) ? $order->payment_method : 'TRANSFER';
            $isCod = strtolower($paymentVal) === 'cod';

            $rawPayStatus = strtolower(trim((string) ($order->payment_status ?? '')));
            $normalizedPayStatus = match(true) {
                $rawPayStatus === 'paid' => 'paid',
                $rawPayStatus === 'rejected' => 'rejected',
                $rawPayStatus === 'pending_verification' => 'pending_verification',
                !empty($order->payment_proof) && !in_array($rawPayStatus, ['paid', 'rejected'], true) => 'pending_verification',
                default => 'unpaid',
            };

            $paymentStatusLabel = $isCod
                ? 'BAYAR DI TEMPAT (COD)'
                : match($normalizedPayStatus) {
                    'paid'                 => 'LUNAS (TERVERIFIKASI)',
                    'pending_verification' => 'MENUNGGU VERIFIKASI',
                    'rejected'             => 'BUKTI DITOLAK',
                    default                => 'BELUM DIBAYAR',
                };

            $shippingMethodVal = filled($order->shipping_method ?? null) ? strtoupper($order->shipping_method) : '-';
            $resiVal = filled($order->tracking_number ?? null) ? $order->tracking_number : ($order->resi ?? '');
            $notesVal = filled($order->notes ?? null) ? $order->notes : '-';
            $subtotalPrice = (float) ($order->subtotal ?? 0);
            $shippingCost = (float) ($order->shipping_cost ?? 0);
            $totalOrderPrice = (float) ($order->total_price ?? $order->total_amount ?? 0);

            return [
                $order->id => [
                    'id'                   => $order->id,
                    'code'                 => $code,
                    'customer'             => $customerName,
                    'phone'                => $phoneRaw,
                    'city'                 => $cityVal,
                    'address'              => $addressVal,
                    'payment'              => strtoupper($paymentVal),
                    'payment_status_label' => $paymentStatusLabel,
                    'shipping_method'      => $shippingMethodVal,
                    'tracking_number'      => $resiVal,
                    'notes'                => $notesVal,
                    'status'               => strtolower($order->status ?? 'pending'),
                    'date'                 => optional($order->created_at)->format('d M Y, H:i') ?? '-',
                    'subtotal'             => $subtotalPrice,
                    'shipping_cost'        => $shippingCost,
                    'total'                => $totalOrderPrice,
                    'items'                => $order->items ? $order->items->map(function ($item) {
                        $qty = (int) ($item->quantity ?? $item->qty ?? 1);
                        $price = (float) ($item->price ?? 0);
                        $itemName = filled($item->product_name ?? null)
                            ? $item->product_name
                            : (optional($item->product)->name ?? 'Produk');
                        $itemSize = $item->variant_size ?? $item->size ?? null;
                        $itemColor = $item->variant_color ?? $item->color ?? null;

                        return [
                            'name'     => $itemName,
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
        window.__SM_CUSTOMER_ORDERS__ = @json($customerOrdersMap);
        window.__SM_STATUS_COUNTS__ = @json($statusCounts);

        function customerOrdersPage() {
            return {
                statusFilter: 'semua',
                counts: window.__SM_STATUS_COUNTS__ || {},
                copiedResiId: null,
                copiedRekening: '',
                confirmModal: null,
                reviewModal: null,
                paymentModal: null,
                printOrder: null,
                submitting: false,
                cartItems: JSON.parse(localStorage.getItem('cart_items')) || [],

                syncCart() {
                    this.cartItems = JSON.parse(localStorage.getItem('cart_items')) || [];
                },

                get cartCount() {
                    return this.cartItems.reduce((acc, item) => acc + Number(item.qty || 0), 0);
                },

                copyResi(text, orderId) {
                    if (!text) return;
                    navigator.clipboard.writeText(text);
                    this.copiedResiId = orderId;
                    setTimeout(() => { this.copiedResiId = null; }, 1800);
                },

                copyRekening(nomor) {
                    if (!nomor) return;
                    navigator.clipboard.writeText(nomor);
                    this.copiedRekening = nomor;
                    setTimeout(() => { this.copiedRekening = ''; }, 1800);
                },

                openPaymentModal(data) {
                    this.submitting = false;
                    this.copiedRekening = '';
                    this.paymentModal = {
                        orderId: data.orderId,
                        orderCode: data.orderCode,
                        paymentMethod: data.paymentMethod,
                        totalFormatted: data.totalFormatted,
                        actionUrl: data.actionUrl,
                        existingProofUrl: data.existingProofUrl || null,
                        paymentStatus: data.paymentStatus || 'unpaid',
                        rejectedReason: data.rejectedReason || null,
                        canUpload: data.canUpload !== false,
                        previewUrl: null,
                        fileName: '',
                        fileError: ''
                    };
                },

                handlePaymentFileChange(event) {
                    const file = event.target.files[0];
                    if (!file) return;
                    this.paymentModal.fileError = '';

                    const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
                    if (!allowedTypes.includes(file.type)) {
                        this.paymentModal.fileError = 'Format file harus JPG, JPEG, PNG, atau WEBP.';
                        event.target.value = '';
                        return;
                    }

                    if (file.size > 4 * 1024 * 1024) {
                        this.paymentModal.fileError = 'Ukuran foto maksimal 4 MB.';
                        event.target.value = '';
                        return;
                    }

                    if (this.paymentModal.previewUrl) {
                        URL.revokeObjectURL(this.paymentModal.previewUrl);
                    }

                    this.paymentModal.fileName = file.name;
                    this.paymentModal.previewUrl = URL.createObjectURL(file);
                },

                submitPaymentForm(event) {
                    if (!this.paymentModal || !this.paymentModal.fileName) {
                        event.preventDefault();
                        if (this.paymentModal) {
                            this.paymentModal.fileError = 'Silakan pilih foto bukti pembayaran terlebih dahulu.';
                        }
                        return;
                    }
                    this.submitting = true;
                },

                closePaymentModal() {
                    if (this.submitting) return;
                    if (this.paymentModal && this.paymentModal.previewUrl) {
                        URL.revokeObjectURL(this.paymentModal.previewUrl);
                    }
                    this.paymentModal = null;
                },

                openConfirmModal(type, orderCode, actionUrl) {
                    this.submitting = false;
                    if (type === 'cancel') {
                        this.confirmModal = {
                            type: 'cancel',
                            badge: 'Pembatalan Pesanan',
                            title: 'Batalkan #' + orderCode + '?',
                            message: 'Yakin ingin membatalkan pesanan #' + orderCode + '? Stok varian produk akan otomatis dikembalikan ke etalase.',
                            confirmText: 'Ya, Batalkan Pesanan',
                            actionUrl: actionUrl
                        };
                    } else {
                        this.confirmModal = {
                            type: 'complete',
                            badge: 'Konfirmasi Penerimaan',
                            title: 'Pesanan #' + orderCode + ' Sudah Diterima?',
                            message: 'Pastikan paket #' + orderCode + ' sudah diterima dengan baik. Setelah dikonfirmasi selesai, kamu langsung bisa memberi rating & ulasan produk.',
                            confirmText: 'Ya, Pesanan Diterima',
                            actionUrl: actionUrl
                        };
                    }
                },

                openReviewModal(productId, productName, orderId) {
                    this.submitting = false;
                    this.reviewModal = {
                        product_id: productId,
                        product_name: productName,
                        order_id: orderId,
                        rating: 5,
                        comment: ''
                    };
                },

                submitReviewForm(event) {
                    if (!this.reviewModal || !this.reviewModal.comment.trim()) {
                        event.preventDefault();
                        return;
                    }
                    this.reviewModal.comment = this.reviewModal.comment.trim();
                    this.submitting = true;
                },

                printInvoice(orderId) {
                    const data = window.__SM_CUSTOMER_ORDERS__[orderId];
                    if (!data) return;
                    this.printOrder = data;
                    this.$nextTick(() => {
                        requestAnimationFrame(() => {
                            window.print();
                        });
                    });
                }
            };
        }
    </script>
</head>

<body class="bg-slate-50/90 text-slate-800 antialiased selection:bg-red-600 selection:text-white"
      x-data="customerOrdersPage()"
      @cart-updated.window="syncCart()"
      @storage.window="syncCart()"
      @pageshow.window="syncCart()"
      @afterprint.window="printOrder = null">

    <!-- ========================================================= -->
    <!-- AREA UTAMA HALAMAN PELANGGAN (TERSEMBUNYI SAAT PRINT)      -->
    <!-- ========================================================= -->
    <div class="min-h-screen flex flex-col print:hidden">

        <!-- Navbar Top -->
        <header class="sticky top-0 z-30 bg-slate-950/95 backdrop-blur-md border-b border-slate-800 text-white">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 py-4 flex items-center justify-between gap-4">
                <a href="{{ route('home') }}" class="flex items-center gap-2.5 hover:opacity-90 transition">
                    <div class="p-1 rounded-lg bg-slate-900 border border-slate-800">
                        <img src="{{ asset('image/LOGO.jpeg') }}" alt="Logo" class="h-7 w-auto object-contain rounded">
                    </div>
                    <span class="text-xl font-black tracking-tighter uppercase italic">
                        <span class="text-red-500">SPORT</span><span class="text-white">MANIA</span>
                    </span>
                </a>

                <div class="flex items-center gap-3">
                    <a href="{{ route('home') }}" class="text-xs font-bold text-slate-300 hover:text-white bg-slate-900 hover:bg-slate-800 px-3.5 py-2 rounded-xl border border-slate-800 transition">
                        ← Lanjut Belanja
                    </a>
                    <a href="{{ route('customer.cart') }}" class="relative p-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-slate-300 hover:text-white border border-slate-800 transition" title="Keranjang Belanja">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        <span x-show="cartCount > 0" x-cloak class="absolute -top-1.5 -right-1.5 bg-red-600 text-white text-[9px] font-black px-1.5 py-0.5 rounded-full leading-none" x-text="cartCount"></span>
                    </a>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-1 max-w-5xl w-full mx-auto px-4 sm:px-6 py-8 sm:py-10">

            @if(session('success'))
                <div x-data="{ show: true }"
                     x-show="show"
                     x-transition.opacity.duration.200ms
                     class="mb-6 p-4 bg-emerald-50 border border-emerald-200/80 text-emerald-800 rounded-2xl font-semibold flex items-center justify-between gap-3 shadow-sm text-sm">
                    <div class="flex items-center gap-3">
                        <div class="w-7 h-7 rounded-lg bg-emerald-500 text-white flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                        </div>
                        <span>{{ session('success') }}</span>
                    </div>
                    <button type="button" @click="show = false" class="text-emerald-600 hover:text-emerald-900 text-xs font-black px-2 py-1 rounded-lg hover:bg-emerald-100 transition">✕</button>
                </div>
            @endif

            @if(session('error'))
                <div x-data="{ show: true }"
                     x-show="show"
                     x-transition.opacity.duration.200ms
                     class="mb-6 p-4 bg-rose-50 border border-rose-200/80 text-rose-800 rounded-2xl font-semibold flex items-center justify-between gap-3 shadow-sm text-sm">
                    <div class="flex items-center gap-3">
                        <div class="w-7 h-7 rounded-lg bg-rose-500 text-white flex items-center justify-center shrink-0 font-black">!</div>
                        <span>{{ session('error') }}</span>
                    </div>
                    <button type="button" @click="show = false" class="text-rose-600 hover:text-rose-900 text-xs font-black px-2 py-1 rounded-lg hover:bg-rose-100 transition">✕</button>
                </div>
            @endif

            @if($errors->any())
                <div class="mb-6 p-4 bg-rose-50 border border-rose-200/80 text-rose-800 rounded-2xl font-semibold text-xs">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Header & Filter Status -->
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-7">
                <div>
                    <span class="text-[11px] font-black uppercase tracking-widest text-red-600">Akun Pelanggan</span>
                    <h1 class="text-2xl font-black text-slate-900 tracking-tight mt-0.5">Riwayat Pesanan Saya</h1>
                    <p class="text-xs text-slate-500 mt-1">Unggah bukti pembayaran, pantau status pengiriman, salin nomor resi kurir, dan cetak invoice Anda.</p>
                </div>

                <!-- Tab Filter Status -->
                <div class="flex flex-wrap gap-1.5 bg-white p-1.5 rounded-2xl border border-slate-200/80 shadow-sm">
                    @foreach(['semua' => 'Semua', 'pending' => 'Pending', 'diproses' => 'Diproses', 'dikirim' => 'Dikirim', 'selesai' => 'Selesai', 'dibatalkan' => 'Dibatalkan'] as $key => $label)
                        <button type="button"
                                @click="statusFilter = '{{ $key }}'"
                                :class="statusFilter === '{{ $key }}' ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100'"
                                class="px-3 py-1.5 rounded-xl text-[11px] font-bold uppercase transition inline-flex items-center gap-1.5">
                            <span>{{ $label }}</span>
                            @if(($statusCounts[$key] ?? 0) > 0)
                                <span class="text-[9px] px-1.5 py-0.2 rounded-full"
                                      :class="statusFilter === '{{ $key }}' ? 'bg-red-600 text-white' : 'bg-slate-200 text-slate-700'">
                                    {{ $statusCounts[$key] }}
                                </span>
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>

            <!-- Empty State Khusus Ketika Filter Tab Kosong -->
            @if($orders->isNotEmpty())
                <div x-show="statusFilter !== 'semua' && (counts[statusFilter] || 0) === 0"
                     x-cloak
                     class="bg-white rounded-3xl border border-slate-200/80 p-10 text-center shadow-sm mb-5">
                    <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3 text-xl">
                        📦
                    </div>
                    <h3 class="text-sm font-black text-slate-900">Belum Ada Pesanan di Tab Ini</h3>
                    <p class="text-xs text-slate-500 mt-1 mb-4">Tidak ada transaksi dengan status <span class="font-bold uppercase text-slate-700" x-text="statusFilter"></span> saat ini.</p>
                    <button type="button"
                            @click="statusFilter = 'semua'"
                            class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-700 hover:text-white bg-slate-100 hover:bg-slate-900 px-4 py-2 rounded-xl transition">
                        Tampilkan Semua Pesanan
                    </button>
                </div>
            @endif

            <!-- Daftar Kartu Pesanan -->
            <div class="space-y-5">
                @forelse($orders as $order)
                    @php
                        $statusRaw = strtolower($order->status ?? 'pending');
                        $statusNormalized = $statusRaw === 'batal' ? 'dibatalkan' : $statusRaw;
                        $orderCode = filled($order->order_number ?? null) ? $order->order_number : (filled($order->invoice_number ?? null) ? $order->invoice_number : ('ORD-' . str_pad($order->id, 4, '0', STR_PAD_LEFT)));
                        $resi = $order->tracking_number ?? $order->resi ?? null;
                        $totalOrderPrice = (float) ($order->total_price ?? $order->total_amount ?? 0);
                        $totalFormatted = 'Rp ' . number_format($totalOrderPrice, 0, ',', '.');
                        $paymentMethodUpper = strtoupper($order->payment_method ?? 'TRANSFER');
                        $isCod = strtolower($order->payment_method ?? '') === 'cod';

                        $rawPayStatus = strtolower(trim((string) ($order->payment_status ?? '')));
                        $paymentStatus = match(true) {
                            $rawPayStatus === 'paid' => 'paid',
                            $rawPayStatus === 'rejected' => 'rejected',
                            $rawPayStatus === 'pending_verification' => 'pending_verification',
                            !empty($order->payment_proof) && !in_array($rawPayStatus, ['paid', 'rejected'], true) => 'pending_verification',
                            default => 'unpaid',
                        };

                        $proofUrl = filled($order->payment_proof ?? null)
                            ? (filter_var($order->payment_proof, FILTER_VALIDATE_URL) ? $order->payment_proof : asset('storage/' . $order->payment_proof))
                            : null;
                        $canUploadPayment = !$isCod && $statusNormalized !== 'dibatalkan' && $paymentStatus !== 'paid';

                        $shippingLabel = match(strtolower($order->shipping_method ?? '')) {
                            'pickup'  => 'AMBIL DI STORE',
                            'jne'     => 'JNE REG',
                            'sicepat' => 'SICEPAT',
                            default   => strtoupper($order->shipping_method ?? 'KURIR'),
                        };

                        $waCustomerName = filled($order->recipient_name ?? null)
                            ? $order->recipient_name
                            : (filled($order->name ?? null) ? $order->name : (auth()->user()->name ?? '-'));
                        $waProofNote = $proofUrl ? 'Sudah diunggah di website' : 'Belum diunggah';
                        $waText = "Halo Admin Sport Mania Distro, saya ingin konfirmasi pembayaran pesanan:\n"
                            . "• No. Pesanan: #{$orderCode}\n"
                            . "• Nama: {$waCustomerName}\n"
                            . "• Metode Bayar: {$paymentMethodUpper} ({$waProofNote})\n"
                            . "• Total Tagihan: {$totalFormatted}";
                        $waAdminUrl = 'https://wa.me/6282234548092?text=' . rawurlencode($waText);

                        $defaultBadgeClass = match($statusNormalized) {
                            'pending'    => 'bg-amber-50 text-amber-700 border-amber-200',
                            'diproses'   => 'bg-blue-50 text-blue-700 border-blue-200',
                            'dikirim'    => 'bg-purple-50 text-purple-700 border-purple-200',
                            'selesai'    => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                            'dibatalkan' => 'bg-rose-50 text-rose-700 border-rose-200',
                            default      => 'bg-slate-100 text-slate-700 border-slate-200',
                        };
                        $badgeColor = filled($order->status_color ?? null) ? $order->status_color : $defaultBadgeClass;

                        $paymentModalPayload = [
                            'orderId'          => $order->id,
                            'orderCode'        => $orderCode,
                            'paymentMethod'    => $paymentMethodUpper,
                            'totalFormatted'   => $totalFormatted,
                            'actionUrl'        => route('customer.orders.payment-proof', $order->id),
                            'existingProofUrl' => $proofUrl,
                            'paymentStatus'    => $paymentStatus,
                            'rejectedReason'   => $order->payment_rejected_reason ?? null,
                            'canUpload'        => $canUploadPayment,
                        ];
                    @endphp

                    <div x-show="statusFilter === 'semua' || statusFilter === '{{ $statusNormalized }}'"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 translate-y-2"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         class="bg-white rounded-3xl border border-slate-200/80 shadow-sm hover:shadow-md transition-shadow overflow-hidden">

                        <!-- Top Bar Card -->
                        <div class="px-6 py-4 bg-slate-50/80 border-b border-slate-200/70 flex flex-wrap items-center justify-between gap-3">
                            <div class="flex flex-wrap items-center gap-2.5">
                                <span class="text-xs font-black text-slate-900 tracking-tight">#{{ $orderCode }}</span>
                                <span class="text-slate-300">•</span>
                                <span class="text-xs text-slate-500 font-medium tabular-nums">{{ optional($order->created_at)->format('d M Y, H:i') ?? '-' }}</span>
                                <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-md bg-white border border-slate-200 text-slate-600">
                                    {{ $paymentMethodUpper }}
                                </span>
                                <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-md bg-slate-100 border border-slate-200/80 text-slate-600">
                                    {{ $shippingLabel }}
                                </span>
                            </div>

                            <div class="flex items-center gap-2">
                                <!-- Badge Status Pembayaran -->
                                @if(!$isCod)
                                    @if($paymentStatus === 'paid')
                                        <span class="text-[10px] font-black uppercase tracking-wider px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            ✓ Lunas
                                        </span>
                                    @elseif($paymentStatus === 'pending_verification')
                                        <span class="text-[10px] font-black uppercase tracking-wider px-2.5 py-1 rounded-full bg-blue-50 text-blue-700 border border-blue-200">
                                            ⏳ Verifikasi Bayar
                                        </span>
                                    @elseif($paymentStatus === 'rejected')
                                        <span class="text-[10px] font-black uppercase tracking-wider px-2.5 py-1 rounded-full bg-rose-50 text-rose-700 border border-rose-200">
                                            ! Bukti Ditolak
                                        </span>
                                    @elseif($statusNormalized !== 'dibatalkan')
                                        <span class="text-[10px] font-black uppercase tracking-wider px-2.5 py-1 rounded-full bg-amber-50 text-amber-700 border border-amber-200">
                                            Belum Bayar
                                        </span>
                                    @endif
                                @endif

                                <!-- Badge Status Pesanan -->
                                <span class="text-[10px] font-black uppercase tracking-widest px-3 py-1 rounded-full border {{ $badgeColor }}">
                                    {{ strtoupper($statusNormalized) }}
                                </span>

                                <!-- Tombol Cetak Invoice -->
                                <button type="button"
                                        @click="printInvoice({{ $order->id }})"
                                        class="inline-flex items-center gap-1.5 text-[11px] font-bold text-slate-700 hover:text-white bg-white hover:bg-slate-900 px-3 py-1.5 rounded-xl border border-slate-200 transition shadow-sm"
                                        title="Cetak Invoice Pembayaran">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                                    Invoice
                                </button>
                            </div>
                        </div>

                        <!-- Banner Status Pembayaran & Unggah Bukti Transfer -->
                        @if(!$isCod && ($statusNormalized !== 'dibatalkan' || $proofUrl))
                            @if($paymentStatus === 'unpaid' && $statusNormalized !== 'dibatalkan')
                                <div class="px-6 py-3.5 bg-amber-50/90 border-b border-amber-200/70 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                    <div class="flex items-start sm:items-center gap-2.5 text-xs text-amber-900">
                                        <span class="w-6 h-6 rounded-lg bg-amber-500 text-white flex items-center justify-center shrink-0 font-black text-xs mt-0.5 sm:mt-0">Rp</span>
                                        <div>
                                            <p class="font-bold">Menunggu Pembayaran ({{ $paymentMethodUpper }})</p>
                                            <p class="text-[11px] text-amber-700">Lakukan pembayaran sebesar <strong>{{ $totalFormatted }}</strong> lalu unggah bukti transfer agar pesanan segera diproses.</p>
                                        </div>
                                    </div>
                                    <button type="button"
                                            @click="openPaymentModal(@js($paymentModalPayload))"
                                            class="inline-flex items-center justify-center gap-1.5 text-[11px] font-black uppercase tracking-wider text-white bg-red-600 hover:bg-red-700 px-4 py-2 rounded-xl transition shadow-sm shrink-0">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                                        Unggah Bukti Bayar
                                    </button>
                                </div>
                            @elseif($paymentStatus === 'pending_verification')
                                <div class="px-6 py-3 bg-blue-50/80 border-b border-blue-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                                    <div class="flex items-center gap-2.5 text-blue-900">
                                        <span class="w-6 h-6 rounded-lg bg-blue-600 text-white flex items-center justify-center shrink-0">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        </span>
                                        <div>
                                            <span class="font-bold">Bukti Pembayaran Sedang Diverifikasi Admin</span>
                                            <span class="text-[11px] text-blue-700 block sm:inline sm:ml-1.5">• Anda dapat memeriksa atau mengganti foto bukti jika keliru.</span>
                                        </div>
                                    </div>
                                    <button type="button"
                                            @click="openPaymentModal(@js($paymentModalPayload))"
                                            class="inline-flex items-center justify-center gap-1.5 text-[11px] font-bold text-blue-700 hover:text-white bg-white hover:bg-blue-600 px-3 py-1.5 rounded-xl border border-blue-200 transition shrink-0">
                                        Lihat / Ganti Bukti
                                    </button>
                                </div>
                            @elseif($paymentStatus === 'rejected')
                                <div class="px-6 py-3.5 bg-rose-50/90 border-b border-rose-200/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                                    <div class="flex items-start gap-2.5 text-rose-900">
                                        <span class="w-6 h-6 rounded-lg bg-rose-600 text-white flex items-center justify-center shrink-0 font-black mt-0.5">!</span>
                                        <div>
                                            <p class="font-bold">Bukti Pembayaran Ditolak Admin</p>
                                            @if(filled($order->payment_rejected_reason))
                                                <p class="text-[11px] text-rose-700 mt-0.5">Alasan: <strong>"{{ $order->payment_rejected_reason }}"</strong></p>
                                            @endif
                                        </div>
                                    </div>
                                    @if($statusNormalized !== 'dibatalkan')
                                        <button type="button"
                                                @click="openPaymentModal(@js($paymentModalPayload))"
                                                class="inline-flex items-center justify-center gap-1.5 text-[11px] font-black uppercase tracking-wider text-white bg-rose-600 hover:bg-rose-700 px-4 py-2 rounded-xl transition shadow-sm shrink-0">
                                            Unggah Ulang Bukti
                                        </button>
                                    @endif
                                </div>
                            @elseif($paymentStatus === 'paid' && $proofUrl)
                                <div class="px-6 py-2.5 bg-emerald-50/70 border-b border-emerald-100 flex items-center justify-between gap-3 text-xs">
                                    <div class="flex items-center gap-2 text-emerald-800">
                                        <span class="w-5 h-5 rounded-md bg-emerald-600 text-white flex items-center justify-center text-[10px] font-black shrink-0">✓</span>
                                        <span class="font-semibold">Pembayaran telah diverifikasi & dinyatakan lunas oleh Admin.</span>
                                    </div>
                                    <button type="button"
                                            @click="openPaymentModal(@js($paymentModalPayload))"
                                            class="text-[11px] font-bold text-emerald-700 hover:text-emerald-950 underline underline-offset-2 shrink-0">
                                        Lihat Bukti Bayar
                                    </button>
                                </div>
                            @endif
                        @endif

                        <!-- Banner Nomor Resi Kurir -->
                        @if(filled($resi))
                            <div class="px-6 py-3 bg-purple-50/80 border-b border-purple-100 flex flex-wrap items-center justify-between gap-2 text-xs">
                                <div class="flex items-center gap-2 text-purple-900">
                                    <span class="w-6 h-6 rounded-lg bg-purple-600 text-white flex items-center justify-center shrink-0">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"></path></svg>
                                    </span>
                                    <span class="font-semibold">Nomor Resi Pengiriman:</span>
                                    <span class="font-black tracking-wide text-purple-950 bg-white px-2.5 py-0.5 rounded-md border border-purple-200">{{ $resi }}</span>
                                </div>

                                <button type="button"
                                        @click="copyResi(@js($resi), {{ $order->id }})"
                                        class="text-[11px] font-bold text-purple-700 hover:text-purple-900 bg-white hover:bg-purple-100 px-3 py-1 rounded-lg border border-purple-200 transition">
                                    <span x-show="copiedResiId !== {{ $order->id }}">Salin Resi</span>
                                    <span x-show="copiedResiId === {{ $order->id }}" x-cloak class="text-emerald-600">✓ Tersalin</span>
                                </button>
                            </div>
                        @endif

                        <!-- Daftar Produk dalam Pesanan -->
                        <div class="divide-y divide-slate-100 px-6">
                            @foreach($order->items as $item)
                                @php
                                    $qty = (int) ($item->quantity ?? $item->qty ?? 1);
                                    $price = (float) ($item->price ?? 0);
                                    $prod = $item->product;
                                    $displayProductName = filled($item->product_name ?? null)
                                        ? $item->product_name
                                        : ($prod->name ?? 'Produk Tidak Tersedia');
                                    $itemSize = $item->variant_size ?? $item->size ?? 'All Size';
                                    $itemColor = $item->variant_color ?? $item->color ?? '-';
                                    $prodImg = ($prod && $prod->image)
                                        ? (filter_var($prod->image, FILTER_VALIDATE_URL) ? $prod->image : asset('storage/' . $prod->image))
                                        : null;
                                    $existingReview = null;
                                    if ($prod) {
                                        $existingReview = $hasReviewOrderId
                                            ? $userReviewsMap->get($order->id . '-' . $prod->id)
                                            : $userReviewsMap->get('prod-' . $prod->id);
                                    }
                                @endphp
                                <div class="py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                    <div class="flex items-center gap-4">
                                        @if($prodImg)
                                            <img src="{{ $prodImg }}" alt="{{ $displayProductName }}" class="w-16 h-16 rounded-2xl object-cover border border-slate-200/80 shrink-0 shadow-sm">
                                        @else
                                            <div class="w-16 h-16 rounded-2xl bg-slate-100 border border-slate-200/80 flex items-center justify-center text-2xl shrink-0">
                                                👕
                                            </div>
                                        @endif

                                        <div>
                                            @if($prod)
                                                <a href="{{ route('produk.show', $prod->id) }}" class="font-bold text-slate-900 hover:text-red-600 text-sm transition">
                                                    {{ $displayProductName }}
                                                </a>
                                            @else
                                                <p class="font-bold text-slate-600 text-sm">{{ $displayProductName }}</p>
                                            @endif

                                            <div class="flex flex-wrap items-center gap-1.5 mt-1">
                                                <span class="text-[10px] font-bold bg-slate-100 text-slate-600 px-2 py-0.5 rounded-md">
                                                    Ukuran: {{ $itemSize }}
                                                </span>
                                                @if(!empty($itemColor) && $itemColor !== '-')
                                                    <span class="text-[10px] font-bold bg-slate-100 text-slate-600 px-2 py-0.5 rounded-md">
                                                        Warna: {{ $itemColor }}
                                                    </span>
                                                @endif
                                            </div>

                                            <p class="text-xs text-slate-500 mt-1 tabular-nums">
                                                {{ $qty }} pcs × Rp {{ number_format($price, 0, ',', '.') }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="flex sm:flex-col items-center sm:items-end justify-between gap-2">
                                        <p class="font-black text-slate-900 text-sm tabular-nums">
                                            Rp {{ number_format($price * $qty, 0, ',', '.') }}
                                        </p>

                                        <!-- Tombol / Status Ulasan jika pesanan sudah SELESAI -->
                                        @if($statusNormalized === 'selesai' && $prod)
                                            @if($existingReview)
                                                <span class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-700 bg-emerald-50 px-3 py-1 rounded-xl border border-emerald-200">
                                                    ✓ ★ {{ $existingReview->rating }}/5 Diulas
                                                </span>
                                            @else
                                                <button type="button"
                                                        @click="openReviewModal({{ $prod->id }}, @js($displayProductName), {{ $order->id }})"
                                                        class="inline-flex items-center gap-1 text-[11px] font-bold text-amber-700 hover:text-white bg-amber-50 hover:bg-amber-500 px-3 py-1.5 rounded-xl border border-amber-200 transition">
                                                    ★ Beri Ulasan
                                                </button>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <!-- Footer Card: Alamat, Catatan, Tombol Aksi Pelanggan & Total -->
                        <div class="px-6 py-4 bg-slate-50/60 border-t border-slate-100 flex flex-col md:flex-row md:items-center justify-between gap-4">
                            <div class="text-xs text-slate-500 max-w-md space-y-1">
                                <div>
                                    <span class="font-bold text-slate-700">Alamat Pengiriman:</span>
                                    <span>{{ $order->shipping_address ?? $order->address ?? 'Ambil langsung di Store Malang' }}</span>
                                </div>
                                @if(filled($order->notes ?? null))
                                    <div>
                                        <span class="font-bold text-slate-700">Catatan:</span>
                                        <span class="italic text-slate-600">{{ $order->notes }}</span>
                                    </div>
                                @endif
                            </div>

                            <div class="flex flex-wrap items-center justify-between md:justify-end gap-4">
                                <!-- Aksi Mandiri Pelanggan Berdasarkan Status -->
                                @if($statusNormalized === 'pending')
                                    <div class="flex flex-wrap items-center gap-2">
                                        <a href="{{ $waAdminUrl }}"
                                           target="_blank"
                                           rel="noopener"
                                           class="inline-flex items-center gap-1.5 text-[11px] font-bold text-white bg-emerald-600 hover:bg-emerald-700 px-3.5 py-2 rounded-xl transition shadow-sm">
                                            Chat Admin WA
                                        </a>

                                        <button type="button"
                                                @click="openConfirmModal('cancel', @js($orderCode), @js(route('customer.orders.cancel', $order->id)))"
                                                class="inline-flex items-center gap-1 text-[11px] font-bold text-rose-600 hover:text-white bg-rose-50 hover:bg-rose-600 px-3 py-2 rounded-xl border border-rose-200 transition">
                                            Batalkan
                                        </button>
                                    </div>
                                @elseif($statusNormalized === 'dikirim' || ($statusNormalized === 'diproses' && strtolower($order->shipping_method ?? '') === 'pickup'))
                                    <button type="button"
                                            @click="openConfirmModal('complete', @js($orderCode), @js(route('customer.orders.complete', $order->id)))"
                                            class="inline-flex items-center gap-1.5 text-[11px] font-black uppercase tracking-wider text-white bg-slate-900 hover:bg-emerald-600 px-4 py-2 rounded-xl transition shadow-sm">
                                        ✓ Pesanan Diterima
                                    </button>
                                @endif

                                <div class="text-left md:text-right">
                                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">Total Pembayaran</span>
                                    <span class="text-lg font-black text-red-600 tabular-nums">{{ $totalFormatted }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="bg-white rounded-3xl border border-slate-200/80 p-12 text-center shadow-sm">
                        <div class="w-16 h-16 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-4 text-2xl">
                            🛍️
                        </div>
                        <h3 class="text-base font-black text-slate-900">Belum Ada Riwayat Pesanan</h3>
                        <p class="text-xs text-slate-500 mt-1 mb-6 max-w-sm mx-auto">Anda belum pernah melakukan transaksi. Yuk jelajahi koleksi jersey dan perlengkapan olahraga terbaru kami!</p>
                        <a href="{{ route('home') }}" class="inline-flex items-center gap-2 bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-700 hover:to-rose-700 text-white text-xs font-black uppercase tracking-wider px-6 py-3 rounded-xl transition shadow-lg shadow-red-600/25">
                            Mulai Belanja Sekarang →
                        </a>
                    </div>
                @endforelse
            </div>
        </main>

        <!-- Footer -->
        <footer class="bg-white border-t border-slate-200/80 py-8 mt-auto">
            <div class="max-w-5xl mx-auto px-4 text-center">
                <img src="{{ asset('image/LOGO.jpeg') }}" alt="Logo" class="h-9 w-auto mx-auto mb-3 object-contain grayscale opacity-60">
                <p class="text-slate-500 text-[10px] sm:text-xs font-medium">© 2026 Eka Bagus Karisma & Aditya Panji Pratama. Proyek Tugas Akhir S1 Pendidikan Teknik Informatika.</p>
            </div>
        </footer>
    </div>

    <!-- =================================================================== -->
    <!-- MODAL UNGGAH & LIHAT BUKTI PEMBAYARAN                               -->
    <!-- =================================================================== -->
    <div x-show="paymentModal !== null"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/75 backdrop-blur-sm print:hidden"
         @keydown.escape.window="closePaymentModal()">
        <div @click.outside="closePaymentModal()"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             class="bg-white rounded-3xl max-w-md w-full overflow-hidden shadow-2xl border border-slate-200 max-h-[92vh] flex flex-col">

            <template x-if="paymentModal">
                <div class="flex flex-col overflow-hidden">
                    <!-- Header Modal -->
                    <div class="bg-slate-950 px-6 py-4 flex items-center justify-between shrink-0">
                        <div>
                            <span class="text-[10px] font-black uppercase tracking-widest text-red-500 block">Bukti Pembayaran</span>
                            <h3 class="text-sm font-bold text-white mt-0.5" x-text="'Pesanan #' + paymentModal.orderCode"></h3>
                        </div>
                        <button type="button" :disabled="submitting" @click="closePaymentModal()" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center text-xs transition disabled:opacity-50">✕</button>
                    </div>

                    <!-- Body Modal -->
                    <div class="p-6 overflow-y-auto space-y-4">
                        <!-- Ringkasan Tagihan -->
                        <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200/80 flex items-center justify-between">
                            <div>
                                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">Metode Pembayaran</span>
                                <span class="text-xs font-black text-slate-900" x-text="paymentModal.paymentMethod"></span>
                            </div>
                            <div class="text-right">
                                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">Total Transfer</span>
                                <span class="text-base font-black text-red-600 tabular-nums" x-text="paymentModal.totalFormatted"></span>
                            </div>
                        </div>

                        <!-- Tujuan Pembayaran (Transfer Bank / QRIS) saat belum Lunas -->
                        <template x-if="paymentModal.canUpload && paymentModal.paymentMethod === 'TRANSFER'">
                            <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200/80 space-y-2 text-xs">
                                <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Rekening Tujuan a.n. Sport Mania Distro:</p>
                                <div class="flex items-center justify-between bg-white px-3 py-2 rounded-xl border border-slate-200">
                                    <span><strong>BCA:</strong> 1234567890</span>
                                    <button type="button" @click="copyRekening('1234567890')" class="text-[10px] font-black uppercase px-2.5 py-1 rounded-lg bg-slate-900 hover:bg-red-600 text-white transition">
                                        <span x-text="copiedRekening === '1234567890' ? '✓ Tersalin' : 'Salin'"></span>
                                    </button>
                                </div>
                                <div class="flex items-center justify-between bg-white px-3 py-2 rounded-xl border border-slate-200">
                                    <span><strong>Mandiri:</strong> 1440012345678</span>
                                    <button type="button" @click="copyRekening('1440012345678')" class="text-[10px] font-black uppercase px-2.5 py-1 rounded-lg bg-slate-900 hover:bg-red-600 text-white transition">
                                        <span x-text="copiedRekening === '1440012345678' ? '✓ Tersalin' : 'Salin'"></span>
                                    </button>
                                </div>
                            </div>
                        </template>

                        <template x-if="paymentModal.canUpload && paymentModal.paymentMethod === 'QRIS'">
                            <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200/80 flex items-center gap-3.5 text-xs">
                                <img src="{{ asset('image/QRIS.jpeg') }}" alt="QRIS Sport Mania" class="w-16 h-16 rounded-xl object-contain bg-white border border-slate-200 p-1 shrink-0">
                                <div class="space-y-1">
                                    <p class="font-black text-slate-900 text-[11px] uppercase">QRIS KYOWACELL • SPORT MANIA</p>
                                    <p class="text-[11px] text-slate-500 leading-snug">Scan QRIS sesuai nominal tagihan lalu unggah bukti bayar di bawah ini.</p>
                                    <a href="{{ asset('image/QRIS.jpeg') }}" target="_blank" rel="noopener" class="inline-block text-[11px] font-bold text-red-600 hover:underline">
                                        Lihat / Simpan QRIS Penuh ↗
                                    </a>
                                </div>
                            </div>
                        </template>

                        <!-- Alasan Penolakan (Jika Ditolak) -->
                        <template x-if="paymentModal.paymentStatus === 'rejected' && paymentModal.rejectedReason">
                            <div class="p-3.5 rounded-2xl bg-rose-50 border border-rose-200 text-xs text-rose-800">
                                <span class="font-black uppercase text-[10px] tracking-wider text-rose-600 block mb-0.5">Alasan Penolakan Admin:</span>
                                <p class="font-semibold" x-text="paymentModal.rejectedReason"></p>
                            </div>
                        </template>

                        <!-- Pratinjau Gambar Bukti (Baru dipilih atau sudah tersimpan) -->
                        <template x-if="paymentModal.previewUrl || paymentModal.existingProofUrl">
                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <span class="text-[11px] font-black uppercase tracking-wider text-slate-500"
                                          x-text="paymentModal.previewUrl ? 'Pratinjau Foto Baru:' : 'Bukti Terunggah Saat Ini:'"></span>
                                    <a :href="paymentModal.previewUrl || paymentModal.existingProofUrl"
                                       target="_blank"
                                       rel="noopener"
                                       class="text-[11px] font-bold text-red-600 hover:underline">
                                        Buka Ukuran Penuh ↗
                                    </a>
                                </div>
                                <div class="rounded-2xl overflow-hidden border border-slate-200 bg-slate-100 flex items-center justify-center max-h-60">
                                    <img :src="paymentModal.previewUrl || paymentModal.existingProofUrl"
                                         alt="Bukti Pembayaran"
                                         class="max-h-60 w-auto object-contain">
                                </div>
                            </div>
                        </template>

                        <!-- Form Upload (Tampil jika belum paid dan pesanan tidak dibatalkan) -->
                        <template x-if="paymentModal.canUpload">
                            <form :action="paymentModal.actionUrl"
                                  method="POST"
                                  enctype="multipart/form-data"
                                  @submit="submitPaymentForm($event)"
                                  class="space-y-4">
                                @csrf

                                <div>
                                    <label class="block text-[11px] font-black uppercase tracking-wider text-slate-500 mb-2"
                                           x-text="paymentModal.existingProofUrl ? 'Ganti Foto Bukti Transfer' : 'Pilih Foto Bukti Transfer'"></label>

                                    <input type="file"
                                           name="payment_proof"
                                           x-ref="paymentFileInput"
                                           accept="image/jpeg,image/png,image/jpg,image/webp"
                                           @change="handlePaymentFileChange($event)"
                                           class="hidden">

                                    <button type="button"
                                            @click="$refs.paymentFileInput.click()"
                                            class="w-full p-4 rounded-2xl border-2 border-dashed border-slate-300 hover:border-red-500 bg-slate-50 hover:bg-red-50/30 transition text-center group">
                                        <div class="w-10 h-10 rounded-xl bg-white border border-slate-200 group-hover:border-red-200 text-slate-500 group-hover:text-red-600 flex items-center justify-center mx-auto mb-2 shadow-sm transition">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                        </div>
                                        <p class="text-xs font-bold text-slate-700"
                                           x-text="paymentModal.fileName ? paymentModal.fileName : 'Klik untuk memilih foto struk / screenshot transfer'"></p>
                                        <p class="text-[10px] text-slate-400 mt-1">Format JPG, PNG, WEBP • Maksimal 4 MB</p>
                                    </button>

                                    <p x-show="paymentModal.fileError"
                                       x-cloak
                                       class="text-xs font-bold text-rose-600 mt-2"
                                       x-text="paymentModal.fileError"></p>
                                </div>

                                <div class="flex items-center justify-end gap-2.5 pt-1">
                                    <button type="button"
                                            :disabled="submitting"
                                            @click="closePaymentModal()"
                                            class="px-4 py-2.5 rounded-xl text-xs font-bold text-slate-600 hover:text-slate-900 bg-slate-100 hover:bg-slate-200 transition disabled:opacity-50">
                                        Tutup
                                    </button>
                                    <button type="submit"
                                            :disabled="submitting"
                                            class="flex-1 bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-700 hover:to-rose-700 text-white font-black text-xs uppercase tracking-wider py-2.5 px-5 rounded-xl transition shadow-lg shadow-red-600/25 disabled:opacity-60"
                                            x-text="submitting ? 'Mengunggah Bukti...' : 'Kirim Bukti Pembayaran'">
                                    </button>
                                </div>
                            </form>
                        </template>

                        <!-- Tombol Tutup jika status sudah Lunas -->
                        <template x-if="!paymentModal.canUpload">
                            <div class="flex justify-end pt-1">
                                <button type="button"
                                        @click="closePaymentModal()"
                                        class="w-full py-2.5 rounded-xl text-xs font-black uppercase tracking-wider text-white bg-slate-900 hover:bg-slate-800 transition">
                                    Tutup Pratinjau
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- =================================================================== -->
    <!-- MODAL KONFIRMASI AKSI PESANAN (BATAL & PESANAN DITERIMA)            -->
    <!-- =================================================================== -->
    <div x-show="confirmModal !== null"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-sm print:hidden"
         @keydown.escape.window="if (!submitting) confirmModal = null">
        <div @click.outside="if (!submitting) confirmModal = null"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             class="bg-white rounded-3xl max-w-md w-full overflow-hidden shadow-2xl border border-slate-200">

            <template x-if="confirmModal">
                <div>
                    <div class="bg-slate-950 px-6 py-4 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl flex items-center justify-center text-base font-black shrink-0"
                                 :class="confirmModal.type === 'cancel'
                                     ? 'bg-rose-500/20 text-rose-400 border border-rose-500/30'
                                     : 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30'">
                                <span x-text="confirmModal.type === 'cancel' ? '!' : '✓'"></span>
                            </div>
                            <div>
                                <span class="text-[10px] font-black uppercase tracking-widest block"
                                      :class="confirmModal.type === 'cancel' ? 'text-rose-400' : 'text-emerald-400'"
                                      x-text="confirmModal.badge"></span>
                                <h3 class="text-sm font-bold text-white mt-0.5" x-text="confirmModal.title"></h3>
                            </div>
                        </div>
                        <button type="button" :disabled="submitting" @click="confirmModal = null" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center text-xs transition disabled:opacity-50">✕</button>
                    </div>

                    <div class="p-6">
                        <p class="text-xs text-slate-600 leading-relaxed" x-text="confirmModal.message"></p>

                        <form :action="confirmModal.actionUrl" method="POST" @submit="submitting = true" class="mt-6 flex items-center justify-end gap-2.5">
                            @csrf
                            <button type="button"
                                    :disabled="submitting"
                                    @click="confirmModal = null"
                                    class="px-4 py-2.5 rounded-xl text-xs font-bold text-slate-600 hover:text-slate-900 bg-slate-100 hover:bg-slate-200 transition disabled:opacity-50">
                                Kembali
                            </button>

                            <button type="submit"
                                    :disabled="submitting"
                                    class="px-5 py-2.5 rounded-xl text-xs font-black uppercase tracking-wider text-white transition shadow-lg disabled:opacity-60"
                                    :class="confirmModal.type === 'cancel'
                                        ? 'bg-gradient-to-r from-rose-600 to-red-600 hover:from-rose-700 hover:to-red-700 shadow-rose-600/25'
                                        : 'bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 shadow-emerald-600/25'"
                                    x-text="submitting ? 'Memproses...' : confirmModal.confirmText">
                            </button>
                        </form>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- =================================================================== -->
    <!-- MODAL ULASAN PRODUK (SAAT PESANAN SELESAI)                          -->
    <!-- =================================================================== -->
    <div x-show="reviewModal !== null"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-sm print:hidden"
         @keydown.escape.window="if (!submitting) reviewModal = null">
        <div @click.outside="if (!submitting) reviewModal = null"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             class="bg-white rounded-3xl max-w-md w-full overflow-hidden shadow-2xl border border-slate-200">
            
            <template x-if="reviewModal">
                <div>
                    <div class="bg-slate-950 px-6 py-4 flex items-center justify-between">
                        <div>
                            <span class="text-[10px] font-black uppercase tracking-widest text-red-500">Ulasan Produk</span>
                            <h3 class="text-sm font-bold text-white mt-0.5" x-text="reviewModal.product_name"></h3>
                        </div>
                        <button type="button" :disabled="submitting" @click="reviewModal = null" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center text-xs disabled:opacity-50">✕</button>
                    </div>

                    <form action="{{ route('customer.reviews.store') }}" method="POST" @submit="submitReviewForm($event)" class="p-6 space-y-4">
                        @csrf
                        <input type="hidden" name="product_id" :value="reviewModal.product_id">
                        <input type="hidden" name="order_id" :value="reviewModal.order_id">
                        <input type="hidden" name="rating" :value="reviewModal.rating">

                        <div>
                            <label class="block text-[11px] font-black uppercase tracking-wider text-slate-500 mb-2">Rating Kepuasan</label>
                            <div class="flex items-center gap-2">
                                <template x-for="star in [1, 2, 3, 4, 5]" :key="star">
                                    <button type="button"
                                            @click="reviewModal.rating = star"
                                            class="text-2xl transition transform hover:scale-110 focus:outline-none"
                                            :class="star <= reviewModal.rating ? 'text-amber-400' : 'text-slate-200'">
                                        ★
                                    </button>
                                </template>
                                <span class="text-xs font-bold text-slate-600 ml-2" x-text="reviewModal.rating + ' / 5 Bintang'"></span>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[11px] font-black uppercase tracking-wider text-slate-500 mb-1.5">Komentar Anda</label>
                            <textarea name="comment"
                                      rows="3"
                                      required
                                      x-model="reviewModal.comment"
                                      placeholder="Ceritakan kualitas bahan, ukuran, dan pelayanan Sport Mania Distro..."
                                      class="w-full text-xs text-slate-900 bg-slate-50 border border-slate-200 rounded-2xl p-3.5 focus:bg-white focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none transition"></textarea>
                        </div>

                        <button type="submit"
                                :disabled="submitting"
                                class="w-full bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-700 hover:to-rose-700 text-white font-black text-xs uppercase tracking-wider py-3 rounded-xl transition shadow-lg shadow-red-600/25 disabled:opacity-60"
                                x-text="submitting ? 'Mengirim Ulasan...' : 'Kirim Ulasan'">
                        </button>
                    </form>
                </div>
            </template>
        </div>
    </div>

    <!-- =================================================================== -->
    <!-- AREA CETAK INVOICE MANDIRI PELANGGAN (A4)                           -->
    <!-- =================================================================== -->
    <div class="hidden print:block text-black bg-white">
        <template x-if="printOrder">
            <div class="max-w-3xl mx-auto p-6 text-gray-900">
                <div class="flex justify-between items-start border-b-2 border-gray-900 pb-5 mb-6">
                    <div>
                        <h1 class="text-2xl font-black tracking-tighter uppercase italic">
                            <span class="text-red-600">SPORT</span><span class="text-gray-900">MANIA</span> DISTRO
                        </h1>
                        <p class="text-xs text-gray-600 mt-1">Official Sport Fashion & Apparel Store</p>
                        <p class="text-xs text-gray-600">Malang, Jawa Timur • Telp/WA: 0822-3454-8092</p>
                    </div>
                    <div class="text-right">
                        <span class="inline-block bg-gray-900 text-white text-xs font-black uppercase tracking-widest px-3 py-1 rounded mb-2">
                            INVOICE PEMBELIAN
                        </span>
                        <p class="text-sm font-black" x-text="'#' + printOrder.code"></p>
                        <p class="text-xs text-gray-500" x-text="'Tanggal: ' + printOrder.date"></p>
                        <p class="text-xs font-bold uppercase mt-1" x-text="'Status: ' + printOrder.status"></p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-6 bg-gray-50 p-4 rounded-xl border border-gray-200 mb-6 text-xs">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-wider text-gray-500 mb-1">Penerima:</p>
                        <p class="text-sm font-black text-gray-900" x-text="printOrder.customer"></p>
                        <p class="text-gray-700 mt-0.5" x-text="'Telp / WA: ' + printOrder.phone"></p>
                        <p class="text-gray-700 mt-0.5" x-text="printOrder.address + (printOrder.city && printOrder.city !== '-' ? ' (' + printOrder.city + ')' : '')"></p>
                        <template x-if="printOrder.notes && printOrder.notes !== '-'">
                            <p class="text-gray-600 mt-1 italic" x-text="'Catatan: ' + printOrder.notes"></p>
                        </template>
                    </div>
                    <div class="text-right">
                        <p class="text-[10px] font-black uppercase tracking-wider text-gray-500 mb-1">Detail Pengiriman & Bayar:</p>
                        <p class="text-gray-700">Metode Bayar: <strong class="uppercase" x-text="printOrder.payment"></strong></p>
                        <p class="text-gray-700 mt-0.5">Status Bayar: <strong class="uppercase" x-text="printOrder.payment_status_label"></strong></p>
                        <p class="text-gray-700 mt-0.5">Kurir: <strong class="uppercase" x-text="printOrder.shipping_method"></strong></p>
                        <p class="text-gray-700 mt-0.5">No. Resi Kurir: <strong x-text="printOrder.tracking_number || '-'"></strong></p>
                    </div>
                </div>

                <table class="w-full text-left border-collapse mb-6 text-xs">
                    <thead>
                        <tr class="border-y-2 border-gray-900 uppercase text-[10px] font-black tracking-wider bg-gray-100">
                            <th class="py-2.5 px-3">No</th>
                            <th class="py-2.5 px-3">Nama Produk</th>
                            <th class="py-2.5 px-3">Varian</th>
                            <th class="py-2.5 px-3 text-center">Qty</th>
                            <th class="py-2.5 px-3 text-right">Harga Satuan</th>
                            <th class="py-2.5 px-3 text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 border-b-2 border-gray-900">
                        <template x-for="(item, idx) in printOrder.items" :key="idx">
                            <tr>
                                <td class="py-3 px-3 font-semibold" x-text="idx + 1"></td>
                                <td class="py-3 px-3 font-bold" x-text="item.name"></td>
                                <td class="py-3 px-3 text-gray-600" x-text="item.size + ' / ' + item.color"></td>
                                <td class="py-3 px-3 text-center font-bold" x-text="item.qty"></td>
                                <td class="py-3 px-3 text-right" x-text="'Rp ' + Number(item.price).toLocaleString('id-ID')"></td>
                                <td class="py-3 px-3 text-right font-bold" x-text="'Rp ' + Number(item.subtotal).toLocaleString('id-ID')"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>

                <div class="flex justify-between items-end">
                    <div class="text-xs text-gray-500 max-w-sm">
                        <p class="font-bold text-gray-800 mb-1">Terima Kasih Telah Berbelanja!</p>
                        <p>Simpan invoice ini sebagai bukti pembayaran resmi Sport Mania Distro.</p>
                    </div>
                    <div class="text-right bg-gray-100 px-5 py-3 rounded-xl border border-gray-300 space-y-1">
                        <template x-if="printOrder.subtotal > 0">
                            <p class="text-[11px] text-gray-600">
                                Subtotal: <strong x-text="'Rp ' + Number(printOrder.subtotal).toLocaleString('id-ID')"></strong> •
                                Ongkir: <strong x-text="printOrder.shipping_cost === 0 ? 'GRATIS' : 'Rp ' + Number(printOrder.shipping_cost).toLocaleString('id-ID')"></strong>
                            </p>
                        </template>
                        <span class="text-[10px] font-black uppercase tracking-wider text-gray-500 block">Total Pembayaran</span>
                        <span class="text-xl font-black text-red-600" x-text="'Rp ' + Number(printOrder.total).toLocaleString('id-ID')"></span>
                    </div>
                </div>
            </div>
        </template>
    </div>
</body>
</html>