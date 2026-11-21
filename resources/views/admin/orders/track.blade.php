<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lacak Pesanan | Sport Mania Distro</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#FAFAFA] text-gray-900 antialiased min-h-screen flex flex-col justify-between">
    <nav class="bg-white border-b border-gray-100 py-4 px-4">
        <div class="max-w-4xl mx-auto flex justify-between items-center">
            <a href="{{ route('home') }}" class="text-xl font-black tracking-tighter uppercase italic">
                <span class="text-red-600">SPORT</span><span class="text-gray-900">MANIA</span>
            </a>
            <a href="{{ route('home') }}" class="text-xs font-bold text-gray-600 hover:text-red-600 uppercase tracking-wider">
                ← Kembali ke Beranda
            </a>
        </div>
    </nav>

    <main class="max-w-2xl w-full mx-auto px-4 py-12 flex-1">
        <div class="bg-white rounded-3xl p-6 sm:p-8 shadow-sm border border-gray-200 space-y-6">
            <div class="text-center space-y-2">
                <span class="inline-block bg-red-50 text-red-600 text-[10px] font-black uppercase tracking-widest px-3 py-1 rounded-full">
                    Order Tracking
                </span>
                <h1 class="text-2xl sm:text-3xl font-black uppercase tracking-tight">Lacak Status Pesanan</h1>
                <p class="text-xs text-gray-500">Masukkan kode invoice atau ID pesanan untuk memantau status pengiriman.</p>
            </div>

            @if(session('error'))
                <div class="p-3.5 rounded-xl bg-red-50 border border-red-200 text-red-700 text-xs font-bold">
                    {{ session('error') }}
                </div>
            @endif

            <form method="POST" action="{{ route('order.track') }}" class="flex flex-col sm:flex-row gap-3">
                @csrf
                <input type="text"
                       name="order_code"
                       value="{{ old('order_code', $orderCode ?? '') }}"
                       placeholder="Contoh: INV-20261121-001 atau 12"
                       required
                       class="flex-1 bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-red-600">
                <button type="submit" class="bg-gray-900 hover:bg-red-600 text-white font-black text-xs uppercase tracking-wider px-6 py-3 rounded-xl transition">
                    Lacak Sekarang
                </button>
            </form>

            @if(isset($order) && $order)
                <div class="border-t border-gray-100 pt-6 space-y-4">
                    <div class="flex flex-wrap justify-between items-center gap-2 bg-gray-50 p-4 rounded-2xl border border-gray-200">
                        <div>
                            <p class="text-[10px] font-bold uppercase text-gray-400">Kode Pesanan</p>
                            <p class="text-sm font-black text-gray-900">{{ $order->order_number ?? $order->invoice_number ?? '#' . $order->id }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-[10px] font-bold uppercase text-gray-400">Status Saat Ini</p>
                            <span class="inline-block mt-0.5 px-3 py-1 rounded-lg text-xs font-black uppercase bg-red-600 text-white">
                                {{ $order->status }}
                            </span>
                        </div>
                    </div>

                    @if(!empty($order->tracking_number ?? $order->resi))
                        <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-xs">
                            <span class="font-bold text-emerald-800">Nomor Resi Pengiriman:</span>
                            <span class="font-black text-emerald-950 ml-1">{{ $order->tracking_number ?? $order->resi }}</span>
                        </div>
                    @endif

                    <div class="text-xs text-gray-600 space-y-1">
                        <p><span class="font-bold">Tanggal Order:</span> {{ $order->created_at?->format('d M Y, H:i') }} WIB</p>
                        <p><span class="font-bold">Total Pembayaran:</span> Rp {{ number_format($order->total_price ?? $order->total_amount ?? $order->grand_total ?? 0, 0, ',', '.') }}</p>
                    </div>
                </div>
            @endif
        </div>
    </main>

    <footer class="text-center py-6 text-xs text-gray-400">
        © 2026 Sport Mania Distro Malang
    </footer>
</body>
</html>