<!DOCTYPE html>
<html lang="id" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pesanan | Admin Sport Mania</title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
        'resources/css/pages/admin-orders.css',
    ])
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
</head>
<body class="admin-orders-page h-full antialiased text-gray-900"
      x-data="adminOrdersPage()">

    <div class="min-h-screen flex flex-col">
        <!-- Top Navbar Admin -->
        <header class="bg-gray-900 text-white border-b border-gray-800 sticky top-0 z-30">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <span class="font-black tracking-tighter uppercase italic text-lg"><span class="text-red-600">SPORT</span>MANIA ADMIN</span>
                </div>
                <a href="{{ route('admin.dashboard') }}" class="text-xs font-bold bg-gray-800 hover:bg-gray-700 text-gray-200 px-4 py-2 rounded-xl transition">
                    ← Kembali ke Dashboard
                </a>
            </div>
        </header>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 flex-grow w-full">
            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-8">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-black uppercase tracking-tight text-gray-900">Manajemen <span class="text-red-600">Pesanan Masuk</span></h1>
                    <p class="text-xs sm:text-sm text-gray-500 font-medium mt-1">Kelola status pesanan pelanggan dari panel admin.</p>
                </div>

                <!-- Pencarian Pesanan -->
                <div class="relative w-full sm:w-72">
                    <input type="text"
                           x-model="searchQuery"
                           placeholder="Cari ID order, pelanggan, produk..."
                           class="w-full bg-white border border-gray-200 rounded-xl pl-3.5 pr-8 py-2.5 text-xs font-medium text-gray-900 focus:outline-none focus:ring-2 focus:ring-red-600 shadow-sm">
                    <button type="button"
                            x-show="searchQuery.length > 0"
                            x-cloak
                            @click="searchQuery = ''"
                            class="absolute right-2.5 top-2.5 text-gray-400 hover:text-red-600 text-xs font-bold">✕</button>
                </div>
            </div>

            @if(session('success'))
                <div class="mb-6 bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs font-bold p-4 rounded-2xl shadow-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="mb-6 bg-red-50 border border-red-200 text-red-700 text-xs font-bold p-4 rounded-2xl shadow-sm">
                    {{ session('error') }}
                </div>
            @endif

            <!-- Ringkasan Statistik Singkat -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">
                <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm">
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Total Pesanan</p>
                    <p class="text-2xl font-black text-gray-900 mt-1">{{ $orders->count() }}</p>
                </div>
                <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm">
                    <p class="text-[10px] font-black uppercase tracking-widest text-amber-500">Menunggu (Pending)</p>
                    <p class="text-2xl font-black text-gray-900 mt-1">{{ $orders->where('status', 'pending')->count() }}</p>
                </div>
                <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm">
                    <p class="text-[10px] font-black uppercase tracking-widest text-blue-600">Diproses & Dikirim</p>
                    <p class="text-2xl font-black text-gray-900 mt-1">{{ $orders->whereIn('status', ['paid', 'shipped'])->count() }}</p>
                </div>
                <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm">
                    <p class="text-[10px] font-black uppercase tracking-widest text-emerald-600">Selesai (Completed)</p>
                    <p class="text-2xl font-black text-gray-900 mt-1">{{ $orders->where('status', 'completed')->count() }}</p>
                </div>
            </div>

            <!-- Filter Tab Status -->
            <div class="flex flex-wrap gap-2 mb-6">
                @php
                    $tabs = [
                        'semua' => 'Semua (' . $orders->count() . ')',
                        'pending' => 'Pending (' . $orders->where('status', 'pending')->count() . ')',
                        'paid' => 'Paid (' . $orders->where('status', 'paid')->count() . ')',
                        'shipped' => 'Shipped (' . $orders->where('status', 'shipped')->count() . ')',
                        'completed' => 'Completed (' . $orders->where('status', 'completed')->count() . ')',
                        'cancelled' => 'Cancelled (' . $orders->where('status', 'cancelled')->count() . ')',
                    ];
                @endphp
                @foreach($tabs as $key => $label)
                    <button type="button"
                            @click="statusFilter = '{{ $key }}'"
                            :class="statusFilter === '{{ $key }}' ? 'bg-gray-900 text-white shadow-sm' : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200'"
                            class="px-3.5 py-2 rounded-xl text-xs font-bold uppercase tracking-wider transition">
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            <!-- Tabel Pesanan -->
            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50/70 border-b border-gray-100 text-[10px] font-black uppercase tracking-widest text-gray-400">
                                <th class="p-4 sm:p-5">ID / Waktu</th>
                                <th class="p-4 sm:p-5">Pelanggan</th>
                                <th class="p-4 sm:p-5">Detail Produk</th>
                                <th class="p-4 sm:p-5">Total Harga</th>
                                <th class="p-4 sm:p-5">Status Pesanan</th>
                                <th class="p-4 sm:p-5 text-center">Aksi Ubah Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-xs sm:text-sm">
                            @forelse($orders as $order)
                            @php
                                $customerName = optional($order->user)->name ?? 'Tamu / Guest';
                                $productNames = $order->items->map(fn($i) => optional($i->product)->name ?? '')->implode(' ');
                                $searchBlob = strtolower('#ord-' . $order->id . ' ' . $order->id . ' ' . $customerName . ' ' . $order->shipping_address . ' ' . $productNames);

                                $statusColors = [
                                    'pending' => 'bg-amber-50 text-amber-600 border-amber-200',
                                    'paid' => 'bg-blue-50 text-blue-600 border-blue-200',
                                    'shipped' => 'bg-purple-50 text-purple-600 border-purple-200',
                                    'completed' => 'bg-emerald-50 text-emerald-600 border-emerald-200',
                                    'cancelled' => 'bg-red-50 text-red-600 border-red-200',
                                ];
                            @endphp
                            <tr data-status="{{ strtolower($order->status) }}"
                                data-search="{{ $searchBlob }}"
                                x-show="matchesRow($el.dataset.status, $el.dataset.search)"
                                class="hover:bg-gray-50/50 transition">
                                <td class="p-4 sm:p-5 font-bold align-top">
                                    <span class="text-gray-900">#ORD-{{ $order->id }}</span>
                                    <p class="text-[10px] text-gray-400 font-medium mt-0.5">{{ $order->created_at->format('d M Y, H:i') }}</p>
                                </td>
                                <td class="p-4 sm:p-5 align-top">
                                    <p class="font-bold text-gray-900">{{ $customerName }}</p>
                                    @if(optional($order->user)->email)
                                        <p class="text-[10px] text-gray-400 font-medium">{{ $order->user->email }}</p>
                                    @endif
                                    <p class="text-[11px] text-gray-500 mt-1 leading-relaxed max-w-xs">{{ $order->shipping_address }}</p>
                                </td>
                                <td class="p-4 sm:p-5 align-top">
                                    <ul class="space-y-1.5 text-xs">
                                        @foreach($order->items as $item)
                                        @php
                                            $itemSize = $item->size ?? optional($item->variant)->size ?? null;
                                            $itemColor = $item->color ?? optional($item->variant)->color ?? null;
                                        @endphp
                                        <li class="flex flex-wrap items-center gap-1.5">
                                            <span class="font-bold text-gray-800">{{ optional($item->product)->name ?? 'Produk Dihapus' }}</span>
                                            @if(($itemSize && $itemSize !== 'All Size') || ($itemColor && $itemColor !== '-'))
                                                <span class="bg-gray-100 text-gray-600 text-[10px] font-bold px-2 py-0.5 rounded-md">
                                                    {{ collect([$itemSize, $itemColor])->filter(fn($v) => $v && $v !== '-')->implode(' • ') }}
                                                </span>
                                            @endif
                                            <span class="font-black text-red-600">x{{ $item->quantity }}</span>
                                            <span class="text-gray-400 text-[11px]">(Rp {{ number_format($item->price, 0, ',', '.') }})</span>
                                        </li>
                                        @endforeach
                                    </ul>
                                </td>
                                <td class="p-4 sm:p-5 font-black text-gray-900 align-top whitespace-nowrap">
                                    Rp {{ number_format($order->total_price, 0, ',', '.') }}
                                </td>
                                <td class="p-4 sm:p-5 align-top">
                                    <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider border {{ $statusColors[$order->status] ?? 'bg-gray-100 text-gray-600' }}">
                                        {{ $order->status }}
                                    </span>
                                </td>
                                <td class="p-4 sm:p-5 text-center align-top">
                                    <form action="{{ route('admin.orders.updateStatus', $order->id) }}" method="POST" class="inline-flex items-center gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <select name="status"
                                                data-current="{{ $order->status }}"
                                                @change="handleStatusChange($el, {{ $order->id }})"
                                                class="text-xs bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 font-bold focus:outline-none focus:border-gray-900 transition cursor-pointer">
                                            <option value="pending" {{ $order->status == 'pending' ? 'selected' : '' }}>Pending</option>
                                            <option value="paid" {{ $order->status == 'paid' ? 'selected' : '' }}>Paid</option>
                                            <option value="shipped" {{ $order->status == 'shipped' ? 'selected' : '' }}>Shipped</option>
                                            <option value="completed" {{ $order->status == 'completed' ? 'selected' : '' }}>Completed</option>
                                            <option value="cancelled" {{ $order->status == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                                        </select>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="p-12 text-center text-gray-400 font-medium">
                                    Belum ada data pesanan masuk.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

</body>
</html>