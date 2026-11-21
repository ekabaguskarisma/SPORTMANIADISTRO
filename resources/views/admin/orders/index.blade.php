<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pesanan - Admin Sport Mania Distro</title>
    
    <!-- Memanggil CSS dan JS melalui Vite -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 antialiased"
      x-data="{ sidebarOpen: false, proofPreview: null }"
      @keydown.escape.window="proofPreview = null">

    @php
        $pendingVerificationCount = 0;
        if (\Illuminate\Support\Facades\Schema::hasColumn('orders', 'payment_status')) {
            $pendingVerificationCount = \App\Models\Order::where('payment_status', 'pending_verification')->count();
        }

        $hasActiveFilter = request()->anyFilled([
            'search',
            'time_filter',
            'date_from',
            'date_until',
            'status',
            'payment_status',
        ]);
    @endphp

    <!-- Wrapper Utama (Flexbox untuk Sidebar & Konten) -->
    <div class="flex h-screen overflow-hidden">

        <!-- ========================================== -->
        <!-- SIDEBAR -->
        <!-- ========================================== -->
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
                <a href="{{ route('admin.orders.index') }}" class="flex items-center justify-between px-4 py-3 text-sm font-bold rounded-xl bg-red-600 text-white shadow-lg shadow-red-600/20 transition">
                    <span class="flex items-center gap-3">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                        Pesanan
                    </span>
                    @if($pendingVerificationCount > 0)
                        <span class="bg-white text-red-600 text-[10px] font-black px-2 py-0.5 rounded-full">
                            {{ $pendingVerificationCount }}
                        </span>
                    @endif
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
                <button @click="sidebarOpen = false" class="text-gray-400 hover:text-white"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
            </div>
            
            <nav class="flex-1 overflow-y-auto py-6 px-4 space-y-2">
                <a href="{{ route('admin.dashboard') }}" class="block px-4 py-3 text-sm font-medium rounded-xl text-gray-400 hover:text-white hover:bg-gray-800">Dashboard</a>
                <a href="{{ route('admin.products.index') }}" class="block px-4 py-3 text-sm font-medium rounded-xl text-gray-400 hover:text-white hover:bg-gray-800">Produk</a>
                <a href="{{ route('admin.orders.index') }}" class="flex items-center justify-between px-4 py-3 text-sm font-bold rounded-xl bg-red-600 text-white shadow-lg shadow-red-600/20">
                    <span>Pesanan</span>
                    @if($pendingVerificationCount > 0)
                        <span class="bg-white text-red-600 text-[10px] font-black px-2 py-0.5 rounded-full">{{ $pendingVerificationCount }}</span>
                    @endif
                </a>
                <a href="{{ route('admin.rekap.bulanan') }}" class="block px-4 py-3 text-sm font-medium rounded-xl text-gray-400 hover:text-white hover:bg-gray-800">Rekap Bulanan</a>
            </nav>
            <div class="p-4 border-t border-gray-800">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full text-left px-4 py-3 text-sm font-bold rounded-xl text-red-400 hover:text-white hover:bg-red-600">Logout</button>
                </form>
            </div>
        </aside>

        <!-- ========================================== -->
        <!-- AREA KONTEN UTAMA -->
        <!-- ========================================== -->
        <main class="flex-1 flex flex-col h-screen overflow-hidden bg-gray-50 relative">
            
            <!-- Navbar Header -->
            <header class="h-20 bg-white border-b border-gray-200 flex items-center justify-between px-4 sm:px-6 lg:px-8 z-10 shrink-0">
                <div class="flex items-center gap-4">
                    <button @click="sidebarOpen = true" class="md:hidden p-2 rounded-lg text-gray-500 hover:bg-gray-100 focus:outline-none">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    </button>
                    <h2 class="text-lg font-bold text-gray-900 hidden sm:block">Manajemen Pesanan</h2>
                </div>
                
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-3 pl-4 border-l border-gray-200">
                        <div class="w-8 h-8 rounded-full bg-red-100 text-red-600 flex items-center justify-center font-black text-sm">
                            {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                        </div>
                        <div class="hidden md:block text-sm">
                            <p class="font-bold text-gray-900 leading-tight">{{ Auth::user()->name }}</p>
                            <p class="text-[10px] text-gray-500 font-semibold uppercase">{{ Auth::user()->role }}</p>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Notifikasi Sukses / Error -->
            @if(session('success'))
                <div class="mx-6 mt-6 p-4 bg-green-50 border border-green-200 text-green-700 rounded-xl font-bold text-xs sm:text-sm flex items-center gap-3 shadow-sm shrink-0">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            @if(session('error'))
                <div class="mx-6 mt-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl font-bold text-xs sm:text-sm flex items-center gap-3 shadow-sm shrink-0">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            <!-- Isi Konten Tabel Pesanan -->
            <div class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">

                <!-- Banner Pengingat Verifikasi Pembayaran -->
                @if($pendingVerificationCount > 0 && request('payment_status') !== 'pending_verification')
                    <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-2xl flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-sm">
                        <div class="flex items-center gap-3">
                            <span class="w-9 h-9 rounded-xl bg-blue-600 text-white flex items-center justify-center font-black text-sm shrink-0">
                                {{ $pendingVerificationCount }}
                            </span>
                            <div>
                                <p class="text-xs sm:text-sm font-black text-blue-950">Ada {{ $pendingVerificationCount }} Bukti Pembayaran Menunggu Verifikasi</p>
                                <p class="text-[11px] text-blue-700">Periksa kesesuaian nominal transfer pelanggan sebelum memproses pengiriman.</p>
                            </div>
                        </div>
                        <a href="{{ route('admin.orders.index', array_merge(request()->query(), ['payment_status' => 'pending_verification'])) }}"
                           class="inline-flex items-center justify-center text-[11px] font-black uppercase tracking-wider bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-xl transition shrink-0">
                            Filter Perlu Verifikasi →
                        </a>
                    </div>
                @endif
                
                <div class="flex flex-col gap-4 mb-6">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div>
                            <h1 class="text-2xl sm:text-3xl font-black text-gray-900 uppercase tracking-tight">Daftar <span class="text-red-600">Pesanan</span></h1>
                            <p class="text-xs sm:text-sm text-gray-500 font-medium mt-1">Pantau transaksi masuk, filter waktu order, verifikasi bukti bayar, dan ekspor rekap CSV.</p>
                        </div>

                        <a href="{{ route('admin.orders.export-csv', request()->query()) }}"
                           class="inline-flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black uppercase tracking-wider px-4 py-2.5 rounded-xl transition shadow-sm shrink-0">
                            ⬇ Export CSV
                        </a>
                    </div>

                    <!-- Filter Pencarian, Waktu, Pembayaran & Status Pesanan -->
                    <form method="GET" action="{{ route('admin.orders.index') }}" class="flex flex-wrap items-center gap-2">
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari Order ID / Nama / Resi / Tanggal..."
                               class="bg-white border border-gray-200 rounded-xl px-3.5 py-2 text-xs font-medium focus:outline-none focus:ring-2 focus:ring-red-600">

                        <select name="time_filter" onchange="this.form.submit()"
                                class="bg-white border border-gray-200 rounded-xl px-3 py-2 text-xs font-bold text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-600">
                            <option value="">Semua Waktu</option>
                            <option value="today" {{ request('time_filter') === 'today' ? 'selected' : '' }}>Order Hari Ini</option>
                            <option value="yesterday" {{ request('time_filter') === 'yesterday' ? 'selected' : '' }}>Order Kemarin</option>
                            <option value="7days" {{ request('time_filter') === '7days' ? 'selected' : '' }}>7 Hari Terakhir</option>
                            <option value="this_month" {{ request('time_filter') === 'this_month' ? 'selected' : '' }}>Bulan Ini</option>
                        </select>

                        <div class="flex items-center gap-1.5 bg-white border border-gray-200 rounded-xl px-2.5 py-1.5 text-xs">
                            <span class="text-[10px] font-bold uppercase text-gray-400">Dari</span>
                            <input type="date" name="date_from" value="{{ request('date_from') }}" onchange="this.form.submit()"
                                   class="text-xs font-semibold text-gray-700 focus:outline-none">
                            <span class="text-[10px] font-bold uppercase text-gray-400">s/d</span>
                            <input type="date" name="date_until" value="{{ request('date_until') }}" onchange="this.form.submit()"
                                   class="text-xs font-semibold text-gray-700 focus:outline-none">
                        </div>

                        <select name="payment_status" onchange="this.form.submit()"
                                class="bg-white border border-gray-200 rounded-xl px-3 py-2 text-xs font-bold text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-600">
                            <option value="">Semua Pembayaran</option>
                            <option value="pending_verification" {{ request('payment_status') === 'pending_verification' ? 'selected' : '' }}>Perlu Verifikasi</option>
                            <option value="unpaid" {{ request('payment_status') === 'unpaid' ? 'selected' : '' }}>Belum Bayar</option>
                            <option value="paid" {{ request('payment_status') === 'paid' ? 'selected' : '' }}>Lunas</option>
                            <option value="rejected" {{ request('payment_status') === 'rejected' ? 'selected' : '' }}>Bukti Ditolak</option>
                        </select>

                        <select name="status" onchange="this.form.submit()"
                                class="bg-white border border-gray-200 rounded-xl px-3 py-2 text-xs font-bold text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-600">
                            <option value="">Semua Status</option>
                            <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="diproses" {{ request('status') === 'diproses' ? 'selected' : '' }}>Diproses</option>
                            <option value="dikirim" {{ request('status') === 'dikirim' ? 'selected' : '' }}>Dikirim</option>
                            <option value="selesai" {{ request('status') === 'selesai' ? 'selected' : '' }}>Selesai</option>
                            <option value="dibatalkan" {{ in_array(request('status'), ['dibatalkan', 'batal'], true) ? 'selected' : '' }}>Dibatalkan</option>
                        </select>

                        <button type="submit" class="bg-gray-900 hover:bg-red-600 text-white text-xs font-bold px-3.5 py-2 rounded-xl transition">
                            Cari
                        </button>

                        @if($hasActiveFilter)
                            <a href="{{ route('admin.orders.index') }}" class="text-xs font-bold text-gray-500 hover:text-red-600 px-2">Reset</a>
                        @endif
                    </form>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-50 border-b border-gray-100 text-[10px] uppercase tracking-widest text-gray-500 font-bold">
                                    <th class="p-4 whitespace-nowrap">Order ID</th>
                                    <th class="p-4 whitespace-nowrap">Pelanggan</th>
                                    <th class="p-4 whitespace-nowrap">Pembayaran</th>
                                    <th class="p-4 whitespace-nowrap">Waktu & Tanggal</th>
                                    <th class="p-4 whitespace-nowrap">Total Tagihan</th>
                                    <th class="p-4 whitespace-nowrap">Ubah Status</th>
                                    <th class="p-4 whitespace-nowrap text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm font-medium text-gray-700">
                                @forelse($orders as $order)
                                    @php
                                        $orderCode = $order->order_number ?? $order->invoice_number ?? ('ORD-' . str_pad($order->id, 4, '0', STR_PAD_LEFT));
                                        $customerName = $order->name ?? $order->recipient_name ?? optional($order->user)->name ?? 'Pelanggan';
                                        $trackingNumber = $order->tracking_number ?? $order->resi ?? null;

                                        $timeLabel = $order->time_label ?? (
                                            optional($order->created_at)->isToday()
                                                ? 'Hari Ini'
                                                : (optional($order->created_at)->isYesterday() ? 'Kemarin' : optional($order->created_at)->format('d M Y'))
                                        );

                                        $timeBadgeStyle = match($timeLabel) {
                                            'Hari Ini' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                                            'Kemarin'  => 'bg-amber-100 text-amber-800 border-amber-200',
                                            default    => 'bg-gray-100 text-gray-700 border-gray-200',
                                        };

                                        $statusRaw = strtolower($order->status ?? 'pending');
                                        if ($statusRaw === 'batal') {
                                            $statusRaw = 'dibatalkan';
                                        }

                                        $isCod = strtolower($order->payment_method ?? '') === 'cod';
                                        $paymentStatus = strtolower($order->payment_status ?? 'unpaid');
                                        $proofUrl = filled($order->payment_proof ?? null)
                                            ? (filter_var($order->payment_proof, FILTER_VALIDATE_URL) ? $order->payment_proof : asset('storage/' . $order->payment_proof))
                                            : null;

                                        $selectStyle = match($statusRaw) {
                                            'pending'    => 'bg-amber-50 text-amber-800 border-amber-200',
                                            'diproses'   => 'bg-blue-50 text-blue-800 border-blue-200',
                                            'dikirim'    => 'bg-indigo-50 text-indigo-800 border-indigo-200',
                                            'selesai'    => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                                            'dibatalkan' => 'bg-red-50 text-red-800 border-red-200',
                                            default      => 'bg-gray-50 text-gray-800 border-gray-200',
                                        };
                                    @endphp
                                    <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition {{ $paymentStatus === 'pending_verification' ? 'bg-blue-50/30' : '' }}">
                                        <td class="p-4">
                                            <span class="font-bold text-gray-900 block">{{ $orderCode }}</span>
                                            @if(!empty($trackingNumber))
                                                <span class="text-[10px] font-semibold text-indigo-600">Resi: {{ $trackingNumber }}</span>
                                            @endif
                                        </td>

                                        <td class="p-4">
                                            <p class="text-gray-900 font-bold">{{ $customerName }}</p>
                                            <p class="text-[10px] text-gray-500 uppercase">
                                                Kurir: {{ strtoupper($order->shipping_method ?? 'REG') }}
                                            </p>
                                        </td>

                                        <!-- Kolom Status & Bukti Pembayaran -->
                                        <td class="p-4">
                                            <div class="flex flex-col items-start gap-1.5">
                                                <div class="flex items-center gap-1.5 flex-wrap">
                                                    <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded bg-gray-100 text-gray-700 border border-gray-200">
                                                        {{ strtoupper($order->payment_method ?? '-') }}
                                                    </span>

                                                    @if($isCod)
                                                        <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">
                                                            Bayar di Tempat
                                                        </span>
                                                    @elseif($paymentStatus === 'paid')
                                                        <span class="text-[10px] font-black uppercase px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                            ✓ Lunas
                                                        </span>
                                                    @elseif($paymentStatus === 'pending_verification')
                                                        <span class="inline-flex items-center gap-1 text-[10px] font-black uppercase px-2.5 py-0.5 rounded-full bg-blue-100 text-blue-800 border border-blue-300">
                                                            <span class="w-1.5 h-1.5 rounded-full bg-blue-600 animate-ping"></span>
                                                            Perlu Verifikasi
                                                        </span>
                                                    @elseif($paymentStatus === 'rejected')
                                                        <span class="text-[10px] font-black uppercase px-2.5 py-0.5 rounded-full bg-rose-50 text-rose-700 border border-rose-200">
                                                            ✕ Ditolak
                                                        </span>
                                                    @else
                                                        <span class="text-[10px] font-bold uppercase px-2.5 py-0.5 rounded-full bg-amber-50 text-amber-700 border border-amber-200">
                                                            Belum Bayar
                                                        </span>
                                                    @endif
                                                </div>

                                                @if($proofUrl)
                                                    <button type="button"
                                                            @click="proofPreview = {
                                                                url: @js($proofUrl),
                                                                code: @js($orderCode),
                                                                customer: @js($customerName),
                                                                detailUrl: @js(route('admin.orders.show', $order->id)),
                                                                status: @js($paymentStatus)
                                                            }"
                                                            class="inline-flex items-center gap-1 text-[10px] font-bold text-blue-700 hover:text-blue-900 underline underline-offset-2">
                                                        🖼️ Lihat Bukti Bayar
                                                    </button>
                                                @endif
                                            </div>
                                        </td>

                                        <td class="p-4 text-xs whitespace-nowrap">
                                            <span class="inline-block text-[10px] font-black uppercase px-2 py-0.5 rounded-md border mb-1 {{ $timeBadgeStyle }}">
                                                {{ $timeLabel }}
                                            </span>
                                            <span class="block text-gray-500 font-medium">{{ optional($order->created_at)->format('d M Y, H:i') }}</span>
                                        </td>
                                        
                                        <td class="p-4 font-black text-red-600 whitespace-nowrap">
                                            {{ $order->formatted_total ?? 'Rp ' . number_format($order->total_amount ?? $order->total_price ?? 0, 0, ',', '.') }}
                                        </td>

                                        <td class="p-4">
                                            <!-- Form Dropdown Status Interaktif -->
                                            <form action="{{ route('admin.orders.update', $order->id) }}" method="POST">
                                                @csrf
                                                @method('PUT')
                                                <select name="status" onchange="this.form.submit()"
                                                        class="text-xs font-bold rounded-xl border px-3 py-1.5 cursor-pointer focus:outline-none focus:ring-2 focus:ring-red-600 {{ $selectStyle }}">
                                                    <option value="pending" {{ $statusRaw === 'pending' ? 'selected' : '' }}>Pending</option>
                                                    <option value="diproses" {{ $statusRaw === 'diproses' ? 'selected' : '' }}>Diproses</option>
                                                    <option value="dikirim" {{ $statusRaw === 'dikirim' ? 'selected' : '' }}>Dikirim</option>
                                                    <option value="selesai" {{ $statusRaw === 'selesai' ? 'selected' : '' }}>Selesai</option>
                                                    <option value="dibatalkan" {{ $statusRaw === 'dibatalkan' ? 'selected' : '' }}>Batal</option>
                                                </select>
                                            </form>
                                        </td>

                                        <td class="p-4 text-center whitespace-nowrap">
                                            <a href="{{ route('admin.orders.show', $order->id) }}"
                                               class="inline-flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-widest px-3.5 py-2 rounded-lg transition shadow-sm {{ $paymentStatus === 'pending_verification' ? 'bg-blue-600 hover:bg-blue-700 text-white' : 'bg-gray-900 hover:bg-red-600 text-white' }}">
                                                {{ $paymentStatus === 'pending_verification' ? 'Verifikasi' : 'Detail' }}
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="p-10 text-center text-gray-400 font-medium text-sm">
                                            {{ $hasActiveFilter ? 'Tidak ada pesanan yang cocok dengan filter pencarian/waktu.' : 'Belum ada data pesanan masuk.' }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    @if($orders->hasPages())
                        <div class="p-4 border-t border-gray-100">
                            {{ $orders->links() }}
                        </div>
                    @endif
                </div>

            </div>
        </main>
    </div>

    <!-- Modal Pratinjau Cepat Bukti Pembayaran -->
    <div x-show="proofPreview !== null"
         x-cloak
         x-transition.opacity
         @click="proofPreview = null"
         class="fixed inset-0 z-50 bg-gray-950/85 backdrop-blur-sm flex items-center justify-center p-4">
        <template x-if="proofPreview">
            <div @click.stop class="bg-white rounded-2xl max-w-lg w-full overflow-hidden shadow-2xl border border-gray-200">
                <div class="bg-gray-900 px-5 py-3.5 flex items-center justify-between text-white">
                    <div>
                        <span class="text-[10px] font-black uppercase tracking-widest text-red-500 block">Pratinjau Bukti Transfer</span>
                        <h3 class="text-xs font-bold mt-0.5" x-text="'#' + proofPreview.code + ' • ' + proofPreview.customer"></h3>
                    </div>
                    <button type="button" @click="proofPreview = null" class="w-8 h-8 rounded-lg bg-gray-800 hover:bg-red-600 text-gray-300 hover:text-white flex items-center justify-center text-xs transition">✕</button>
                </div>

                <div class="p-4 bg-gray-100 flex items-center justify-center max-h-[65vh] overflow-auto">
                    <img :src="proofPreview.url" alt="Bukti Pembayaran" class="max-h-[60vh] w-auto object-contain rounded-lg shadow">
                </div>

                <div class="px-5 py-3.5 bg-white border-t border-gray-100 flex items-center justify-between gap-2">
                    <a :href="proofPreview.url" target="_blank" rel="noopener" class="text-xs font-bold text-gray-600 hover:text-gray-900 underline">
                        Buka Ukuran Penuh ↗
                    </a>
                    <a :href="proofPreview.detailUrl"
                       class="bg-red-600 hover:bg-red-700 text-white text-xs font-black uppercase tracking-wider px-4 py-2 rounded-xl transition shadow-sm">
                        Buka Detail & Verifikasi →
                    </a>
                </div>
            </div>
        </template>
    </div>

</body>
</html>