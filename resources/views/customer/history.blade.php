<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan Saya | Sport Mania Distro</title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
        'resources/css/pages/customer-history.css',
    ])
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
</head>
<body class="customer-history-page bg-[#FAFAFA] text-gray-900 antialiased selection:bg-red-500 selection:text-white flex flex-col min-h-screen"
      x-data="customerHistoryPage()"
      @cart-updated.window="syncCart()"
      @storage.window="syncCart()"
      @pageshow.window="syncCart()"
      @keydown.escape.window="showReviewModal = false">

    <!-- ========================================== -->
    <!-- ELEGAN TOAST NOTIFICATION (Global Handler) -->
    <!-- ========================================== -->
    <div x-data="historyToast()" 
         @show-toast.window="open($event.detail)"
         x-show="show"
         x-transition:enter="translate-y-[-20px] opacity-0"
         x-transition:enter-end="translate-y-0 opacity-100"
         x-transition:leave="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed top-6 right-6 z-50 max-w-sm w-full px-4" x-cloak>
        <div class="flex items-center gap-3 p-4 bg-gray-900 text-white rounded-2xl shadow-2xl border border-gray-800 text-xs font-bold">
            <span class="flex h-2 w-2 rounded-full bg-green-500 animate-pulse"></span>
            <p class="flex-1" x-text="message"></p>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- NAVBAR -->
    <!-- ========================================== -->
    <nav class="bg-white/95 backdrop-blur-md border-b border-gray-100 sticky top-0 z-40 transition-all duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <!-- Logo & Brand Name -->
                <div class="flex-shrink-0 flex items-center">
                    <a href="/" class="flex items-center gap-3 hover:opacity-80 transition group">
                        <div class="p-1 rounded-lg bg-gray-900 border border-gray-800 shadow-inner">
                            <img src="{{ asset('image/LOGO.jpeg') }}" alt="Logo" class="h-8 w-auto object-contain rounded">
                        </div>
                        <span class="text-xl lg:text-2xl font-black tracking-tighter uppercase italic">
                            <span class="text-red-600 group-hover:text-red-500 transition">SPORT</span><span class="text-gray-900">MANIA</span>
                        </span>
                    </a>
                </div>

                <!-- Bagian Kanan: Cart & Auth (Desktop) -->
                <div class="hidden lg:flex items-center space-x-4">
                    <a href="/" class="text-xs font-bold uppercase tracking-wider text-gray-700 hover:text-red-600 transition px-3 py-2 bg-gray-100 rounded-lg mr-2">
                        ← Kembali ke Toko
                    </a>
                    
                    @auth
                        @if(Auth::user()->role === 'admin')
                            <a href="{{ route('admin.dashboard') }}" class="bg-gray-900 hover:bg-red-600 transition text-white px-3.5 py-1.5 rounded-lg font-bold text-xs uppercase">Panel Admin</a>
                        @else
                            <a href="/customer/cart" class="relative text-gray-700 hover:text-red-600 p-1.5 rounded-full hover:bg-gray-100" title="Keranjang Belanja">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                                <span x-show="cartCount > 0" x-cloak class="absolute top-0 right-0 bg-red-600 text-white text-[9px] font-black px-1 py-0.5 rounded-full" x-text="cartCount"></span>
                            </a>
                        @endif

                        <!-- Dropdown Profil -->
                        <div x-data="{ open: false }" class="relative border-l border-gray-200 pl-3">
                            <button @click="open = !open" @click.outside="open = false" class="flex items-center gap-1.5 text-gray-800 font-bold focus:outline-none">
                                <div class="w-7 h-7 rounded-full bg-red-100 text-red-600 flex items-center justify-center font-black text-xs">
                                    {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                                </div>
                                <span class="text-xs truncate max-w-[90px]">{{ Auth::user()->name }}</span>
                            </button>
                            <div x-show="open" x-cloak class="absolute right-0 mt-2 w-48 bg-white rounded-2xl shadow-xl py-2 z-50 border border-gray-100">
                                <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">Profil Saya</a>
                                <a href="{{ route('customer.orders') }}" class="block px-4 py-2 text-xs font-semibold text-red-600 bg-red-50">Pesanan Saya</a>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="block w-full text-left px-4 py-2 text-xs text-gray-700 hover:bg-red-50 hover:text-red-600 font-bold">Log Out</button>
                                </form>
                            </div>
                        </div>
                    @endauth
                </div>

                <!-- Bagian Mobile: Keranjang & Hamburger -->
                <div class="flex items-center gap-3 lg:hidden">
                    <a href="/customer/cart" class="relative text-gray-700 p-1.5 rounded-full">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                        <span x-show="cartCount > 0" x-cloak class="absolute top-0 right-0 bg-red-600 text-white text-[9px] font-black px-1.5 py-0.5 rounded-full" x-text="cartCount"></span>
                    </a>
                    <button @click="mobileMenuOpen = !mobileMenuOpen" class="text-gray-700 p-2">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    </button>
                </div>
            </div>

            <!-- Mobile Dropdown Menu -->
            <div x-show="mobileMenuOpen" x-cloak class="lg:hidden pb-5 border-t border-gray-100 pt-4 space-y-4">
                <div class="px-2 space-y-2">
                    <a href="/" class="block w-full text-center bg-gray-100 text-gray-700 font-bold text-xs uppercase tracking-widest py-3 rounded-xl mb-2">← Kembali ke Toko</a>
                    <a href="{{ route('profile.edit') }}" class="block w-full text-center bg-gray-100 text-gray-700 font-bold text-xs uppercase tracking-widest py-3 rounded-xl mb-2">Profil Saya</a>
                    <a href="{{ route('customer.orders') }}" class="block w-full text-center bg-red-50 text-red-600 font-bold text-xs uppercase tracking-widest py-3 rounded-xl mb-2">Pesanan Saya</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="block w-full text-center bg-gray-100 text-gray-700 font-bold text-xs uppercase tracking-widest py-3 rounded-xl">Log Out</button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <!-- ========================================== -->
    <!-- KONTEN UTAMA: RIWAYAT PESANAN -->
    <!-- ========================================== -->
    <main class="max-w-5xl mx-auto py-10 px-4 sm:px-6 flex-grow w-full">
        
        <div class="mb-8">
            <h1 class="text-2xl sm:text-3xl font-black text-gray-900 uppercase tracking-tight">Pesanan <span class="text-red-600">Saya</span></h1>
            <p class="text-xs sm:text-sm text-gray-500 font-medium mt-1">Lacak status pengiriman dan riwayat belanjamu di Sport Mania Distro.</p>
        </div>

        <!-- Filter Tab Status -->
        <div class="flex gap-4 border-b border-gray-200 mb-8 overflow-x-auto hide-scrollbar">
            <button @click="activeTab = 'semua'" :class="activeTab === 'semua' ? 'border-b-2 border-red-600 text-red-600 font-black' : 'text-gray-500 font-bold hover:text-gray-800'" class="pb-3 text-xs sm:text-sm uppercase tracking-wider transition px-2 whitespace-nowrap">Semua</button>
            <button @click="activeTab = 'diproses'" :class="activeTab === 'diproses' ? 'border-b-2 border-red-600 text-red-600 font-black' : 'text-gray-500 font-bold hover:text-gray-800'" class="pb-3 text-xs sm:text-sm uppercase tracking-wider transition px-2 whitespace-nowrap">Sedang Diproses</button>
            <button @click="activeTab = 'dikirim'" :class="activeTab === 'dikirim' ? 'border-b-2 border-red-600 text-red-600 font-black' : 'text-gray-500 font-bold hover:text-gray-800'" class="pb-3 text-xs sm:text-sm uppercase tracking-wider transition px-2 whitespace-nowrap">Dikirim</button>
            <button @click="activeTab = 'selesai'" :class="activeTab === 'selesai' ? 'border-b-2 border-red-600 text-red-600 font-black' : 'text-gray-500 font-bold hover:text-gray-800'" class="pb-3 text-xs sm:text-sm uppercase tracking-wider transition px-2 whitespace-nowrap">Selesai</button>
        </div>

        <!-- List Pesanan -->
        <div class="space-y-6">
            
            <!-- Item Pesanan 1: Status Dikirim -->
            <div x-show="activeTab === 'semua' || activeTab === 'dikirim'" class="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 transition-all hover:shadow-md">
                <div class="flex flex-wrap justify-between items-center border-b border-gray-100 pb-4 mb-4 gap-4">
                    <div>
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">13 Nov 2026 • INV/202611/SM/01</p>
                        <h3 class="text-sm font-black text-gray-900 mt-1">Sport Mania Official Store</h3>
                    </div>
                    <span class="bg-blue-50 text-blue-600 border border-blue-200 text-[10px] font-black uppercase tracking-widest px-3 py-1.5 rounded-xl flex items-center gap-1.5 shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path></svg>
                        Paket Dikirim
                    </span>
                </div>

                <div class="flex flex-col sm:flex-row gap-6">
                    <img src="https://images.unsplash.com/photo-1517838277536-f5f99be501cd?q=80&w=200" alt="Produk" class="w-20 h-24 object-cover rounded-2xl bg-gray-50 border border-gray-100">
                    <div class="flex-1">
                        <h4 class="text-base font-bold text-gray-900">Nike Pro Combat Dry-Fit</h4>
                        <p class="text-xs text-gray-500 font-medium mt-1">Varian: L • Hitam | 1 x Rp 250.000</p>
                        
                        <div class="mt-4 bg-gray-50 rounded-2xl p-3 border border-gray-200 flex items-center justify-between">
                            <div>
                                <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">No. Resi (JNT Express)</p>
                                <p class="text-xs font-black text-gray-900 uppercase">JP9876543210</p>
                            </div>
                            <a href="https://jet.co.id/track" target="_blank" class="text-xs font-bold text-red-600 hover:underline">Lacak →</a>
                        </div>
                    </div>
                    <div class="sm:text-right border-t sm:border-t-0 sm:border-l border-gray-100 pt-4 sm:pt-0 sm:pl-6 flex flex-col justify-center min-w-[120px]">
                        <p class="text-[10px] font-bold text-gray-500 uppercase">Total Belanja</p>
                        <p class="text-lg font-black text-red-600">Rp 250.000</p>
                    </div>
                </div>
            </div>

            <!-- Item Pesanan 2: Status Selesai -->
            <div x-show="activeTab === 'semua' || activeTab === 'selesai'" class="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 transition-all hover:shadow-md">
                <div class="flex flex-wrap justify-between items-center border-b border-gray-100 pb-4 mb-4 gap-4">
                    <div>
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">02 Nov 2026 • INV/202611/SM/00</p>
                        <h3 class="text-sm font-black text-gray-900 mt-1">Sport Mania Official Store</h3>
                    </div>
                    <span class="bg-emerald-50 text-emerald-600 border border-emerald-200 text-[10px] font-black uppercase tracking-widest px-3 py-1.5 rounded-xl flex items-center gap-1.5 shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        Pesanan Selesai
                    </span>
                </div>

                <div class="flex flex-col sm:flex-row gap-6">
                    <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?q=80&w=200" alt="Produk" class="w-20 h-24 object-cover rounded-2xl bg-gray-50 border border-gray-100">
                    <div class="flex-1">
                        <h4 class="text-base font-bold text-gray-900">Adidas Performance Elite</h4>
                        <p class="text-xs text-gray-500 font-medium mt-1">Varian: 42 • Putih | 1 x Rp 850.000</p>
                    </div>
                    <div class="sm:text-right flex flex-col justify-between items-end min-w-[150px] border-t sm:border-t-0 sm:border-l border-gray-100 pt-4 sm:pt-0 sm:pl-6">
                        <div class="w-full text-left sm:text-right">
                            <p class="text-[10px] font-bold text-gray-500 uppercase">Total Belanja</p>
                            <p class="text-lg font-black text-red-600">Rp 850.000</p>
                        </div>
                        <button @click="openReviewModal('Adidas Performance Elite')" 
                                class="mt-4 w-full bg-gray-900 hover:bg-gray-800 text-white text-[11px] font-bold uppercase tracking-wider py-2.5 px-4 rounded-xl transition shadow-sm cursor-pointer">
                            Beri Ulasan
                        </button>
                    </div>
                </div>
            </div>

        </div>

        <!-- ========================================== -->
        <!-- MODAL ALPINE.JS UNTUK TAMBAH ULASAN (Aesthetic) -->
        <!-- ========================================== -->
        <div x-show="showReviewModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                
                <!-- Backdrop dengan Blur Halus -->
                <div x-show="showReviewModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity" @click="showReviewModal = false" aria-hidden="true"></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">​</span>

                <!-- Card Modal -->
                <div x-show="showReviewModal" x-transition:enter="ease-out duration-300 transform" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200 transform" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
                     class="inline-block align-bottom bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full border border-gray-100 p-6 sm:p-8">
                    
                    <div class="flex justify-between items-start mb-6">
                        <div>
                            <span class="text-[10px] font-black tracking-widest text-red-600 uppercase bg-red-50 px-3 py-1 rounded-full">Ulasan Pembeli</span>
                            <h3 class="text-xl font-black text-gray-900 uppercase tracking-tight mt-2" id="modal-title">Beri Ulasan Produk</h3>
                            <p class="text-xs text-gray-500 font-medium mt-0.5" x-text="selectedProduct"></p>
                        </div>
                        <button @click="showReviewModal = false" class="text-gray-400 hover:text-gray-900 bg-gray-50 hover:bg-gray-100 rounded-full p-2 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    <form @submit.prevent="submitReview()" class="space-y-6">
                        @csrf
                        <div>
                            <label class="block text-xs font-bold uppercase text-gray-700 mb-2">Kualitas Produk</label>
                            <div class="flex items-center gap-1.5 cursor-pointer" @mouseleave="hoverRating = 0">
                                <template x-for="i in 5" :key="i">
                                    <button type="button" 
                                            @mouseenter="hoverRating = i" 
                                            @mouseleave="hoverRating = 0"
                                            @click="rating = i" 
                                            class="focus:outline-none transition transform hover:scale-110">
                                        <svg :class="(hoverRating >= i || (!hoverRating && rating >= i)) ? 'text-amber-400 fill-amber-400' : 'text-gray-200 fill-gray-200'" 
                                             class="w-8 h-8 transition-colors duration-150" viewBox="0 0 20 20">
                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                        </svg>
                                    </button>
                                </template>
                                <span class="ml-3 text-xs font-bold text-gray-600 bg-gray-100 px-2.5 py-1 rounded-lg" x-text="rating + ' / 5 Bintang'"></span>
                            </div>
                            <input type="hidden" name="rating" :value="rating">
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase text-gray-700 mb-2">Komentar & Kepuasan</label>
                            <textarea name="comment" rows="4" required placeholder="Bagaimana kualitas bahannya? Apakah ukurannya pas? Ceritakan pengalamanmu..." class="w-full bg-gray-50 border border-gray-200 rounded-2xl p-4 text-xs font-medium focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white transition"></textarea>
                        </div>

                        <button type="submit" 
                                :disabled="isSubmitting" 
                                class="w-full bg-red-600 hover:bg-red-700 disabled:bg-gray-400 text-white py-4 rounded-2xl font-black text-xs uppercase tracking-widest transition shadow-lg shadow-red-600/20 flex items-center justify-center gap-2 cursor-pointer">
                            <span x-show="!isSubmitting">Kirim Ulasan →</span>
                            <span x-show="isSubmitting" x-cloak>Mengirim Ulasan...</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <!-- ========================================== -->
    <!-- FOOTER -->
    <!-- ========================================== -->
    <footer class="bg-[#09090b] text-white pt-24 pb-12 mt-auto relative z-30">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12 pb-16 border-b border-gray-800/60">
                <div class="space-y-6">
                    <div class="flex items-center gap-3">
                        <div class="p-2 rounded-xl bg-white/5 border border-white/10">
                            <img src="{{ asset('image/LOGO.jpeg') }}" alt="Logo" class="h-7 w-auto object-contain rounded">
                        </div>
                        <span class="text-xl font-black tracking-tighter uppercase italic">
                            <span class="text-red-600">SPORT</span><span>MANIA</span>
                        </span>
                    </div>
                    <p class="text-gray-400 text-xs leading-relaxed font-medium">
                        Sebagai Pusat Sport Fashion & Apparel Online terdepan di Malang, kami menciptakan pengalaman gaya tanpa batas dengan menghadirkan perlengkapan olahraga original dan lokal terbaik. <strong>Bersama Sport Mania, You Own Now.</strong>
                    </p>
                    <div class="space-y-2 text-xs text-gray-400 font-medium">
                        <p class="font-bold text-white uppercase tracking-wider">Layanan Pelanggan</p>
                        <p class="text-red-500 font-semibold">WhatsApp CS: +62 822-3454-8092</p>
                    </div>
                </div>

                <div class="space-y-4">
                    <h4 class="font-black text-xs uppercase tracking-[0.2em] text-white">Layanan</h4>
                    <ul class="space-y-3 text-xs text-gray-400 font-medium">
                        <li><a href="#" class="hover:text-white transition">Bantuan & FAQ</a></li>
                        <li><a href="#" class="hover:text-white transition">Hubungi Kami (WhatsApp)</a></li>
                        <li><a href="#" class="hover:text-white transition">Pengiriman & Ongkir</a></li>
                    </ul>
                </div>

                <div class="space-y-4">
                    <h4 class="font-black text-xs uppercase tracking-[0.2em] text-white">Tentang Kami</h4>
                    <ul class="space-y-3 text-xs text-gray-400 font-medium">
                        <li><a href="#" class="hover:text-white transition">About Us (Tugas Akhir)</a></li>
                        <li><a href="#" class="hover:text-white transition">Persyaratan & Ketentuan</a></li>
                        <li><a href="#" class="hover:text-white transition">Kebijakan Privasi</a></li>
                    </ul>
                </div>

                <div class="space-y-6">
                    <div>
                        <h4 class="font-black text-xs uppercase tracking-[0.2em] text-white mb-2">Berlangganan Newsletter</h4>
                        <form @submit.prevent="submitNewsletter($event)" class="space-y-3">
                            <input type="email" placeholder="Alamat email Kamu" required class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-xs text-white focus:outline-none focus:border-red-500">
                            <button type="submit" class="w-full bg-white hover:bg-red-600 text-black hover:text-white font-bold text-xs uppercase tracking-wider py-2.5 rounded-xl transition cursor-pointer">Submit</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="pt-8 flex flex-col sm:flex-row justify-between items-center gap-4 text-xs text-gray-500 font-medium">
                <p>© 2026 Eka Bagus Karisma & Aditya Panji Pratama. Proyek Tugas Akhir S1 Pendidikan Teknik Informatika UM.</p>
            </div>
        </div>
    </footer>
</body>
</html>