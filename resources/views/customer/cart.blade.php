<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keranjang Belanja | Sport Mania Distro</title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
        'resources/css/pages/customer-cart.css',
    ])
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
</head>
<body class="customer-cart-page bg-[#FAFAFA] text-gray-900 antialiased selection:bg-red-500 selection:text-white flex flex-col min-h-screen"
      x-data="customerCart()"
      @cart-updated.window="syncCart()"
      @storage.window="syncCart()"
      @pageshow.window="syncCart()">

    <!-- Top Announcement Bar -->
    <div class="bg-gray-900 text-gray-300 text-[11px] py-2.5 px-4 font-medium tracking-wide border-b border-gray-800">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div class="flex items-center space-x-4">
                <span class="inline-flex items-center gap-2 text-white">
                    <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse"></span>
                    PROMO 2026: Gratis Ongkir Seluruh Jawa min. Belanja Rp 250rb!
                </span>
            </div>
            <div class="hidden sm:flex items-center space-x-6 text-gray-400">
                <a href="https://wa.me/6282234548092" target="_blank" class="hover:text-white transition flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-green-500" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                    Bantuan CS (WhatsApp)
                </a>
            </div>
        </div>
    </div>

    <!-- Main Navbar -->
    <nav class="bg-white/95 backdrop-blur-md border-b border-gray-100 sticky top-0 z-40 transition-all duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <div class="flex-shrink-0 flex items-center gap-2.5 sm:gap-3">
                    <button type="button"
                            onclick="if (window.history.length > 1 && document.referrer.includes(window.location.host)) { window.history.back(); } else { window.location.href = '{{ route('home') }}#katalog'; }"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-gray-100 hover:bg-gray-900 text-gray-800 hover:text-white text-xs font-black transition active:scale-95"
                            title="Kembali">
                        <span>←</span>
                        <span>Kembali</span>
                    </button>

                    <a href="{{ route('home') }}" class="flex items-center gap-2.5 hover:opacity-80 transition group">
                        <div class="p-1 rounded-lg bg-gray-900 border border-gray-800 shadow-inner">
                            <img src="{{ asset('image/LOGO.jpeg') }}" alt="Logo" class="h-8 w-auto object-contain rounded">
                        </div>
                        <span class="text-xl lg:text-2xl font-black tracking-tighter uppercase italic">
                            <span class="text-red-600 group-hover:text-red-500 transition">SPORT</span><span class="text-gray-900">MANIA</span>
                        </span>
                    </a>
                </div>

                <div class="hidden lg:flex items-center space-x-6">
                    @if (Route::has('login'))
                        @auth
                            <div x-data="{ open: false }" class="relative border-l border-gray-200 pl-3">
                                <button @click="open = !open" @click.outside="open = false" class="flex items-center gap-1.5 text-gray-800 font-bold focus:outline-none">
                                    <div class="w-7 h-7 rounded-full bg-red-100 text-red-600 flex items-center justify-center font-black text-xs">
                                        {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                                    </div>
                                    <span class="text-xs truncate max-w-[90px]">{{ Auth::user()->name }}</span>
                                </button>
                                <div x-show="open" x-cloak class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-xl py-2 z-50 border border-gray-100">
                                    <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">Profil Saya</a>
                                    @if(Route::has('customer.orders'))
                                        <a href="{{ route('customer.orders') }}" class="block px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">Pesanan Saya</a>
                                    @endif
                                    @if(Auth::user()->role === 'admin')
                                        <a href="{{ route('admin.dashboard') }}" class="block px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">Panel Admin</a>
                                    @elseif(Auth::user()->role === 'owner' && Route::has('owner.dashboard'))
                                        <a href="{{ route('owner.dashboard') }}" class="block px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">Owner Panel</a>
                                    @endif
                                    
                                    <form method="POST" action="{{ route('logout') }}" @submit="localStorage.removeItem('cart_items')">
                                        @csrf
                                        <button type="submit" class="block w-full text-left px-4 py-2 text-xs text-red-600 hover:bg-red-50 font-bold">Log Out</button>
                                    </form>
                                </div>
                            </div>
                        @else
                            <div class="flex items-center space-x-2 border-l border-gray-200 pl-3">
                                <a href="{{ route('login') }}" class="text-gray-700 hover:text-red-600 font-bold text-xs uppercase px-2 py-1">Log in</a>
                                <a href="{{ route('register') }}" class="bg-gray-900 hover:bg-red-600 text-white px-4 py-2 rounded-lg font-bold text-xs uppercase tracking-wider shadow-sm transition">Daftar</a>
                            </div>
                        @endauth
                    @endif
                </div>

                <div class="flex items-center gap-3 lg:hidden">
                    <button @click="mobileMenuOpen = !mobileMenuOpen" class="text-gray-700 p-2 focus:outline-none">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    </button>
                </div>
            </div>

            <div x-show="mobileMenuOpen" x-cloak class="lg:hidden pb-5 border-t border-gray-100 pt-4 space-y-4">
                <div class="px-2 space-y-2">
                    @if (Route::has('login'))
                        @auth
                            <div class="flex items-center gap-3 mb-4 px-2">
                                <div class="w-10 h-10 rounded-full bg-red-100 text-red-600 flex items-center justify-center font-black text-sm">
                                    {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-sm font-bold text-gray-900">{{ Auth::user()->name }}</span>
                                    <span class="text-[10px] text-gray-500 uppercase font-semibold">{{ Auth::user()->role }}</span>
                                </div>
                            </div>
                            @if(Auth::user()->role === 'admin')
                                <a href="{{ route('admin.dashboard') }}" class="block w-full text-center bg-gray-900 text-white font-bold text-xs uppercase tracking-widest py-3 rounded-lg mb-2">Panel Admin</a>
                            @elseif(Auth::user()->role === 'owner' && Route::has('owner.dashboard'))
                                <a href="{{ route('owner.dashboard') }}" class="block w-full text-center bg-gray-900 text-white font-bold text-xs uppercase tracking-widest py-3 rounded-lg mb-2">Owner Panel</a>
                            @endif
                            <a href="{{ route('profile.edit') }}" class="block w-full text-center bg-gray-100 text-gray-700 font-bold text-xs uppercase tracking-widest py-3 rounded-lg mb-2">Profil Saya</a>
                            @if(Route::has('customer.orders'))
                                <a href="{{ route('customer.orders') }}" class="block w-full text-center bg-gray-100 text-gray-700 font-bold text-xs uppercase tracking-widest py-3 rounded-lg mb-2">Pesanan Saya</a>
                            @endif
                            
                            <form method="POST" action="{{ route('logout') }}" @submit="localStorage.removeItem('cart_items')">
                                @csrf
                                <button type="submit" class="block w-full text-center bg-red-50 text-red-600 font-bold text-xs uppercase tracking-widest py-3 rounded-lg">Log Out</button>
                            </form>
                        @else
                            <div class="flex gap-2">
                                <a href="{{ route('login') }}" class="flex-1 text-center bg-gray-100 text-gray-700 font-bold text-xs uppercase tracking-widest py-3 rounded-lg">Log in</a>
                                <a href="{{ route('register') }}" class="flex-1 text-center bg-gray-900 text-white font-bold text-xs uppercase tracking-widest py-3 rounded-lg">Daftar</a>
                            </div>
                        @endauth
                    @endif
                </div>
            </div>
        </div>
    </nav>

    <!-- Konten Keranjang Utama -->
    <main class="max-w-7xl mx-auto py-8 sm:py-12 px-4 sm:px-6 lg:px-8 flex-grow w-full">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-6 sm:mb-8">
            <div class="flex items-center gap-3">
                <h1 class="text-2xl sm:text-3xl font-black text-gray-900 uppercase tracking-tight">Keranjang <span class="text-red-600">Belanja</span></h1>
                <span class="bg-gray-900 text-white text-[10px] font-bold px-2.5 py-1 rounded-md" x-text="totalItems + ' Item'"></span>
            </div>

            <button type="button"
                    x-show="cartItems.length > 0"
                    x-cloak
                    @click="clearCart()"
                    class="text-xs font-bold text-gray-400 hover:text-red-600 transition">
                Kosongkan Keranjang
            </button>
        </div>

        <!-- Jika keranjang kosong -->
        <div x-show="cartItems.length === 0" x-cloak class="bg-white rounded-3xl p-10 sm:p-16 text-center border border-gray-100 shadow-sm flex-1 flex flex-col justify-center items-center">
            <div class="w-16 h-16 sm:w-20 sm:h-20 bg-red-50 text-red-600 rounded-full flex items-center justify-center mb-4 sm:mb-6 text-3xl font-bold">🛒</div>
            <h3 class="text-lg sm:text-xl font-black text-gray-900 mb-2">Keranjang Kamu Masih Kosong</h3>
            <p class="text-xs sm:text-sm text-gray-500 mb-8 font-medium max-w-md">Yuk, kembali ke katalog dan pilih produk perlengkapan olahraga terbaikmu sekarang!</p>
            <div class="flex flex-wrap justify-center gap-3">
                <a href="{{ route('home') }}" class="inline-block bg-gray-900 hover:bg-red-600 text-white font-bold text-xs uppercase tracking-widest px-8 py-4 rounded-xl transition shadow-md">Mulai Belanja</a>
                @guest
                    <a href="{{ route('login') }}" class="inline-block bg-white hover:bg-gray-50 text-gray-800 border border-gray-200 font-bold text-xs uppercase tracking-widest px-8 py-4 rounded-xl transition">Login Akun</a>
                @endguest
            </div>
        </div>

        <!-- Jika ada isinya -->
        <div x-show="cartItems.length > 0" x-cloak class="grid grid-cols-1 lg:grid-cols-3 gap-6 sm:gap-8 pb-10">
            
            <!-- Daftar Produk di Keranjang (Kiri) -->
            <div class="lg:col-span-2 space-y-4">
                <template x-for="(item, index) in cartItems" :key="item.id + '-' + (item.variant_id || '') + '-' + (item.size || '') + '-' + (item.color || '') + '-' + index">
                    <div class="bg-white rounded-2xl sm:rounded-3xl p-4 sm:p-6 border border-gray-100 shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                        <div class="flex items-center gap-4 w-full sm:w-auto">
                            <a :href="'/produk/' + item.id" class="w-20 h-20 sm:w-24 sm:h-24 rounded-xl sm:rounded-2xl bg-gray-50 flex items-center justify-center shrink-0 font-black text-gray-300 border border-gray-100 overflow-hidden">
                                <template x-if="item.image">
                                    <img :src="item.image" :alt="item.name" class="w-full h-full object-cover">
                                </template>
                                <template x-if="!item.image">
                                    <span>SM</span>
                                </template>
                            </a>
                            <div>
                                <span class="text-[9px] sm:text-[10px] font-bold uppercase tracking-widest text-red-500" x-text="item.category || 'Sport Apparel'"></span>
                                <a :href="'/produk/' + item.id" class="block font-bold text-gray-900 hover:text-red-600 transition text-sm sm:text-base leading-tight mt-0.5" x-text="item.name"></a>
                                <div class="flex flex-wrap items-center gap-2 mt-1.5 mb-2">
                                    <span class="inline-block bg-gray-100 px-2 py-1 rounded text-[10px] sm:text-xs text-gray-600 font-semibold">
                                        Varian: <span class="text-gray-900" x-text="(item.size || 'All Size') + ' • ' + (item.color || '-')"></span>
                                    </span>
                                    <template x-if="getItemMaxStock(item) !== null">
                                        <span class="text-[10px] font-bold px-2 py-1 rounded"
                                              :class="getItemMaxStock(item) <= 0 ? 'bg-red-50 text-red-600' : 'bg-gray-50 text-gray-500'"
                                              x-text="getItemMaxStock(item) <= 0 ? 'Stok Habis' : 'Sisa stok: ' + getItemMaxStock(item)"></span>
                                    </template>
                                </div>
                                <p class="font-black text-red-600 text-sm sm:text-base" x-text="'Rp ' + Number(item.price).toLocaleString('id-ID')"></p>
                            </div>
                        </div>

                        <!-- Kontrol Qty & Hapus -->
                        <div class="flex items-center justify-between sm:justify-end w-full sm:w-auto gap-4 sm:gap-6 pt-3 sm:pt-0 border-t sm:border-0 border-gray-100 mt-2 sm:mt-0">
                            <div class="flex items-center border border-gray-200 rounded-lg p-0.5 bg-gray-50">
                                <button type="button" @click="decreaseQty(index)" class="w-8 h-8 flex items-center justify-center font-bold text-gray-500 hover:text-gray-900 hover:bg-white rounded-md transition">-</button>
                                <span class="w-8 text-center text-xs font-black text-gray-900" x-text="item.qty"></span>
                                <button type="button" @click="increaseQty(index)" class="w-8 h-8 flex items-center justify-center font-bold text-gray-500 hover:text-gray-900 hover:bg-white rounded-md transition">+</button>
                            </div>
                            <button type="button" @click="removeItem(index)" class="text-gray-400 hover:text-red-600 bg-gray-50 hover:bg-red-50 rounded-lg p-2.5 transition" title="Hapus produk">
                                <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Ringkasan Pesanan Box (Kanan) -->
            <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-sm h-fit space-y-6 sticky top-28">
                <h3 class="text-base sm:text-lg font-black text-gray-900 uppercase tracking-tight border-b border-gray-100 pb-4">Ringkasan Belanja</h3>
                
                <div class="space-y-3 text-xs sm:text-sm font-medium text-gray-600 border-t border-gray-100 pt-4">
                    <div class="flex justify-between">
                        <span>Total Harga Barang (<span x-text="totalItems"></span> pcs)</span>
                        <span class="font-bold text-gray-900" x-text="'Rp ' + Number(subtotal).toLocaleString('id-ID')"></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span>Estimasi Ongkir (Jawa)</span>
                        <span class="font-bold" :class="shippingCost === 0 ? 'text-green-600 bg-green-50 px-2 py-0.5 rounded' : 'text-gray-900'" x-text="shippingCost === 0 ? 'GRATIS' : 'Rp ' + Number(shippingCost).toLocaleString('id-ID')"></span>
                    </div>
                    
                    <!-- Indikator Kurang Belanja -->
                    <div x-show="subtotal > 0 && subtotal < 250000" class="bg-blue-50 border border-blue-100 rounded-lg p-3 mt-2">
                        <p class="text-[10px] text-blue-700 italic font-semibold">💡 Tambah belanja Rp <span x-text="Number(250000 - subtotal).toLocaleString('id-ID')"></span> lagi untuk mendapatkan Gratis Ongkir!</p>
                    </div>
                </div>

                <div class="border-t border-gray-100 pt-4 flex justify-between items-end">
                    <span class="font-bold text-sm sm:text-base text-gray-900 uppercase">Total Tagihan</span>
                    <span class="font-black text-xl sm:text-2xl text-red-600" x-text="'Rp ' + Number(total).toLocaleString('id-ID')"></span>
                </div>

                @auth
                    <a href="{{ route('customer.checkout') }}" 
                       class="w-full bg-green-600 hover:bg-green-700 text-white py-4 rounded-xl font-bold text-xs uppercase tracking-widest transition shadow-lg shadow-green-600/20 flex items-center justify-center gap-2 text-center mt-4">
                        Lanjut ke Checkout →
                    </a>
                    <p class="text-[10px] text-center text-gray-400 font-medium mt-2">Lengkapi alamat pengiriman dan konfirmasi pesanan di halaman selanjutnya.</p>
                @else
                    <a href="{{ route('login') }}" 
                       class="w-full bg-red-600 hover:bg-red-700 text-white py-4 rounded-xl font-bold text-xs uppercase tracking-widest transition shadow-lg shadow-red-600/20 flex items-center justify-center gap-2 text-center mt-4">
                        Login untuk Checkout →
                    </a>
                    <p class="text-[10px] text-center text-gray-400 font-medium mt-2">Belum punya akun? <a href="{{ route('register') }}" class="text-red-600 font-bold hover:underline">Daftar sekarang</a> untuk melanjutkan pesanan.</p>
                @endauth
            </div>

        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 py-10 mt-auto">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <img src="{{ asset('image/LOGO.jpeg') }}" alt="Logo" class="h-10 w-auto mx-auto mb-4 object-contain grayscale opacity-60">
            <p class="text-gray-500 text-[10px] sm:text-xs font-medium">© 2026 Eka Bagus Karisma & Aditya Panji Pratama. Proyek Tugas Akhir S1 Pendidikan Teknik Informatika.</p>
        </div>
    </footer>

    <!-- Toast Notification -->
    <div x-data="cartToast()"
         @notify.window="openToast($event.detail)"
         @show-toast.window="openToast($event.detail)"
         class="fixed top-24 right-4 sm:right-8 z-[100] flex flex-col gap-3 pointer-events-none"
         x-cloak>
        
        <div x-show="show"
             x-transition:enter="transition ease-out duration-300 transform"
             x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:translate-x-10 scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:translate-x-0 scale-100"
             x-transition:leave="transition ease-in duration-200 transform"
             x-transition:leave-start="opacity-100 sm:translate-x-0 scale-100"
             x-transition:leave-end="opacity-0 sm:translate-x-10 scale-95"
             class="bg-gray-900/95 backdrop-blur-md border border-gray-800 shadow-[0_20px_40px_-15px_rgba(0,0,0,0.6)] rounded-2xl flex items-center gap-4 px-5 py-4 max-w-sm w-full relative overflow-hidden pointer-events-auto">
            
            <div class="absolute left-0 top-0 bottom-0 w-1.5 transition-colors"
                 :class="{
                     'bg-emerald-500': type === 'success',
                     'bg-amber-500': type === 'warning',
                     'bg-red-600': type === 'error'
                 }"></div>
            
            <div class="rounded-full p-2 shrink-0 transition-colors"
                 :class="{
                     'bg-emerald-500/20 text-emerald-400': type === 'success',
                     'bg-amber-500/20 text-amber-400': type === 'warning',
                     'bg-red-500/20 text-red-500': type === 'error'
                 }">
                <svg x-show="type === 'success'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                <svg x-show="type === 'warning'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                <svg x-show="type === 'error'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
            </div>
            
            <div class="flex-1 min-w-0">
                <h4 class="text-white text-xs font-black uppercase tracking-wider" x-text="title"></h4>
                <p class="text-gray-300 text-xs font-medium mt-0.5 leading-relaxed" x-text="message"></p>
            </div>

            <button @click="show = false" class="text-gray-500 hover:text-white transition shrink-0 ml-1 p-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>

            <div :key="barKey" class="absolute bottom-0 left-0 h-0.5 animate-toast-bar"
                 :class="{
                     'bg-emerald-500': type === 'success',
                     'bg-amber-500': type === 'warning',
                     'bg-red-600': type === 'error'
                 }"></div>
        </div>
    </div>
</body>
</html>