<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Detail Pesanan #') . $order->order_number }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            
            <!-- Tombol Kembali & Notifikasi Sukses -->
            <div class="flex justify-between items-center">
                <a href="{{ route('admin.orders.index') }}" class="text-xs font-bold text-gray-500 hover:text-red-600 transition flex items-center gap-1">
                    ← Kembali ke Daftar Pesanan
                </a>
                <span class="text-xs text-gray-400 font-medium">Tanggal Transaksi: {{ $order->created_at->format('d M Y, H:i') }}</span>
            </div>

            @if(session('success'))
                <div class="p-4 bg-green-50 border border-green-200 text-green-700 text-xs rounded-2xl font-bold">
                    {{ session('success') }}
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <!-- Kolom Kiri: Rincian Produk yang Dibeli -->
                <div class="lg:col-span-2 space-y-6">
                    <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-sm">
                        <h3 class="text-xs font-black uppercase tracking-wider text-gray-900 mb-4">Daftar Produk Pesanan</h3>
                        
                        <div class="divide-y divide-gray-100">
                            @foreach($order->items as $item)
                                <div class="py-4 flex items-center justify-between gap-4 first:pt-0 last:pb-0">
                                    <div class="flex items-center gap-4">
                                        @if($item->product && $item->product->image)
                                            <img src="{{ asset('storage/' . $item->product->image) }}" class="w-14 h-16 rounded-xl object-cover border">
                                        @else
                                            <div class="w-14 h-16 rounded-xl bg-gray-100 flex items-center justify-center text-[10px] text-gray-400 font-bold">No Image</div>
                                        @endif
                                        <div>
                                            <h4 class="text-xs font-bold text-gray-900">{{ $item->product->name ?? 'Produk Dihapus' }}</h4>
                                            <p class="text-[10px] text-gray-500 mt-0.5">
                                                Varian: <span class="font-bold text-gray-700">{{ $item->size ?? 'All Size' }} / {{ $item->color ?? '-' }}</span>
                                            </p>
                                            <p class="text-[10px] text-gray-500">Jumlah: <span class="font-bold text-gray-900">{{ $item->quantity }} pcs</span></p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-xs font-black text-gray-900">Rp {{ number_format($item->price * $item->quantity, 0, ',', '.') }}</p>
                                        <p class="text-[10px] text-gray-400">(@ Rp {{ number_format($item->price, 0, ',', '.') }})</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <!-- Total Pembayaran -->
                        <div class="mt-6 pt-6 border-t border-gray-100 flex justify-between items-center">
                            <span class="text-xs font-bold uppercase text-gray-500">Total Keseluruhan</span>
                            <span class="text-lg font-black text-red-600">Rp {{ number_format($order->total_price ?? $order->total_amount, 0, ',', '.') }}</span>
                        </div>
                    </div>
                </div>

                <!-- Kolom Kanan: Informasi Pelanggan & Form Update Status -->
                <div class="space-y-6">
                    
                    <!-- Informasi Pelanggan & Alamat Pengiriman -->
                    <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-sm space-y-4">
                        <h3 class="text-xs font-black uppercase tracking-wider text-gray-900">Informasi Pengiriman</h3>
                        
                        <div class="text-xs space-y-2">
                            <div>
                                <span class="text-gray-400 block text-[10px] uppercase font-bold">Nama Penerima</span>
                                <span class="font-bold text-gray-900">{{ $order->name }}</span>
                            </div>
                            <div>
                                <span class="text-gray-400 block text-[10px] uppercase font-bold">No. WhatsApp / Telepon</span>
                                <span class="font-bold text-gray-900">{{ $order->phone ?? '-' }}</span>
                            </div>
                            <div>
                                <span class="text-gray-400 block text-[10px] uppercase font-bold">Kota / Kabupaten</span>
                                <span class="font-bold text-gray-900">{{ $order->city ?? '-' }}</span>
                            </div>
                            <div>
                                <span class="text-gray-400 block text-[10px] uppercase font-bold">Alamat Lengkap</span>
                                <p class="font-medium text-gray-700 leading-relaxed mt-0.5">{{ $order->address ?? '-' }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Form Update Status & Resi oleh Admin -->
                    <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-sm">
                        <h3 class="text-xs font-black uppercase tracking-wider text-gray-900 mb-4">Kelola Status Pesanan</h3>
                        
                        <form action="{{ route('admin.orders.update', $order->id) }}" method="POST" class="space-y-4">
                            @csrf
                            @method('PUT')

                            <div>
                                <label class="block text-xs font-bold uppercase text-gray-700 mb-1">Status Pesanan</label>
                                <select name="status" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-xs font-bold focus:ring-2 focus:ring-red-600 focus:outline-none">
                                    <option value="pending" {{ $order->status == 'pending' ? 'selected' : '' }}>Pending (Menunggu)</option>
                                    <option value="diproses" {{ $order->status == 'diproses' ? 'selected' : '' }}>Diproses (Dikemas)</option>
                                    <option value="dikirim" {{ $order->status == 'dikirim' ? 'selected' : '' }}>Dikirim (Dalam Perjalanan)</option>
                                    <option value="selesai" {{ $order->status == 'selesai' ? 'selected' : '' }}>Selesai</option>
                                    <option value="dibatalkan" {{ $order->status == 'dibatalkan' ? 'selected' : '' }}>Dibatalkan</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-bold uppercase text-gray-700 mb-1">Nomor Resi Pengiriman</label>
                                <input type="text" name="tracking_number" value="{{ old('tracking_number', $order->tracking_number) }}" placeholder="Contoh: JNT-882910392" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-xs font-medium focus:ring-2 focus:ring-red-600 focus:outline-none">
                            </div>

                            <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white py-3.5 rounded-xl font-bold text-xs uppercase tracking-widest transition shadow-lg shadow-red-600/20">
                                Perbarui Status Pesanan
                            </button>
                        </form>
                    </div>

                </div>

            </div>

        </div>
    </div>
</x-app-layout>