@extends('layouts.app')

@section('content')
@php
    $coverImg = $produk->image 
        ? (filter_var($produk->image, FILTER_VALIDATE_URL) ? $produk->image : asset('storage/' . $produk->image))
        : asset('image/LOGO.jpeg');

    $reviewCount = $produk->reviews->count();
    $avgRating = $reviewCount > 0 ? number_format($produk->reviews->avg('rating'), 1) : null;

    $variantsData = $produk->variants ? $produk->variants->map(fn($v) => [
        'id' => $v->id,
        'size' => $v->size ?? 'All Size',
        'color' => $v->color ?? '-',
        'stock' => (int) $v->stock,
    ])->values()->toArray() : [];

    $colors = collect($variantsData)->pluck('color')->unique()->filter(fn($c) => $c && $c !== '-')->values();
    $sizes = collect($variantsData)->pluck('size')->unique()->filter(fn($s) => $s && $s !== 'All Size')->values();

    $totalStock = count($variantsData) > 0 
        ? collect($variantsData)->sum('stock') 
        : (int) ($produk->stock ?? 10);

    $unitPrice = (float) ($produk->discount_price ?? $produk->price);
@endphp

<div class="bg-white text-gray-900 min-h-screen py-10"
     x-data="{
         product: {
             id: {{ $produk->id }},
             name: @js($produk->name),
             category: @js($produk->category->name ?? 'Sport Apparel'),
             image: @js($coverImg),
             price: {{ $unitPrice }}
         },
         variants: @json($variantsData),
         hasColors: {{ $colors->count() > 0 ? 'true' : 'false' }},
         hasSizes: {{ $sizes->count() > 0 ? 'true' : 'false' }},
         selectedColor: @js($colors->count() === 1 ? $colors->first() : ''),
         selectedSize: @js($sizes->count() === 1 ? $sizes->first() : ''),
         fallbackStock: {{ $totalStock }},
         qty: 1,
         sizeGuideOpen: false,
         toast: { show: false, message: '', type: 'success' },

         triggerToast(message, type = 'success') {
             this.toast.message = message;
             this.toast.type = type;
             this.toast.show = true;
             window.dispatchEvent(new CustomEvent('show-toast', { detail: message }));
             setTimeout(() => { this.toast.show = false; }, 3500);
         },

         get isSelectionComplete() {
             return (!this.hasColors || this.selectedColor !== '') && (!this.hasSizes || this.selectedSize !== '');
         },

         get matchedVariant() {
             if (this.variants.length === 0) return null;
             return this.variants.find(v => {
                 const colorMatch = !this.hasColors || v.color === this.selectedColor;
                 const sizeMatch = !this.hasSizes || v.size === this.selectedSize;
                 return colorMatch && sizeMatch;
             }) || null;
         },

         get currentStock() {
             if (!this.isSelectionComplete) return this.fallbackStock;
             if (this.variants.length > 0) {
                 return this.matchedVariant ? Number(this.matchedVariant.stock) : 0;
             }
             return this.fallbackStock;
         },

         isColorAvailable(color) {
             if (this.variants.length === 0) return this.fallbackStock > 0;
             return this.variants.some(v => v.color === color && Number(v.stock) > 0);
         },

         isSizeAvailable(size) {
             if (this.variants.length === 0) return this.fallbackStock > 0;
             return this.variants.some(v => {
                 const colorOk = !this.hasColors || !this.selectedColor || v.color === this.selectedColor;
                 return v.size === size && colorOk && Number(v.stock) > 0;
             });
         },

         selectColor(color) {
             this.selectedColor = color;
             if (this.isSelectionComplete && this.qty > this.currentStock) {
                 this.qty = Math.max(1, this.currentStock);
             }
         },

         selectSize(size) {
             this.selectedSize = size;
             if (this.isSelectionComplete && this.qty > this.currentStock) {
                 this.qty = Math.max(1, this.currentStock);
             }
         },

         get whatsappUrl() {
             let details = [];
             if (this.selectedColor) details.push('Warna: ' + this.selectedColor);
             if (this.selectedSize) details.push('Ukuran: ' + this.selectedSize);
             const variantText = details.length ? ' (' + details.join(', ') + ')' : '';
             const msg = `Halo Admin Sport Mania Distro, saya ingin memesan *${this.product.name}*${variantText} sebanyak ${this.qty} pcs (Harga satuan: Rp ${Number(this.product.price).toLocaleString('id-ID')}). Mohon info ketersediaannya.`;
             return 'https://wa.me/6282234548092?text=' + encodeURIComponent(msg);
         },

         addToCart() {
             if (this.hasColors && !this.selectedColor) {
                 this.triggerToast('Silakan pilih variasi warna terlebih dahulu.', 'error');
                 return;
             }
             if (this.hasSizes && !this.selectedSize) {
                 this.triggerToast('Silakan pilih ukuran (size) terlebih dahulu.', 'error');
                 return;
             }
             if (this.currentStock <= 0) {
                 this.triggerToast('Maaf, stok varian yang dipilih sedang habis.', 'error');
                 return;
             }

             const sizeVal = this.matchedVariant ? this.matchedVariant.size : (this.selectedSize || 'All Size');
             const colorVal = this.matchedVariant ? this.matchedVariant.color : (this.selectedColor || '-');

             let cartItems = JSON.parse(localStorage.getItem('cart_items')) || [];
             let existingItem = cartItems.find(
                 i => i.id === this.product.id && i.size === sizeVal && i.color === colorVal
             );

             const requestedQty = parseInt(this.qty) || 1;
             const currentInCart = existingItem ? Number(existingItem.qty) : 0;

             if (currentInCart + requestedQty > this.currentStock) {
                 this.triggerToast(`Stok tidak mencukupi. Maksimal tersedia: ${this.currentStock} pcs.`, 'error');
                 return;
             }

             if (existingItem) {
                 existingItem.qty += requestedQty;
             } else {
                 cartItems.push({
                     id: this.product.id,
                     name: this.product.name,
                     category: this.product.category,
                     image: this.product.image,
                     size: sizeVal,
                     color: colorVal,
                     price: Number(this.product.price),
                     qty: requestedQty
                 });
             }

             localStorage.setItem('cart_items', JSON.stringify(cartItems));
             window.dispatchEvent(new CustomEvent('cart-updated', { detail: cartItems }));
             this.triggerToast('Produk berhasil ditambahkan ke keranjang!', 'success');
         }
     }">

    <!-- Floating Toast Notification -->
    <div class="fixed top-6 right-6 z-50 max-w-sm w-full px-4 pointer-events-none"
         x-show="toast.show"
         x-transition:enter="transition ease-out duration-300 transform"
         x-transition:enter-start="translate-y-[-20px] opacity-0"
         x-transition:enter-end="translate-y-0 opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0 translate-y-[-20px]"
         style="display: none;">
        <div class="flex items-center gap-3 p-4 rounded-2xl shadow-2xl border text-xs font-bold pointer-events-auto"
             :class="toast.type === 'success' ? 'bg-gray-900 text-white border-gray-800' : 'bg-red-600 text-white border-red-500'">
            <span class="flex h-2 w-2 rounded-full" :class="toast.type === 'success' ? 'bg-green-400 animate-pulse' : 'bg-white animate-ping'"></span>
            <p class="flex-1" x-text="toast.message"></p>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Breadcrumb / Navigasi Kecil -->
        <nav class="text-xs text-gray-500 mb-6 font-medium">
            <a href="{{ url('/') }}" class="hover:underline">Beranda</a> / 
            <a href="{{ url('/') }}#katalog" class="hover:underline">Katalog</a> / 
            <span class="text-gray-900 font-bold">{{ $produk->name }}</span>
        </nav>

        <!-- Grid Utama 2 Kolom (Kiri: Foto, Kanan: Detail & Aksi) -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12">
            
            <!-- ================= KOLOM KIRI: GALERI FOTO DINAMIS ================= -->
            <div class="lg:col-span-7 grid grid-cols-1 {{ ($produk->images && $produk->images->count() > 0) ? 'sm:grid-cols-2' : '' }} gap-4">
                <!-- Foto Utama (Cover) -->
                <div class="rounded-3xl overflow-hidden bg-gray-100 border border-gray-200 aspect-[3/4] relative group shadow-sm">
                    <img src="{{ $coverImg }}" alt="{{ $produk->name }}" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                </div>

                <!-- Foto Galeri Tambahan dari Database -->
                @if($produk->images && $produk->images->count() > 0)
                    @foreach($produk->images as $img)
                        @php
                            $galleryImg = filter_var($img->image_path, FILTER_VALIDATE_URL) 
                                ? $img->image_path 
                                : asset('storage/' . $img->image_path);
                        @endphp
                        <div class="rounded-3xl overflow-hidden bg-gray-100 border border-gray-200 aspect-[3/4] relative group shadow-sm">
                            <img src="{{ $galleryImg }}" alt="Galeri Produk" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                        </div>
                    @endforeach
                @endif
            </div>

            <!-- ================= KOLOM KANAN: INFORMASI PRODUK & AKSI ================= -->
            <div class="lg:col-span-5 space-y-6">
                
                <!-- Box Harga / Diskon -->
                <div class="bg-gradient-to-r from-rose-500 to-red-600 text-white p-5 rounded-3xl shadow-lg flex items-center justify-between">
                    <div>
                        <span class="text-[10px] font-black uppercase tracking-widest bg-black/20 px-2.5 py-1 rounded-full">Sport Mania Original 🔥</span>
                        <div class="text-2xl sm:text-3xl font-black mt-1">
                            Rp {{ number_format($produk->discount_price ?? $produk->price, 0, ',', '.') }}
                        </div>
                        @if($produk->discount_price && $produk->discount_price < $produk->price)
                            <div class="text-xs text-rose-100 line-through mt-0.5">Rp {{ number_format($produk->price, 0, ',', '.') }}</div>
                        @endif
                    </div>
                    <div class="text-right">
                        <span class="inline-block bg-black/25 px-3 py-1.5 rounded-xl text-[11px] font-black uppercase tracking-wider"
                              x-text="currentStock > 0 ? ('Stok: ' + currentStock + ' pcs') : 'Stok Habis'"></span>
                    </div>
                </div>

                <!-- Judul, Kategori & Rating -->
                <div class="space-y-2 border-b border-gray-200 pb-6">
                    <h1 class="text-xs font-black text-red-600 uppercase tracking-widest">{{ $produk->category->name ?? 'Sport Apparel' }}</h1>
                    <h2 class="text-2xl sm:text-3xl font-black text-gray-900 leading-tight">
                        {{ $produk->name }}
                    </h2>
                    
                    <div class="flex items-center gap-2 pt-1 text-xs font-bold">
                        @if($reviewCount > 0)
                            <span class="text-amber-500">⭐ {{ $avgRating }}</span>
                            <span class="text-gray-400 font-normal">| ({{ $reviewCount }} Ulasan Pembeli)</span>
                        @else
                            <span class="text-gray-400 font-medium">Belum ada ulasan pembeli</span>
                        @endif
                    </div>
                </div>

                <!-- Deskripsi Produk -->
                @if($produk->description)
                <div class="space-y-2 text-xs text-gray-600 leading-relaxed font-medium">
                    <p class="font-bold text-gray-900 uppercase tracking-wider">Deskripsi Produk:</p>
                    <p class="whitespace-pre-line">{{ $produk->description }}</p>
                </div>
                @endif

                <!-- Pilihan Varian Warna (Dinamis dari Database) -->
                @if($colors->count() > 0)
                <div class="space-y-3">
                    <label class="text-xs font-black uppercase tracking-wider text-gray-700">
                        Variasi Warna: <span class="font-bold text-gray-900" x-text="selectedColor || 'Pilih Warna'"></span>
                    </label>
                    <div class="flex flex-wrap gap-2">
                        @foreach($colors as $color)
                            <button type="button" 
                                    @click="selectColor(@js($color))"
                                    :disabled="!isColorAvailable(@js($color))"
                                    :class="selectedColor === @js($color)
                                        ? 'border-red-600 bg-red-50 text-red-600 ring-2 ring-red-600'
                                        : (!isColorAvailable(@js($color))
                                            ? 'border-gray-100 bg-gray-50 text-gray-300 cursor-not-allowed line-through'
                                            : 'border-gray-200 bg-white text-gray-800 hover:border-black')"
                                    class="px-4 py-2.5 rounded-xl border text-xs font-bold transition shadow-sm">
                                {{ $color }}
                            </button>
                        @endforeach
                    </div>
                </div>
                @endif

                <!-- Pilihan Ukuran / Size (Dinamis dari Database) -->
                @if($sizes->count() > 0)
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <label class="text-xs font-black uppercase tracking-wider text-gray-700">
                            Pilih Ukuran: <span class="font-bold text-gray-900" x-text="selectedSize || 'Pilih Size'"></span>
                        </label>
                        <button type="button" @click="sizeGuideOpen = true" class="text-xs text-red-600 font-bold hover:underline">📏 Panduan Ukuran</button>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @foreach($sizes as $size)
                            <button type="button" 
                                    @click="selectSize(@js($size))"
                                    :disabled="!isSizeAvailable(@js($size))"
                                    :class="selectedSize === @js($size)
                                        ? 'bg-black text-white border-black'
                                        : (!isSizeAvailable(@js($size))
                                            ? 'bg-gray-50 text-gray-300 border-gray-100 cursor-not-allowed line-through'
                                            : 'bg-white text-gray-800 border-gray-300 hover:border-black')"
                                    class="px-5 py-3 rounded-xl border text-xs font-bold transition shadow-sm">
                                {{ $size }}
                            </button>
                        @endforeach
                    </div>
                </div>
                @endif

                <!-- Jumlah Pembelian -->
                <div class="flex items-center justify-between bg-gray-50 p-3.5 rounded-2xl border border-gray-200">
                    <div>
                        <span class="text-xs font-bold text-gray-800 block">Jumlah Pembelian</span>
                        <span class="text-[11px] text-gray-400 font-medium" x-text="isSelectionComplete ? ('Maksimal tersedia: ' + currentStock + ' pcs') : 'Pilih varian untuk melihat stok pasti'"></span>
                    </div>
                    <div class="flex items-center gap-3">
                        <button type="button" @click="if(qty > 1) qty--" class="w-8 h-8 rounded-xl bg-white border border-gray-300 font-black text-sm hover:bg-gray-100 transition">-</button>
                        <span class="font-black text-sm w-6 text-center" x-text="qty"></span>
                        <button type="button" @click="if(qty < Math.max(1, currentStock)) qty++; else triggerToast('Jumlah mencapai batas stok tersedia.', 'error');" class="w-8 h-8 rounded-xl bg-white border border-gray-300 font-black text-sm hover:bg-gray-100 transition">+</button>
                    </div>
                </div>

                <!-- Tombol Aksi (WhatsApp Checkout & Keranjang) -->
                <div class="space-y-3 pt-2">
                    <a :href="whatsappUrl" target="_blank" 
                       class="w-full bg-red-600 hover:bg-red-700 text-white font-black text-xs uppercase tracking-[0.2em] py-4 rounded-2xl shadow-xl flex items-center justify-center gap-2 transition duration-300">
                        🛒 BELI SEKARANG VIA WHATSAPP
                    </a>
                    
                    <button type="button" 
                            @click="addToCart()"
                            :disabled="isSelectionComplete && currentStock <= 0"
                            class="w-full bg-black hover:bg-gray-800 disabled:bg-gray-300 disabled:cursor-not-allowed text-white font-black text-xs uppercase tracking-[0.2em] py-4 rounded-2xl shadow-md transition duration-300 cursor-pointer">
                        + Tambah ke Keranjang
                    </button>
                </div>

                <!-- Informasi Tambahan -->
                <div class="bg-gray-50 border border-gray-200 p-4 rounded-2xl space-y-2 text-xs text-gray-600 font-medium">
                    <p class="flex items-center gap-2">🛡️ <strong class="text-gray-900">100% Original:</strong> Dijamin produk resmi berkualitas tinggi.</p>
                    <p class="flex items-center gap-2">🚚 <strong class="text-gray-900">Pengiriman Cepat:</strong> Siap kirim dari Malang ke seluruh Indonesia.</p>
                </div>
                
            </div>

        </div>

        <!-- ========================================== -->
        <!-- SEKSI ULASAN PEMBELI (REAL DARI DATABASE) -->
        <!-- ========================================== -->
        <div class="mt-20 border-t border-gray-200 pt-12">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
                <div>
                    <h3 class="text-xl font-black uppercase tracking-tight text-gray-900">Ulasan & Penilaian Produk</h3>
                    <p class="text-xs text-gray-500 font-medium mt-1">Pendapat asli dari pelanggan yang telah membeli produk ini.</p>
                </div>
                <div class="bg-gray-50 border border-gray-200 px-4 py-2 rounded-2xl text-xs font-bold text-gray-800 self-start sm:self-auto">
                    @if($reviewCount > 0)
                        ⭐ {{ $avgRating }} / 5.0 ({{ $reviewCount }} Ulasan)
                    @else
                        Belum ada penilaian
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @forelse($produk->reviews as $review)
                    <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm space-y-3">
                        <div class="flex justify-between items-start">
                            <div>
                                <h4 class="font-bold text-xs text-gray-900">{{ $review->user->name ?? 'Pelanggan Setia' }}</h4>
                                <span class="text-[10px] text-gray-400 font-medium">{{ $review->created_at->format('d M Y • H:i') }}</span>
                            </div>
                            <div class="bg-amber-50 text-amber-600 border border-amber-200 px-2.5 py-1 rounded-xl text-xs font-black">
                                ★ {{ $review->rating }} / 5
                            </div>
                        </div>
                        <p class="text-xs text-gray-600 font-medium leading-relaxed bg-gray-50 p-3.5 rounded-2xl border border-gray-100">
                            "{{ $review->comment }}"
                        </p>
                    </div>
                @empty
                    <div class="col-span-2 bg-gray-50 rounded-3xl p-10 text-center border border-dashed border-gray-200">
                        <p class="text-xs font-bold text-gray-400 uppercase tracking-widest">Belum ada ulasan untuk produk ini.</p>
                        <p class="text-xs text-gray-500 mt-1">Jadilah yang pertama memberikan ulasan setelah menyelesaikan pesananmu!</p>
                    </div>
                @endforelse
            </div>
        </div>

    </div>

    <!-- Modal Panduan Ukuran -->
    <div x-show="sizeGuideOpen"
         style="display: none;"
         @keydown.escape.window="sizeGuideOpen = false"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm">
        <div @click.outside="sizeGuideOpen = false" class="bg-white rounded-3xl max-w-md w-full overflow-hidden shadow-2xl border border-gray-100">
            <div class="bg-gray-900 px-6 py-4 flex justify-between items-center text-white">
                <span class="text-xs font-black uppercase tracking-widest">📏 Panduan Ukuran Apparel</span>
                <button type="button" @click="sizeGuideOpen = false" class="text-gray-400 hover:text-white text-sm font-bold">✕</button>
            </div>
            <div class="p-6">
                <table class="w-full text-xs text-left border-collapse">
                    <thead>
                        <tr class="border-b border-gray-200 text-gray-400 uppercase text-[10px]">
                            <th class="py-2">Size</th>
                            <th class="py-2">Lebar Dada (cm)</th>
                            <th class="py-2">Panjang Badan (cm)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 font-semibold text-gray-700">
                        <tr><td class="py-2.5 font-black text-gray-900">S</td><td>48 cm</td><td>68 cm</td></tr>
                        <tr><td class="py-2.5 font-black text-gray-900">M</td><td>50 cm</td><td>70 cm</td></tr>
                        <tr><td class="py-2.5 font-black text-gray-900">L</td><td>52 cm</td><td>72 cm</td></tr>
                        <tr><td class="py-2.5 font-black text-gray-900">XL</td><td>54 cm</td><td>74 cm</td></tr>
                        <tr><td class="py-2.5 font-black text-gray-900">XXL</td><td>56 cm</td><td>76 cm</td></tr>
                    </tbody>
                </table>
                <p class="text-[11px] text-gray-400 mt-4">*Toleransi ukuran jahitan ±1–2 cm.</p>
            </div>
        </div>
    </div>
</div>
@endsection