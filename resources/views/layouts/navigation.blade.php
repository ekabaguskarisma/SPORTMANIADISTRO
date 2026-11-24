<nav x-data="{ open: false }" class="bg-white border-b border-gray-100 shadow-sm sticky top-0 z-50">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-20">
            <div class="flex">
                <!-- Tombol Kembali & Logo Sport Mania -->
                <div class="shrink-0 flex items-center gap-2.5 sm:gap-3">
                    <button type="button"
                            onclick="if (window.history.length > 1 && document.referrer.includes(window.location.host)) { window.history.back(); } else { window.location.href = '{{ Auth::user()->role === 'admin' ? route('admin.dashboard') : url('/') }}'; }"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-gray-100 hover:bg-gray-900 text-gray-800 hover:text-white text-xs font-black transition active:scale-95"
                            title="Kembali">
                        <span>←</span>
                        <span>Kembali</span>
                    </button>

                    <a href="{{ Auth::user()->role === 'admin' ? route('admin.dashboard') : url('/') }}" class="text-xl sm:text-2xl font-black tracking-tighter uppercase italic hover:opacity-80 transition">
                        <span class="text-red-600">SPORT</span><span class="text-gray-900">MANIA</span>
                    </a>
                </div>

                <!-- Navigation Links (Desktop) -->
                <div class="hidden space-x-6 sm:-my-px sm:ms-10 sm:flex items-center">
                    @if(Auth::user()->role === 'admin')
                        <x-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')" class="text-xs font-bold uppercase tracking-wider">
                            Dashboard Admin
                        </x-nav-link>

                        <x-nav-link href="/" class="text-xs font-bold uppercase tracking-wider text-gray-500 hover:text-gray-900">
                            Lihat Toko
                        </x-nav-link>
                    @else
                        <x-nav-link href="/" :active="request()->is('/')" class="text-xs font-bold uppercase tracking-wider text-gray-500 hover:text-gray-900">
                            Beranda Toko
                        </x-nav-link>

                        <x-nav-link :href="route('customer.orders')" :active="request()->routeIs('customer.orders')" class="text-xs font-bold uppercase tracking-wider text-gray-500 hover:text-gray-900">
                            Pesanan Saya
                        </x-nav-link>
                    @endif
                </div>
            </div>

            <!-- Settings Dropdown (Desktop) -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center gap-3 px-3 py-2 border border-transparent text-sm leading-4 font-bold rounded-2xl text-gray-700 bg-white hover:bg-gray-50 focus:outline-none transition ease-in-out duration-150">
                            <!-- Avatar / Inisial Pengguna -->
                            @if(Auth::user()->avatar)
                                <img src="{{ asset('storage/' . Auth::user()->avatar) }}" class="w-9 h-9 rounded-xl object-cover shadow-sm border border-gray-200">
                            @else
                                <div class="w-9 h-9 rounded-xl bg-gray-900 text-white flex items-center justify-center font-black text-xs uppercase shadow-sm">
                                    {{ strtoupper(substr(Auth::user()->name, 0, 2)) }}
                                </div>
                            @endif

                            <div class="text-left">
                                <span class="block text-xs font-bold text-gray-900 leading-tight">{{ Auth::user()->name }}</span>
                                <span class="block text-[10px] text-gray-400 font-semibold uppercase tracking-wider">
                                    {{ Auth::user()->role === 'admin' ? 'Administrator' : 'Pelanggan' }}
                                </span>
                            </div>

                            <div class="ms-1 text-gray-400">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')" class="text-xs font-bold text-gray-700 hover:bg-gray-50 hover:text-red-600">
                            Pengaturan Profil
                        </x-dropdown-link>

                        @if(Auth::user()->role !== 'admin')
                            <x-dropdown-link :href="route('customer.orders')" class="text-xs font-bold text-gray-700 hover:bg-gray-50 hover:text-red-600">
                                Riwayat Pesanan
                            </x-dropdown-link>
                        @endif

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}" onsubmit="localStorage.removeItem('cart_items')">
                            @csrf
                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                localStorage.removeItem('cart_items');
                                                this.closest('form').submit();" class="text-xs font-bold text-red-600 hover:bg-red-50">
                                Keluar (Log Out)
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger (Untuk Mobile) -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-xl text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu (Mobile) -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden bg-white border-t border-gray-100">
        <div class="pt-2 pb-3 space-y-1 px-4">
            @if(Auth::user()->role === 'admin')
                <x-responsive-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')" class="text-xs font-bold uppercase">
                    Dashboard Admin
                </x-responsive-nav-link>
                <x-responsive-nav-link href="/" class="text-xs font-bold uppercase text-gray-500">
                    Lihat Toko
                </x-responsive-nav-link>
            @else
                <x-responsive-nav-link href="/" :active="request()->is('/')" class="text-xs font-bold uppercase text-gray-500">
                    Beranda Toko
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('customer.orders')" :active="request()->routeIs('customer.orders')" class="text-xs font-bold uppercase text-gray-500">
                    Pesanan Saya
                </x-responsive-nav-link>
            @endif
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-4 border-t border-gray-100 px-4 bg-gray-50/50">
            <div class="flex items-center gap-3">
                @if(Auth::user()->avatar)
                    <img src="{{ asset('storage/' . Auth::user()->avatar) }}" class="w-10 h-10 rounded-xl object-cover shadow-sm border border-gray-200">
                @else
                    <div class="w-10 h-10 rounded-xl bg-gray-900 text-white flex items-center justify-center font-black text-sm uppercase shadow-sm">
                        {{ strtoupper(substr(Auth::user()->name, 0, 2)) }}
                    </div>
                @endif
                <div>
                    <div class="font-bold text-sm text-gray-900">{{ Auth::user()->name }}</div>
                    <div class="font-medium text-xs text-gray-500">{{ Auth::user()->email }}</div>
                </div>
            </div>

            <div class="mt-4 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')" class="text-xs font-bold text-gray-700">
                    Pengaturan Profil
                </x-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}" onsubmit="localStorage.removeItem('cart_items')">
                    @csrf
                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        localStorage.removeItem('cart_items');
                                        this.closest('form').submit();" class="text-xs font-bold text-red-600">
                        Keluar (Log Out)
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>