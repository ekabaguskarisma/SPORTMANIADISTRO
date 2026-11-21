<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekap Penjualan Bulanan - Admin Sport Mania Distro</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        [x-cloak] { display: none !important; }
        @media print {
            .no-print { display: none !important; }
            body { background: #ffffff !important; }
            .print-full { overflow: visible !important; height: auto !important; }
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 antialiased" x-data="{ sidebarOpen: false }">

    @php
        $namaBulanList = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
        $namaBulanAktif = $namaBulanList[(int) $bulan] ?? 'Bulan';
        $tahunSekarang = (int) now()->year;
    @endphp

    <div class="flex h-screen overflow-hidden print-full">

        <!-- Sidebar Desktop -->
        <aside class="no-print hidden md:flex flex-col w-64 bg-gray-900 text-white transition-all duration-300 z-20">
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
                <a href="{{ route('admin.orders.index') }}" class="flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-xl text-gray-400 hover:text-white hover:bg-gray-800 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                    Pesanan
                </a>
                <a href="{{ route('admin.rekap.bulanan') }}" class="flex items-center gap-3 px-4 py-3 text-sm font-bold rounded-xl bg-red-600 text-white shadow-lg shadow-red-600/20 transition">
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

        <!-- Sidebar Mobile -->
        <div x-show="sidebarOpen" x-transition.opacity class="no-print fixed inset-0 bg-gray-900/80 z-30 md:hidden backdrop-blur-sm" @click="sidebarOpen = false" x-cloak></div>
        <aside x-show="sidebarOpen" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition ease-in duration-300" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full" class="no-print fixed inset-y-0 left-0 flex flex-col w-64 bg-gray-900 text-white z-40 md:hidden" x-cloak>
            <div class="flex items-center justify-between h-20 px-6 border-b border-gray-800">
                <span class="text-xl font-black uppercase italic"><span class="text-red-500">SPORT</span>MANIA</span>
                <button @click="sidebarOpen = false" class="text-gray-400 hover:text-white">✕</button>
            </div>
            <nav class="flex-1 overflow-y-auto py-6 px-4 space-y-2">
                <a href="{{ route('admin.dashboard') }}" class="block px-4 py-3 text-sm font-medium rounded-xl text-gray-400 hover:text-white hover:bg-gray-800">Dashboard</a>
                <a href="{{ route('admin.products.index') }}" class="block px-4 py-3 text-sm font-medium rounded-xl text-gray-400 hover:text-white hover:bg-gray-800">Produk</a>
                <a href="{{ route('admin.orders.index') }}" class="block px-4 py-3 text-sm font-medium rounded-xl text-gray-400 hover:text-white hover:bg-gray-800">Pesanan</a>
                <a href="{{ route('admin.rekap.bulanan') }}" class="block px-4 py-3 text-sm font-bold rounded-xl bg-red-600 text-white shadow-lg shadow-red-600/20">Rekap Bulanan</a>
            </nav>
            <div class="p-4 border-t border-gray-800">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full text-left px-4 py-3 text-sm font-bold rounded-xl text-red-400 hover:text-white hover:bg-red-600">Logout</button>
                </form>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 flex flex-col h-screen overflow-hidden bg-gray-50 print-full">

            <!-- Header -->
            <header class="no-print h-20 bg-white border-b border-gray-200 flex items-center justify-between px-4 sm:px-6 lg:px-8 shrink-0">
                <div class="flex items-center gap-4">
                    <button @click="sidebarOpen = true" class="md:hidden p-2 rounded-lg text-gray-500 hover:bg-gray-100 focus:outline-none">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    </button>
                    <h2 class="text-lg font-bold text-gray-900">Rekapitulasi Penjualan Bulanan</h2>
                </div>

                <div class="flex items-center gap-3">
                    <button type="button" onclick="window.print()"
                            class="inline-flex items-center gap-2 bg-gray-900 hover:bg-red-600 text-white text-xs font-black uppercase tracking-wider px-4 py-2.5 rounded-xl transition shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                        Cetak Laporan
                    </button>
                </div>
            </header>

            <!-- Scrollable Content -->
            <div class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 space-y-6 print-full">

                <!-- Judul & Filter Periode -->
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                    <div>
                        <span class="text-[10px] font-black uppercase tracking-widest text-red-600">Laporan Keuangan & Transaksi</span>
                        <h1 class="text-2xl sm:text-3xl font-black text-gray-900 uppercase tracking-tight">
                            Periode <span class="text-red-600">{{ $namaBulanAktif }} {{ $tahun }}</span>
                        </h1>
                    </div>

                    <form method="GET" action="{{ route('admin.rekap.bulanan') }}" class="no-print flex flex-wrap items-center gap-2">
                        <select name="bulan" class="bg-white border border-gray-200 rounded-xl px-3.5 py-2.5 text-xs font-bold text-gray-800 focus:outline-none focus:ring-2 focus:ring-red-600">
                            @foreach($namaBulanList as $num => $label)
                                <option value="{{ $num }}" {{ (int) $bulan === $num ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>

                        <select name="tahun" class="bg-white border border-gray-200 rounded-xl px-3.5 py-2.5 text-xs font-bold text-gray-800 focus:outline-none focus:ring-2 focus:ring-red-600">
                            @for($y = $tahunSekarang; $y >= $tahunSekarang - 4; $y--)
                                <option value="{{ $y }}" {{ (int) $tahun === $y ? 'selected' : '' }}>{{ $y }}</option>
                            @endfor
                        </select>

                        <button type="submit" class="bg-red-600 hover:bg-red-700 text-white text-xs font-black uppercase tracking-wider px-4 py-2.5 rounded-xl transition shadow-sm">
                            Tampilkan
                        </button>
                    </form>
                </div>

                <!-- Kartu Ringkasan Statistik -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm">
                        <span class="text-[10px] font-black uppercase tracking-widest text-emerald-600 block">Omzet Selesai (Realisasi)</span>
                        <p class="text-2xl font-black text-gray-900 mt-1">Rp {{ number_format($omzetSelesai, 0, ',', '.') }}</p>
                        <p class="text-[11px] text-gray-400 font-medium mt-1">Dari {{ $transaksiSelesai }} pesanan selesai</p>
                    </div>

                    <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm">
                        <span class="text-[10px] font-black uppercase tracking-widest text-blue-600 block">Omzet Berjalan (Pipeline)</span>
                        <p class="text-2xl font-black text-gray-900 mt-1">Rp {{ number_format($omzetPipeline, 0, ',', '.') }}</p>
                        <p class="text-[11px] text-gray-400 font-medium mt-1">Pending, diproses & dikirim</p>
                    </div>

                    <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm">
                        <span class="text-[10px] font-black uppercase tracking-widest text-gray-500 block">Total Transaksi Masuk</span>
                        <p class="text-2xl font-black text-gray-900 mt-1">{{ $totalTransaksi }} <span class="text-xs font-bold text-gray-400">Order</span></p>
                        <p class="text-[11px] text-emerald-600 font-bold mt-1">{{ $transaksiSelesai }} selesai • {{ max(0, $totalTransaksi - $transaksiSelesai - $transaksiBatal) }} aktif</p>
                    </div>

                    <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm">
                        <span class="text-[10px] font-black uppercase tracking-widest text-red-600 block">Pesanan Dibatalkan</span>
                        <p class="text-2xl font-black text-gray-900 mt-1">{{ $transaksiBatal }} <span class="text-xs font-bold text-gray-400">Order</span></p>
                        <p class="text-[11px] text-gray-400 font-medium mt-1">Stok telah dikembalikan</p>
                    </div>
                </div>

                <!-- Rekap Berdasarkan Metode Pembayaran -->
                <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm">
                    <h3 class="text-xs font-black uppercase tracking-wider text-gray-400 mb-4">Ringkasan Metode Pembayaran (Transaksi Aktif & Selesai)</h3>

                    @if($rekapMetode->count() > 0)
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach($rekapMetode as $metode => $data)
                                <div class="p-4 rounded-xl bg-gray-50 border border-gray-200/70 flex items-center justify-between">
                                    <div>
                                        <span class="text-xs font-black uppercase text-gray-900 block">{{ filled($metode) ? strtoupper($metode) : 'LAINNYA' }}</span>
                                        <span class="text-[11px] text-gray-500 font-semibold">{{ $data['jumlah'] }} transaksi</span>
                                    </div>
                                    <span class="text-sm font-black text-red-600">
                                        Rp {{ number_format($data['nominal'], 0, ',', '.') }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-xs text-gray-400 font-medium">Belum ada transaksi aktif maupun selesai pada periode ini.</p>
                    @endif
                </div>

                <!-- Tabel Rincian Transaksi Bulanan -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <h3 class="text-xs font-black uppercase tracking-wider text-gray-700">Rincian Transaksi {{ $namaBulanAktif }} {{ $tahun }}</h3>
                        <span class="text-[11px] font-bold text-gray-400">{{ $orders->count() }} Data</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-50 border-b border-gray-100 text-[10px] uppercase tracking-widest text-gray-500 font-bold">
                                    <th class="p-4">No</th>
                                    <th class="p-4">Order ID</th>
                                    <th class="p-4">Tanggal</th>
                                    <th class="p-4">Pelanggan</th>
                                    <th class="p-4">Metode</th>
                                    <th class="p-4">Status</th>
                                    <th class="p-4 text-right">Nominal</th>
                                </tr>
                            </thead>
                            <tbody class="text-xs font-medium text-gray-700 divide-y divide-gray-100">
                                @forelse($orders as $idx => $order)
                                    @php
                                        $orderCode = $order->order_number ?? $order->invoice_number ?? ('ORD-' . str_pad($order->id, 4, '0', STR_PAD_LEFT));
                                        $customerName = $order->name ?? $order->recipient_name ?? optional($order->user)->name ?? 'Pelanggan';
                                        $statusRaw = strtolower($order->status ?? 'pending');
                                        if ($statusRaw === 'batal') {
                                            $statusRaw = 'dibatalkan';
                                        }
                                        $nominal = (float) ($order->total_price ?? $order->total_amount ?? 0);

                                        $badgeClass = match($statusRaw) {
                                            'selesai'    => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                            'dikirim'    => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                                            'diproses'   => 'bg-blue-50 text-blue-700 border-blue-200',
                                            'dibatalkan' => 'bg-red-50 text-red-700 border-red-200',
                                            default      => 'bg-amber-50 text-amber-700 border-amber-200',
                                        };
                                    @endphp
                                    <tr class="hover:bg-gray-50/70">
                                        <td class="p-4 text-gray-400 font-bold">{{ $idx + 1 }}</td>
                                        <td class="p-4 font-bold text-gray-900">
                                            <a href="{{ route('admin.orders.show', $order->id) }}" class="hover:text-red-600 transition">
                                                {{ $orderCode }}
                                            </a>
                                        </td>
                                        <td class="p-4 text-gray-500 whitespace-nowrap">{{ optional($order->created_at)->format('d M Y, H:i') }}</td>
                                        <td class="p-4 font-bold text-gray-900">{{ $customerName }}</td>
                                        <td class="p-4 uppercase font-bold text-gray-600">{{ $order->payment_method ?? '-' }}</td>
                                        <td class="p-4">
                                            <span class="inline-block text-[10px] font-black uppercase px-2.5 py-0.5 rounded-full border {{ $badgeClass }}">
                                                {{ $statusRaw }}
                                            </span>
                                        </td>
                                        <td class="p-4 text-right font-black {{ $statusRaw === 'dibatalkan' ? 'text-gray-400 line-through' : 'text-gray-900' }} whitespace-nowrap">
                                            Rp {{ number_format($nominal, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="p-10 text-center text-gray-400 font-medium text-sm">
                                            Tidak ada transaksi pada periode {{ $namaBulanAktif }} {{ $tahun }}.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>
    </div>

</body>
</html>