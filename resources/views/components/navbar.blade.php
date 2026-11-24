<nav x-data="{ mobileMenuOpen: false, mobileSearchOpen: false, openKategori: false, searchQuery: '{{ request('search') }}' }"
     class="bg-white/95 backdrop-blur-md border-b border-gray-100 sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16 sm:h-20 gap-2">

            <!-- Kiri: Tombol Kembali (Otomatis muncul di semua halaman selain Beranda) + Logo -->
            <div class="flex items-center gap-2.5 min-w-0">
                @if(!request()->routeIs('home'))
                    <a href="javascript:history.back()"
                       onclick="if(document.referrer === '' || !document.referrer.includes(window.location.host)) { this.href = '{{ route('home') }}'; }"
                       class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl bg-gray-100 hover:bg-gray-900 text-gray-800 hover:text-white text-xs font-black transition shrink-0"
                       title="Kembali">
                        ← <span class="hidden sm:inline">Kembali</span>
                    </a>
                @endif

                <a href="{{ route('home') }}" class="flex items-center gap-2.5 hover:opacity-80 transition shrink-0">
                    <div class="p-1 rounded-lg bg-gray-900 border border-gray-800">
                        <img src="{{ asset('image/LOGO.jpeg') }}" alt="Logo" class="h-7 sm:h-8 w-auto object-contain rounded">
                    </div>
                    <span class="text-lg sm:text-2xl font-black tracking-tighter uppercase italic">
                        <span class="text-red-600">SPORT</span><span class="text-gray-900">MANIA</span>
                    </span>
                </a>
            </div>

            <!-- Tengah: Menu Desktop -->
            <div class="hidden lg:flex space-x-6 items-center">
                <a href="{{ route('home') }}" class="text-gray-900 hover:text-red-600 font-bold text-xs uppercase tracking-wider">Beranda</a>
                <a href="{{ route('home') }}#katalog" class="text-gray-700 hover:text-red-600 font-bold text-xs uppercase tracking-wider">Katalog</a>
                <a href="{{ route('home', ['category' => 'pakaian']) }}#katalog" class="text-gray-700 hover:text-red-600 font-bold text-xs uppercase tracking-wider">Pakaian</a>
                <a href="{{ route('home', ['category' => 'sepatu']) }}#katalog" class="text-gray-700 hover:text-red-600 font-bold text-xs uppercase tracking-wider">Sepatu</a>
                <a href="{{ route('home', ['category' => 'tas']) }}#katalog" class="text-gray-700 hover:text-red-600 font-bold text-xs uppercase tracking-wider">Tas</a>
                <a href="{{ route('home', ['category' => 'promo']) }}#katalog" class="text-red-600 font-black text-xs uppercase tracking-wider px-3 py-1.5 bg-red-50 rounded-full border border-red-200">🔥 Promo</a>
            </div>

            <!-- Kanan: Search Desktop & Auth -->
            <div class="hidden lg:flex items-center space-x-3">
                <form action="{{ route('home') }}#katalog" method="GET" class="flex items-center bg-gray-100 rounded-full pl-3.5 pr-1.5 py-1">
                    <input type="text" name="search" x-model="searchQuery" placeholder="Cari produk..." class="bg-transparent text-xs text-gray-900 focus:outline-none w-40 xl:w-48 font-medium">
                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white text-[11px] font-black px-3 py-1.5 rounded-full transition">Cari</button>
                </form>

                <a href="{{ route('customer.cart') }}" class="text-gray-700 hover:text-red-600 p-2 rounded-full hover:bg-gray-100" title="Keranjang">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                </a>

                @auth
                    <a href="{{ route('customer.orders') }}" class="text-xs font-bold text-gray-700 hover:text-red-600">Pesanan</a>
                    <a href="{{ route('profile.edit') }}" class="text-xs font-bold text-gray-900 bg-gray-100 px-3 py-1.5 rounded-lg">{{ Auth::user()->name }}</a>
                @else
                    <a href="{{ route('login') }}" class="text-xs font-bold text-gray-700 hover:text-red-600">Log in</a>
                    <a href="{{ route('register') }}" class="bg-gray-900 text-white px-3.5 py-1.5 rounded-lg font-bold text-xs uppercase">Daftar</a>
                @endauth
            </div>

            <!-- Kanan Mobile (HP): Tombol Search, Keranjang & Hamburger -->
            <div class="flex items-center gap-1 lg:hidden">
                <button type="button"
                        @click="mobileSearchOpen = !mobileSearchOpen; mobileMenuOpen = false"
                        class="p-2 rounded-full text-gray-700 hover:bg-gray-100"
                        title="Cari">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </button>

                <a href="{{ route('customer.cart') }}" class="p-2 rounded-full text-gray-700 hover:bg-gray-100" title="Keranjang">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                </a>

                <button type="button" @click="mobileMenuOpen = !mobileMenuOpen; mobileSearchOpen = false" class="p-2 rounded-lg text-gray-700 hover:bg-gray-100">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                </button>
            </div>
        </div>

        <!-- Bar Search HP (Muncul saat tombol kaca pembesar diklik) -->
        <div x-show="mobileSearchOpen" x-cloak class="lg:hidden pb-3 pt-2 border-t border-gray-100">
            <form action="{{ route('home') }}#katalog" method="GET" class="flex gap-2">
                <input type="text" name="search" x-model="searchQuery" placeholder="Cari produk..." class="flex-1 bg-gray-100 text-xs rounded-xl px-3.5 py-2.5 focus:outline-none focus:ring-2 focus:ring-red-600">
                <button type="submit" class="bg-red-600 text-white px-4 py-2.5 rounded-xl font-black text-xs uppercase">Cari</button>
            </form>
        </div>

        <!-- Menu Dropdown HP -->
        <div x-show="mobileMenuOpen" x-cloak class="lg:hidden pb-4 pt-3 border-t border-gray-100 space-y-3">
            <form action="{{ route('home') }}#katalog" method="GET" class="flex gap-2">
                <input type="text" name="search" x-model="searchQuery" placeholder="Cari produk..." class="flex-1 bg-gray-100 text-xs rounded-xl px-3.5 py-2.5 focus:outline-none focus:ring-2 focus:ring-red-600">
                <button type="submit" class="bg-red-600 text-white px-4 py-2.5 rounded-xl font-black text-xs uppercase">Cari</button>
            </form>

            <div class="flex flex-wrap gap-2">
                <a href="{{ route('home') }}" class="text-xs font-bold px-3 py-1.5 rounded-lg bg-gray-100">Beranda</a>
                <a href="{{ route('home') }}#katalog" class="text-xs font-bold px-3 py-1.5 rounded-lg bg-gray-100">Katalog</a>
                <a href="{{ route('home', ['category' => 'pakaian']) }}#katalog" class="text-xs font-bold px-3 py-1.5 rounded-lg bg-gray-100">Pakaian</a>
                <a href="{{ route('home', ['category' => 'sepatu']) }}#katalog" class="text-xs font-bold px-3 py-1.5 rounded-lg bg-gray-100">Sepatu</a>
                <a href="{{ route('home', ['category' => 'tas']) }}#katalog" class="text-xs font-bold px-3 py-1.5 rounded-lg bg-gray-100">Tas</a>
                <a href="{{ route('home', ['category' => 'aksesoris']) }}#katalog" class="text-xs font-bold px-3 py-1.5 rounded-lg bg-gray-100">Aksesoris</a>
                <a href="{{ route('home', ['category' => 'promo']) }}#katalog" class="text-xs font-black px-3 py-1.5 rounded-lg bg-red-600 text-white">🔥 Promo</a>
            </div>

            <div class="border-t border-gray-100 pt-3 flex flex-wrap gap-2">
                @auth
                    <a href="{{ route('profile.edit') }}" class="text-xs font-bold px-3 py-2 rounded-lg bg-gray-100">Profil Saya</a>
                    <a href="{{ route('customer.orders') }}" class="text-xs font-bold px-3 py-2 rounded-lg bg-gray-100">Pesanan Saya</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-xs font-bold px-3 py-2 rounded-lg bg-red-50 text-red-600">Log Out</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="text-xs font-bold px-4 py-2 rounded-lg bg-gray-100">Log in</a>
                    <a href="{{ route('register') }}" class="text-xs font-bold px-4 py-2 rounded-lg bg-gray-900 text-white">Daftar</a>
                @endauth
            </div>
        </div>
    </div>
</nav>