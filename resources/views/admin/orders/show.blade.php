<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pesanan #{{ $order->order_number ?? $order->invoice_number ?? $order->id }} - Admin Sport Mania Distro</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 antialiased" x-data="{ sidebarOpen: false, proofLightbox: false }" @keydown.escape.window="proofLightbox = false">

    @php
        $orderCode = $order->order_number ?? $order->invoice_number ?? ('ORD-' . str_pad($order->id, 4, '0', STR_PAD_LEFT));
        $statusRaw = strtolower($order->status ?? 'pending');
        if ($statusRaw === 'batal') {
            $statusRaw = 'dibatalkan';
        }
        $subtotalVal = (float) ($order->subtotal ?? 0);
        $shippingVal = (float) ($order->shipping_cost ?? 0);
        $totalVal = (float) ($order->total_price ?? $order->total_amount ?? $order->total ?? 0);
        $isCod = strtolower($order->payment_method ?? '') === 'cod';
        $paymentStatus = strtolower($order->payment_status ?? 'unpaid');
        $proofUrl = filled($order->payment_proof ?? null)
            ? (filter_var($order->payment_proof, FILTER_VALIDATE_URL) ? $order->payment_proof : asset('storage/' . $order->payment_proof))
            : null;
        $trackingValue = old('tracking_number', $order->tracking_number ?? $order->resi ?? '');
    @endphp

    <div class="flex h-screen overflow-hidden">

        <!-- Sidebar Desktop -->
        <aside class="hidden md:flex flex-col w-64 bg-gray-900 text-white transition-all duration-300 z-20">
            <div class="flex items-center justify-center h-20 border-b border-gray-800">
                <a href="/" class="flex items-center gap-3 hover:opacity-80 transition">
                    <div class="p-1 rounded-lg bg-black border border-gray-700 shadow-inner">
                        <img src="{{ asset('image/LOGO.jpeg') }}" alt="Logo" class="h-8 w-auto object-contain rounded">
                    </div>
                    <span class="text-xl font-black tracking-tighter uppercase italic">
                        <span class="text-red-500">SPORT</span><span>MANIA</span>
                    </span>
                </a>
            </div>
            <nav class="flex-1 overflow-y-auto py-6 px-4 space-y-2">
                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-xl text-gray-400 hover:text-white hover:bg-gray-800 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                    Dashboard
                </a>
                <a href="{{ route('admin.products.index') }}" class="flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-xl text-gray-400 hover:text-white hover:bg-gray-800 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                    Produk
                </a>
                <a href="{{ route('admin.orders.index') }}" class="flex items-center gap-3 px-4 py-3 text-sm font-bold rounded-xl bg-red-600 text-white shadow-lg shadow-red-600/20 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                    Pesanan
                </a>
                <a href="{{ route('admin.rekap.bulanan') }}" class="flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-xl text-gray-400 hover:text-white hover:bg-gray-800 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    Rekap Bulanan
                </a>
            </nav>
            <div class="p-4 border-t border-gray-800">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex items-center gap-3 w-full px-4 py-3 text-sm font-bold rounded-xl text-red-400 hover:text-white hover:bg-red-600 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                        Logout
                    </button>
                </form>
            </div>
        </aside>

        <!-- Sidebar Mobile & Backdrop -->
        <div x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 bg-gray-900/80 z-30 md:hidden backdrop-blur-sm" @click="sidebarOpen = false" x-cloak></div>
        <aside x-show="sidebarOpen" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition ease-in duration-300" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full" class="fixed inset-y-0 left-0 flex flex-col w-64 bg-gray-900 text-white z-40 md:hidden" x-cloak>
            <div class="flex items-center justify-between h-20 px-6 border-b border-gray-800">
                <span class="text-xl font-black uppercase italic"><span class="text-red-500">SPORT</span>MANIA</span>
                <button @click="sidebarOpen = false" class="text-gray-400 hover:text-white">✕</button>
            </div>
            <nav class="flex-1 overflow-y-auto py-6 px-4 space-y-2">
                <a href="{{ route('admin.dashboard') }}" class="block px-4 py-3 text-sm font-medium rounded-xl text-gray-400 hover:text-white hover:bg-gray-800">Dashboard</a>
                <a href="{{ route('admin.products.index') }}" class="block px-4 py-3 text-sm font-medium rounded-xl text-gray-400 hover:text-white hover:bg-gray-800">Produk</a>
                <a href="{{ route('admin.orders.index') }}" class="block px-4 py-3 text-sm font-bold rounded-xl bg-red-600 text-white shadow-lg shadow-red-600/20">Pesanan</a>
                <a href="{{ route('admin.rekap.bulanan') }}" class="block px-4 py-3 text-sm font-medium rounded-xl text-gray-400 hover:text-white hover:bg-gray-800">Rekap Bulanan</a>
            </nav>
            <div class="p-4 border-t border-gray-800">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full text-left px-4 py-3 text-sm font-bold rounded-xl text-red-400 hover:text-white hover:bg-red-600">Logout</button>
                </form>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 flex flex-col h-screen overflow-hidden bg-gray-50">

            <!-- Header -->
            <header class="h-20 bg-white border-b border-gray-200 flex items-center justify-between px-4 sm:px-6 lg:px-10 shrink-0">
                <div class="flex items-center gap-3">
                    <button @click="sidebarOpen = true" class="md:hidden p-2 rounded-lg text-gray-500 hover:bg-gray-100 focus:outline-none">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    </button>
                    <div>
                        <h2 class="text-base sm:text-lg font-bold text-gray-900 uppercase tracking-wide">
                            Detail Pesanan #{{ $orderCode }}
                        </h2>
                        <p class="text-[11px] text-gray-500">{{ optional($order->created_at)->format('d M Y, H:i') }}</p>
                    </div>
                </div>
                <a href="{{ route('admin.orders.index') }}" class="text-xs font-bold text-gray-600 hover:text-gray-900 bg-gray-100 hover:bg-gray-200 px-4 py-2 rounded-lg transition">
                    ← Kembali ke Daftar Pesanan
                </a>
            </header>

            <!-- Scrollable Body -->
            <div class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-10">

                @if(session('success'))
                    <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-700 rounded-xl font-bold text-xs sm:text-sm flex items-center gap-3 shadow-sm">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span>{{ session('success') }}</span>
                    </div>
                @endif

                @if(session('error'))
                    <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl font-bold text-xs sm:text-sm flex items-center gap-3 shadow-sm">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span>{{ session('error') }}</span>
                    </div>
                @endif

                @if($errors->any())
                    <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl font-semibold text-xs">
                        <ul class="list-disc list-inside space-y-1">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

                    <!-- Kiri: Rincian Produk & Ringkasan Biaya -->
                    <div class="lg:col-span-2 space-y-6">
                        <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm">
                            <h3 class="text-xs font-black uppercase tracking-wider text-gray-400 mb-4">Produk yang Dibeli</h3>

                            <div class="divide-y divide-gray-100">
                                @foreach($order->items as $item)
                                    @php
                                        $qty = (int) ($item->quantity ?? $item->qty ?? 1);
                                        $price = (float) ($item->price ?? 0);
                                        $itemName = filled($item->product_name ?? null)
                                            ? $item->product_name
                                            : ($item->product->name ?? 'Produk Dihapus');
                                        $itemSize = $item->variant_size ?? $item->size ?? 'All Size';
                                        $itemColor = $item->variant_color ?? $item->color ?? '-';
                                        $imgUrl = ($item->product && $item->product->image)
                                            ? (filter_var($item->product->image, FILTER_VALIDATE_URL) ? $item->product->image : asset('storage/' . $item->product->image))
                                            : null;
                                    @endphp
                                    <div class="py-4 flex items-center justify-between gap-4 first:pt-0 last:pb-0">
                                        <div class="flex items-center gap-4">
                                            @if($imgUrl)
                                                <img src="{{ $imgUrl }}" alt="{{ $itemName }}" class="w-14 h-16 rounded-xl object-cover border border-gray-200 shrink-0">
                                            @else
                                                <div class="w-14 h-16 rounded-xl bg-gray-100 flex items-center justify-center text-[10px] text-gray-400 font-bold shrink-0">No Img</div>
                                            @endif
                                            <div>
                                                <h4 class="text-sm font-bold text-gray-900">{{ $itemName }}</h4>
                                                <p class="text-xs text-gray-500 mt-0.5">
                                                    Varian: <span class="font-bold text-gray-700">{{ $itemSize }} / {{ $itemColor }}</span>
                                                </p>
                                                <p class="text-xs text-gray-500">Jumlah: <span class="font-bold text-gray-900">{{ $qty }} pcs</span></p>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-sm font-black text-gray-900">Rp {{ number_format($price * $qty, 0, ',', '.') }}</p>
                                            <p class="text-[10px] text-gray-400">(@ Rp {{ number_format($price, 0, ',', '.') }})</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <!-- Rincian Tagihan -->
                            <div class="mt-6 pt-6 border-t border-gray-100 space-y-2 text-xs">
                                @if($subtotalVal > 0)
                                    <div class="flex justify-between text-gray-500">
                                        <span>Subtotal Produk</span>
                                        <span class="font-bold text-gray-800">Rp {{ number_format($subtotalVal, 0, ',', '.') }}</span>
                                    </div>
                                    <div class="flex justify-between text-gray-500">
                                        <span>Ongkos Kirim ({{ strtoupper($order->shipping_method ?? '-') }})</span>
                                        <span class="font-bold text-gray-800">{{ $shippingVal == 0 ? 'GRATIS' : 'Rp ' . number_format($shippingVal, 0, ',', '.') }}</span>
                                    </div>
                                @endif
                                <div class="pt-2 flex justify-between items-center">
                                    <span class="text-xs font-bold uppercase text-gray-500">Total Keseluruhan Tagihan</span>
                                    <span class="text-xl font-black text-red-600">Rp {{ number_format($totalVal, 0, ',', '.') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Kanan: Verifikasi Bukti Bayar, Info Pengiriman & Form Update Status -->
                    <div class="space-y-6">

                        <!-- Panel Verifikasi Bukti Pembayaran -->
                        <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm space-y-4">
                            <div class="flex items-center justify-between gap-2">
                                <h3 class="text-xs font-black uppercase tracking-wider text-gray-400">Verifikasi Pembayaran</h3>

                                @if($isCod)
                                    <span class="text-[10px] font-black uppercase tracking-wider px-2.5 py-1 rounded-full bg-gray-100 text-gray-700 border border-gray-200">
                                        COD
                                    </span>
                                @elseif($paymentStatus === 'paid')
                                    <span class="text-[10px] font-black uppercase tracking-wider px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        ✓ Lunas
                                    </span>
                                @elseif($paymentStatus === 'pending_verification')
                                    <span class="text-[10px] font-black uppercase tracking-wider px-2.5 py-1 rounded-full bg-blue-50 text-blue-700 border border-blue-200">
                                        Perlu Verifikasi
                                    </span>
                                @elseif($paymentStatus === 'rejected')
                                    <span class="text-[10px] font-black uppercase tracking-wider px-2.5 py-1 rounded-full bg-rose-50 text-rose-700 border border-rose-200">
                                        Ditolak
                                    </span>
                                @else
                                    <span class="text-[10px] font-black uppercase tracking-wider px-2.5 py-1 rounded-full bg-amber-50 text-amber-700 border border-amber-200">
                                        Belum Bayar
                                    </span>
                                @endif
                            </div>

                            @if($isCod)
                                <div class="p-3.5 rounded-xl bg-gray-50 border border-gray-200/80 text-xs text-gray-600">
                                    Pesanan menggunakan metode <strong>COD (Bayar di Tempat)</strong>. Tidak memerlukan unggah bukti transfer.
                                </div>
                            @elseif($proofUrl)
                                <!-- Preview Bukti Transfer -->
                                <div class="space-y-2">
                                    <div class="relative group rounded-xl overflow-hidden border border-gray-200 bg-gray-50 cursor-pointer"
                                         @click="proofLightbox = true">
                                        <img src="{{ $proofUrl }}"
                                             alt="Bukti Pembayaran #{{ $orderCode }}"
                                             class="w-full max-h-56 object-contain mx-auto">
                                        <div class="absolute inset-0 bg-gray-950/50 opacity-0 group-hover:opacity-100 transition flex items-center justify-center">
                                            <span class="text-[11px] font-black uppercase tracking-wider text-white bg-gray-900/90 px-3.5 py-1.5 rounded-lg border border-gray-700">
                                                🔍 Perbesar Gambar
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-between text-[11px]">
                                        <button type="button" @click="proofLightbox = true" class="font-bold text-gray-600 hover:text-gray-900">
                                            Klik gambar untuk zoom
                                        </button>
                                        <a href="{{ $proofUrl }}" target="_blank" rel="noopener" class="font-bold text-red-600 hover:underline">
                                            Buka Tab Baru ↗
                                        </a>
                                    </div>
                                </div>

                                @if($paymentStatus === 'rejected' && filled($order->payment_rejected_reason))
                                    <div class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-800">
                                        <span class="text-[10px] font-black uppercase text-rose-600 block">Alasan Penolakan:</span>
                                        <p class="mt-0.5 font-medium">{{ $order->payment_rejected_reason }}</p>
                                    </div>
                                @endif

                                <!-- Form Aksi Terima / Tolak Bukti Pembayaran -->
                                <div x-data="{ rejectMode: false, submitting: false }" class="pt-1">
                                    <form action="{{ route('admin.orders.verify-payment', $order->id) }}" method="POST" @submit="submitting = true" class="space-y-3">
                                        @csrf
                                        @method('PATCH')

                                        <!-- Tombol Utama Approve & Trigger Reject -->
                                        <div x-show="!rejectMode" class="grid grid-cols-2 gap-2.5">
                                            <button type="submit"
                                                    name="action"
                                                    value="approve"
                                                    :disabled="submitting"
                                                    class="bg-emerald-600 hover:bg-emerald-700 text-white py-2.5 px-3 rounded-xl font-black text-[11px] uppercase tracking-wider transition shadow-sm disabled:opacity-60">
                                                {{ $paymentStatus === 'paid' ? '✓ Sudah Lunas' : '✓ Terima & Proses' }}
                                            </button>

                                            <button type="button"
                                                    :disabled="submitting"
                                                    @click="rejectMode = true"
                                                    class="bg-rose-50 hover:bg-rose-600 text-rose-600 hover:text-white border border-rose-200 py-2.5 px-3 rounded-xl font-black text-[11px] uppercase tracking-wider transition disabled:opacity-60">
                                                ✕ Tolak Bukti
                                            </button>
                                        </div>

                                        <!-- Form Alasan Penolakan -->
                                        <div x-show="rejectMode" x-cloak class="space-y-2.5 p-3.5 rounded-xl bg-rose-50/70 border border-rose-200">
                                            <label class="block text-[10px] font-black uppercase tracking-wider text-rose-700">
                                                Alasan Penolakan (Wajib Diisi)
                                            </label>
                                            <textarea name="rejection_reason"
                                                      rows="2"
                                                      :required="rejectMode"
                                                      placeholder="Contoh: Nominal transfer tidak sesuai total tagihan atau gambar buram..."
                                                      class="w-full bg-white border border-rose-200 rounded-xl p-2.5 text-xs text-gray-800 focus:ring-2 focus:ring-rose-500 focus:outline-none"></textarea>

                                            <div class="flex items-center gap-2">
                                                <button type="button"
                                                        :disabled="submitting"
                                                        @click="rejectMode = false"
                                                        class="px-3 py-2 rounded-lg text-[11px] font-bold text-gray-600 bg-white border border-gray-200 hover:bg-gray-100 transition">
                                                    Batal
                                                </button>
                                                <button type="submit"
                                                        name="action"
                                                        value="reject"
                                                        :disabled="submitting"
                                                        class="flex-1 bg-rose-600 hover:bg-rose-700 text-white py-2 px-3 rounded-lg font-black text-[11px] uppercase tracking-wider transition disabled:opacity-60">
                                                    Konfirmasi Tolak
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            @else
                                <div class="p-4 rounded-xl bg-amber-50/70 border border-amber-200/80 text-center space-y-1">
                                    <p class="text-xs font-bold text-amber-900">Belum Ada Bukti Pembayaran</p>
                                    <p class="text-[11px] text-amber-700">Pelanggan belum mengunggah foto bukti transfer untuk transaksi ini.</p>
                                </div>
                            @endif
                        </div>

                        <!-- Info Pelanggan & Pengiriman -->
                        <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm space-y-4">
                            <h3 class="text-xs font-black uppercase tracking-wider text-gray-400">Informasi Pengiriman & Pembayaran</h3>

                            <div class="text-xs space-y-3">
                                <div>
                                    <span class="text-gray-400 block text-[10px] uppercase font-bold">Nama Penerima</span>
                                    <span class="font-bold text-gray-900 text-sm">{{ $order->name ?? $order->recipient_name ?? optional($order->user)->name ?? 'Pelanggan' }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-400 block text-[10px] uppercase font-bold">No. WhatsApp / Telepon</span>
                                    <span class="font-bold text-gray-900">{{ $order->phone ?? '-' }}</span>
                                </div>
                                <div class="grid grid-cols-2 gap-3 pt-1">
                                    <div>
                                        <span class="text-gray-400 block text-[10px] uppercase font-bold">Metode Bayar</span>
                                        <span class="font-bold text-gray-900 uppercase">{{ $order->payment_method ?? '-' }}</span>
                                    </div>
                                    <div>
                                        <span class="text-gray-400 block text-[10px] uppercase font-bold">Kurir / Pengiriman</span>
                                        <span class="font-bold text-gray-900 uppercase">{{ $order->shipping_method ?? '-' }}</span>
                                    </div>
                                </div>
                                <div>
                                    <span class="text-gray-400 block text-[10px] uppercase font-bold">Kota / Kabupaten</span>
                                    <span class="font-bold text-gray-900">{{ $order->city ?? '-' }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-400 block text-[10px] uppercase font-bold">Alamat Lengkap</span>
                                    <p class="font-medium text-gray-700 leading-relaxed mt-0.5">{{ $order->shipping_address ?? $order->address ?? '-' }}</p>
                                </div>
                                @if(filled($order->notes ?? null))
                                    <div class="p-3 bg-amber-50 border border-amber-100 rounded-xl">
                                        <span class="text-amber-700 block text-[10px] uppercase font-black">Catatan Pembeli</span>
                                        <p class="font-medium text-amber-900 mt-0.5">{{ $order->notes }}</p>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Form Kelola Status & Resi -->
                        <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm">
                            <h3 class="text-xs font-black uppercase tracking-wider text-gray-400 mb-4">Kelola Status Pesanan</h3>

                            <form action="{{ route('admin.orders.update', $order->id) }}" method="POST" class="space-y-4">
                                @csrf
                                @method('PUT')

                                <div>
                                    <label class="block text-xs font-bold uppercase text-gray-700 mb-1">Status Pesanan</label>
                                    <select name="status" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-xs font-bold focus:ring-2 focus:ring-red-500 focus:outline-none">
                                        <option value="pending" {{ $statusRaw === 'pending' ? 'selected' : '' }}>Pending</option>
                                        <option value="diproses" {{ $statusRaw === 'diproses' ? 'selected' : '' }}>Diproses</option>
                                        <option value="dikirim" {{ $statusRaw === 'dikirim' ? 'selected' : '' }}>Dikirim</option>
                                        <option value="selesai" {{ $statusRaw === 'selesai' ? 'selected' : '' }}>Selesai</option>
                                        <option value="dibatalkan" {{ $statusRaw === 'dibatalkan' ? 'selected' : '' }}>Batal (Otomatis Restock)</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold uppercase text-gray-700 mb-1">Nomor Resi Kurir</label>
                                    <input type="text" name="tracking_number" value="{{ $trackingValue }}" placeholder="Contoh: JNT-882910392" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-xs font-medium focus:ring-2 focus:ring-red-500 focus:outline-none">
                                </div>

                                <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white py-3 rounded-xl font-bold text-xs uppercase tracking-widest transition shadow-lg shadow-red-600/20">
                                    Simpan Perubahan
                                </button>
                            </form>
                        </div>

                    </div>

                </div>

            </div>
        </main>
    </div>

    <!-- Modal Lightbox Zoom Bukti Pembayaran -->
    @if($proofUrl)
        <div x-show="proofLightbox"
             x-cloak
             x-transition.opacity
             @click="proofLightbox = false"
             class="fixed inset-0 z-50 bg-gray-950/90 backdrop-blur-sm flex items-center justify-center p-4">
            <div @click.stop class="relative max-w-2xl w-full bg-gray-900 rounded-2xl border border-gray-800 overflow-hidden shadow-2xl">
                <div class="px-5 py-3.5 border-b border-gray-800 flex items-center justify-between text-white">
                    <span class="text-xs font-black uppercase tracking-wider">Bukti Pembayaran #{{ $orderCode }}</span>
                    <div class="flex items-center gap-2">
                        <a href="{{ $proofUrl }}" target="_blank" rel="noopener" class="text-[11px] font-bold bg-gray-800 hover:bg-gray-700 px-3 py-1.5 rounded-lg transition">
                            Buka Asli ↗
                        </a>
                        <button type="button" @click="proofLightbox = false" class="w-8 h-8 rounded-lg bg-gray-800 hover:bg-red-600 text-gray-300 hover:text-white flex items-center justify-center text-xs transition">✕</button>
                    </div>
                </div>
                <div class="p-4 bg-black flex items-center justify-center max-h-[80vh] overflow-auto">
                    <img src="{{ $proofUrl }}" alt="Bukti Pembayaran Full" class="max-h-[75vh] w-auto object-contain rounded-lg">
                </div>
            </div>
        </div>
    @endif

</body>
</html>