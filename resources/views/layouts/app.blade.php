<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', config('app.name', 'Sport Mania Distro'))</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

        <!-- Scripts & Styles -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            [x-cloak] { display: none !important; }
        </style>
    </head>
    <body class="font-sans antialiased bg-[#FAFAFA] text-gray-900">
        @hasSection('content')
            {{-- Layout Storefront Sport Mania --}}
            <div class="min-h-screen flex flex-col"
                 style="font-family: 'Inter', sans-serif;"
                 x-data="{
                     mobileMenuOpen: false,
                     cartItems: JSON.parse(localStorage.getItem('cart_items')) || [],
                     get totalItems() {
                         return this.cartItems.reduce((acc, item) => acc + Number(item.qty || 0), 0);
                     },
                     syncCart() {
                         this.cartItems = JSON.parse(localStorage.getItem('cart_items')) || [];
                     }
                 }"
                 @cart-updated.window="syncCart()"
                 @storage.window="syncCart()">

                <!-- Top Announcement Bar -->
                <div class="bg-gray-900 text-gray-300 text-[11px] py-2.5 px-4 font-medium tracking-wide border-b border-gray-800">
                    <div class="max-w-7xl mx-auto flex justify-between items-center">
                        <span class="inline-flex items-center gap-2 text-white">
                            <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse"></span>
                            PROMO 2026: Gratis Ongkir Seluruh Jawa min. Belanja Rp 250rb!
                        </span>
                        <a href="https://wa.me/6282234548092" target="_blank" class="hidden sm:flex items-center gap-1.5 text-gray-400 hover:text-white transition">
                            Bantuan CS (WhatsApp)
                        </a>
                    </div>
                </div>

                <!-- Navbar Storefront -->
                <nav class="bg-white/95 backdrop-blur-md border-b border-gray-100 sticky top-0 z-40">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div class="flex justify-between items-center h-20">
                            <!-- Brand Logo -->
                            <a href="{{ url('/') }}" class="flex items-center gap-3 hover:opacity-80 transition group">
                                <div class="p-1 rounded-lg bg-gray-900 border border-gray-800 shadow-inner">
                                    <img src="{{ asset('image/LOGO.jpeg') }}" alt="Logo" class="h-8 w-auto object-contain rounded">
                                </div>
                                <span class="text-xl lg:text-2xl font-black tracking-tighter uppercase italic">
                                    <span class="text-red-600 group-hover:text-red-500 transition">SPORT</span><span class="text-gray-900">MANIA</span>
                                </span>
                            </a>

                            <!-- Menu Desktop -->
                            <div class="hidden lg:flex items-center space-x-6">
                                <a href="{{ url('/') }}" class="text-xs font-bold uppercase tracking-wider text-gray-700 hover:text-red-600 transition">
                                    Beranda
                                </a>
                                <a href="{{ url('/#katalog') }}" class="text-xs font-bold uppercase tracking-wider text-gray-700 hover:text-red-600 transition">
                                    Katalog
                                </a>

                                <!-- Tombol Keranjang + Badge Real-time -->
                                <a href="{{ route('customer.cart') }}" class="relative inline-flex items-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-900 px-3.5 py-2 rounded-xl text-xs font-bold transition">
                                    <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                    <span>Keranjang</span>
                                    <span x-show="totalItems > 0"
                                          x-cloak
                                          x-text="totalItems"
                                          class="bg-red-600 text-white text-[10px] font-black px-1.5 py-0.5 rounded-full min-w-[20px] text-center"></span>
                                </a>

                                @if (Route::has('login'))
                                    @auth
                                        <div x-data="{ open: false }" class="relative border-l border-gray-200 pl-4">
                                            <button @click="open = !open" @click.outside="open = false" class="flex items-center gap-2 text-gray-800 font-bold focus:outline-none">
                                                <div class="w-7 h-7 rounded-full bg-red-100 text-red-600 flex items-center justify-center font-black text-xs">
                                                    {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                                                </div>
                                                <span class="text-xs truncate max-w-[100px]">{{ Auth::user()->name }}</span>
                                            </button>
                                            <div x-show="open" x-cloak class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-xl py-2 z-50 border border-gray-100">
                                                <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">Profil Saya</a>
                                                @if(Route::has('customer.orders'))
                                                    <a href="{{ route('customer.orders') }}" class="block px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">Pesanan Saya</a>
                                                @endif
                                                @if(Auth::user()->role === 'admin')
                                                    <a href="{{ route('admin.dashboard') }}" class="block px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">Panel Admin</a>
                                                @endif
                                                <form method="POST" action="{{ route('logout') }}" @submit="localStorage.removeItem('cart_items')">
                                                    @csrf
                                                    <button type="submit" class="block w-full text-left px-4 py-2 text-xs text-red-600 hover:bg-red-50 font-bold">Log Out</button>
                                                </form>
                                            </div>
                                        </div>
                                    @else
                                        <div class="flex items-center space-x-2 border-l border-gray-200 pl-4">
                                            <a href="{{ route('login') }}" class="text-gray-700 hover:text-red-600 font-bold text-xs uppercase px-2 py-1">Log in</a>
                                            <a href="{{ route('register') }}" class="bg-gray-900 hover:bg-red-600 text-white px-4 py-2 rounded-lg font-bold text-xs uppercase tracking-wider shadow-sm transition">Daftar</a>
                                        </div>
                                    @endauth
                                @endif
                            </div>

                            <!-- Menu Mobile (Keranjang + Hamburger) -->
                            <div class="flex items-center gap-3 lg:hidden">
                                <a href="{{ route('customer.cart') }}" class="relative p-2 text-gray-800">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                    <span x-show="totalItems > 0"
                                          x-cloak
                                          x-text="totalItems"
                                          class="absolute -top-0.5 -right-0.5 bg-red-600 text-white text-[10px] font-black px-1.5 py-0.2 rounded-full"></span>
                                </a>
                                <button @click="mobileMenuOpen = !mobileMenuOpen" class="text-gray-700 p-2 focus:outline-none">
                                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                                </button>
                            </div>
                        </div>

                        <!-- Dropdown Mobile -->
                        <div x-show="mobileMenuOpen" x-cloak class="lg:hidden pb-5 border-t border-gray-100 pt-4 space-y-3">
                            <a href="{{ url('/') }}" class="block px-3 py-2 text-xs font-bold uppercase text-gray-700 hover:bg-gray-50 rounded-lg">Beranda</a>
                            <a href="{{ url('/#katalog') }}" class="block px-3 py-2 text-xs font-bold uppercase text-gray-700 hover:bg-gray-50 rounded-lg">Katalog</a>
                            <a href="{{ route('customer.cart') }}" class="block px-3 py-2 text-xs font-bold uppercase text-red-600 hover:bg-red-50 rounded-lg">
                                Keranjang Belanja (<span x-text="totalItems"></span>)
                            </a>
                        </div>
                    </div>
                </nav>

                <!-- Page Content -->
                <main class="flex-grow">
                    @yield('content')
                </main>

                <!-- Footer -->
                <footer class="bg-white border-t border-gray-200 py-10 mt-auto">
                    <div class="max-w-7xl mx-auto px-4 text-center">
                        <img src="{{ asset('image/LOGO.jpeg') }}" alt="Logo" class="h-10 w-auto mx-auto mb-4 object-contain grayscale opacity-60">
                        <p class="text-gray-500 text-[10px] sm:text-xs font-medium">© 2026 Eka Bagus Karisma & Aditya Panji Pratama. Proyek Tugas Akhir S1 Pendidikan Teknik Informatika.</p>
                    </div>
                </footer>
            </div>
        @else
            {{-- Layout Default Breeze untuk halaman komponen x-app-layout --}}
            <div class="min-h-screen bg-gray-100">
                @include('layouts.navigation')

                @isset($header)
                    <header class="bg-white shadow">
                        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                <main>
                    {{ $slot ?? '' }}
                </main>
            </div>
        @endif
    </body>
</html>