<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lacak Pesanan | Sport Mania Distro</title>
    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
        'resources/css/pages/track-order.css',
    ])
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
</head>
<body class="track-order-page bg-[#FAFAFA] text-gray-900 min-h-screen flex flex-col justify-between">

    <!-- Header -->
    <nav class="bg-white border-b border-gray-100 sticky top-0 z-30">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
            <a href="{{ route('home') }}" class="flex items-center gap-2.5">
                <img src="{{ asset('image/LOGO.jpeg') }}" alt="Logo" class="h-7 w-auto rounded">
                <span class="text-lg font-black tracking-tighter uppercase italic">
                    <span class="text-red-600">SPORT</span><span class="text-gray-900">MANIA</span>
                </span>
            </a>
            <a href="{{ route('home') }}" class="text-xs font-bold text-gray-600 hover:text-red-600 uppercase tracking-wider">
                ← Kembali ke Toko
            </a>
        </div>
    </nav>

    <main class="max-w-3xl w-full mx-auto px-4 py-12 space-y-8">
        <div class="text-center space-y-2">
            <span class="inline-block bg-red-50 text-red-600 text-[10px] font-black uppercase tracking-widest px-3 py-1 rounded-full">Real-Time Tracking</span>
            <h1 class="text-3xl sm:text-4xl font-black uppercase tracking-tight">Lacak Status <span class="text-red-600 italic">Pesanan</span></h1>
            <p class="text-xs sm:text-sm text-gray-500">Masukkan Kode Invoice dan Nomor WhatsApp yang digunakan saat checkout.</p>
        </div>

        <!-- Form Pencarian -->
        <form method="GET" action="{{ route('order.track') }}" class="bg-white p-6 sm:p-8 rounded-3xl shadow-sm border border-gray-200/80 space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[11px] font-black uppercase tracking-wider text-gray-700 mb-1.5">Kode Transaksi / Invoice</label>
                    <input type="text" name="order_code" value="{{ request('order_code') }}" required
                           placeholder="Contoh: ORD-6AFF03C889257"
                           class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-xs font-bold uppercase focus:outline-none focus:ring-2 focus:ring-red-600">
                </div>
                <div>
                    <label class="block text-[11px] font-black uppercase tracking-wider text-gray-700 mb-1.5">Nomor WhatsApp Penerima</label>
                    <input type="text" name="phone" value="{{ request('phone') }}" required
                           placeholder="Contoh: 082234548092"
                           class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-red-600">
                </div>
            </div>
            <button type="submit" class="w-full bg-gray-900 hover:bg-red-600 text-white font-black text-xs uppercase tracking-widest py-3.5 rounded-xl transition shadow-md">
                🔍 Cek Status Pesanan Sekarang
            </button>
        </form>

        <!-- Hasil Pencarian -->
        @if($searched)
            @if($order)
                @php
                    $status = strtolower($order->status ?? 'pending');
                    $step = match(true) {
                        in_array($status, ['selesai', 'completed', 'delivered']) => 4,
                        in_array($status, ['dikirim', 'shipped']) => 3,
                        in_array($status, ['diproses', 'processing', 'paid']) => 2,
                        in_array($status, ['dibatalkan', 'cancelled', 'batal']) => 0,
                        default => 1,
                    };
                @endphp

                <div class="bg-white p-6 sm:p-8 rounded-3xl shadow-md border border-gray-200 space-y-6">
                    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-gray-100 pb-4">
                        <div>
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Nomor Invoice</p>
                            <h2 class="text-xl font-black text-gray-900">#{{ $order->order_number ?? $order->id }}</h2>
                        </div>
                        <span class="px-3.5 py-1.5 rounded-xl text-xs font-black uppercase tracking-wider
                            {{ $step === 0 ? 'bg-red-100 text-red-700' : ($step === 4 ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-800') }}">
                            {{ strtoupper($order->status) }}
                        </span>
                    </div>

                    @if($step > 0)
                        <!-- Progress Stepper -->
                        <div class="grid grid-cols-4 gap-2 text-center">
                            @foreach(['Menunggu Bayar', 'Diproses Admin', 'Dalam Pengiriman', 'Pesanan Selesai'] as $idx => $label)
                                <div class="space-y-2">
                                    <div class="h-2 rounded-full {{ ($idx + 1) <= $step ? 'bg-red-600' : 'bg-gray-200' }}"></div>
                                    <p class="text-[10px] sm:text-xs font-bold {{ ($idx + 1) <= $step ? 'text-gray-900' : 'text-gray-400' }}">{{ $label }}</p>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if(!empty($order->tracking_number))
                        <div class="bg-gray-900 text-white p-4 rounded-2xl flex items-center justify-between gap-3">
                            <div>
                                <p class="text-[10px] text-gray-400 uppercase font-bold">Nomor Resi Pengiriman</p>
                                <p class="text-sm font-black tracking-wider text-red-400 mt-0.5">{{ $order->tracking_number }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button"
                                        onclick="copyTrackingCode(@js($order->tracking_number), this)"
                                        class="text-xs font-bold bg-white/10 hover:bg-white/20 px-3 py-1.5 rounded-lg transition cursor-pointer">
                                    Salin Resi
                                </button>
                                <span class="text-xs font-bold bg-white/10 px-3 py-1.5 rounded-lg">{{ strtoupper($order->shipping_method ?? 'REGULER') }}</span>
                            </div>
                        </div>
                    @endif

                    <div class="space-y-2 text-xs border-t border-gray-100 pt-4">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Tanggal Transaksi</span>
                            <span class="font-bold">{{ $order->created_at->format('d M Y, H:i') }} WIB</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Total Tagihan</span>
                            <span class="font-black text-red-600 text-sm">Rp {{ number_format($order->total_price, 0, ',', '.') }}</span>
                        </div>
                    </div>
                </div>
            @else
                <div class="bg-red-50 border border-red-200 text-red-700 p-6 rounded-3xl text-center space-y-2">
                    <p class="font-black text-sm">Pesanan Tidak Ditemukan</p>
                    <p class="text-xs">Pastikan kode transaksi dan nomor WhatsApp sudah sesuai dengan data saat pemesanan.</p>
                </div>
            @endif
        @endif
    </main>

    <footer class="py-6 text-center text-xs text-gray-400">
        © 2026 Sport Mania Distro • Malang, Jawa Timur
    </footer>
</body>
</html>