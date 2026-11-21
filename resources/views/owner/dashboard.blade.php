<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Admin Dashboard | Sport Mania Distro</title>
    
    <!-- Tailwind CDN, Font Inter & Alpine.js -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <style> 
        body { font-family: 'Inter', sans-serif; } 
        [x-cloak] { display: none !important; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #fafafa; }
        ::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 4px; }
    </style>
</head>
<body class="bg-[#F8FAFC] text-gray-900 antialiased selection:bg-red-500 selection:text-white flex h-screen overflow-hidden"
      x-data="{ sidebarOpen: false }">

    <!-- SIDEBAR NAVIGATION (DESKTOP & MOBILE) -->
    <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'" 
           class="fixed inset-y-0 left-0 z-50 w-64 bg-gray-900 text-gray-300 transition-transform duration-300 ease-in-out lg:translate-x-0 lg:static lg:inset-auto flex flex-col border-r border-gray-800">
        
        <!-- Sidebar Header / Logo -->
        <div class="h-20 flex items-center justify-between px-6 border-b border-gray-800">
            <a href="/" class="flex items-center gap-3">
                <div class="p-1 rounded-lg bg-gray-800 border border-gray-700">
                    <img src="{{ asset('image/LOGO.jpeg') }}" alt="Logo" class="h-7 w-auto object-contain rounded">
                </div>
                <span class="text-lg font-black tracking-tighter uppercase italic text-white">
                    <span class="text-red-500">SPORT</span>MANIA
                </span>
            </a>
            <button @click="sidebarOpen = false" class="lg:hidden text-gray-400 hover:text-white">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>

        <!-- Sidebar Menu Items -->
        <div class="flex-1 px-4 py-6 space-y-1.5 overflow-y-auto text-xs font-bold uppercase tracking-wider">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-red-600 text-white shadow-lg shadow-red-600/20">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                Dashboard
            </a>
            <a href="#" class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-400 hover:bg-gray-800 hover:text-white transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                Kelola Produk
            </a>
            <a href="#" class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-400 hover:bg-gray-800 hover:text-white transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                Data Pesanan
            </a>
            <a href="#" class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-400 hover:bg-gray-800 hover:text-white transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                Manajemen User
            </a>
        </div>

        <!-- Sidebar Footer / Logout -->
        <div class="p-4 border-t border-gray-800">
            <form method="POST" action="{{ route('logout') }}" @submit="localStorage.removeItem('cart_items')">
                @csrf
                <button type="submit" class="w-full flex items-center justify-center gap-2 bg-gray-800 hover:bg-red-600 text-white py-3 rounded-xl font-bold text-xs uppercase tracking-wider transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                    Log Out
                </button>
            </form>
        </div>
    </aside>

    <!-- MAIN CONTENT WRAPPER -->
    <div class="flex-1 flex flex-col overflow-hidden">
        
        <!-- Top Navbar Admin -->
        <header class="h-20 bg-white border-b border-gray-200 flex items-center justify-between px-6 z-30">
            <div class="flex items-center gap-4">
                <button @click="sidebarOpen = true" class="lg:hidden text-gray-600 hover:text-gray-900 focus:outline-none">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                </button>
                <h1 class="text-base sm:text-xl font-black text-gray-900 uppercase tracking-tight">Panel Admin <span class="text-red-600">Dashboard</span></h1>
            </div>

            <!-- Admin Profile Menu -->
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-full bg-red-100 text-red-600 flex items-center justify-center font-black text-xs">
                    {{ strtoupper(substr(Auth::user()->name ?? 'A', 0, 1)) }}
                </div>
                <div class="hidden sm:flex flex-col text-left">
                    <span class="text-xs font-bold text-gray-900">{{ Auth::user()->name ?? 'Administrator' }}</span>
                    <span class="text-[9px] text-gray-400 font-semibold uppercase">Super Admin</span>
                </div>
            </div>
        </header>

        <!-- Main Scrollable Content -->
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-[#F8FAFC] p-6 sm:p-8 space-y-8">
            
            <!-- STATS CARDS -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Card 1 -->
                <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Total Pendapatan</p>
                        <h3 class="text-2xl font-black text-gray-900 mt-1">Rp 14.850.000</h3>
                        <span class="inline-block bg-emerald-50 text-emerald-600 text-[10px] font-bold px-2 py-0.5 rounded mt-2">+12% bulan ini</span>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-black text-lg">💰</div>
                </div>

                <!-- Card 2 -->
                <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Pesanan Masuk</p>
                        <h3 class="text-2xl font-black text-gray-900 mt-1">28 Pesanan</h3>
                        <span class="inline-block bg-blue-50 text-blue-600 text-[10px] font-bold px-2 py-0.5 rounded mt-2">3 Pending Konfirmasi</span>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center font-black text-lg">📦</div>
                </div>

                <!-- Card 3 -->
                <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Total Produk</p>
                        <h3 class="text-2xl font-black text-gray-900 mt-1">42 Produk</h3>
                        <span class="inline-block bg-amber-50 text-amber-600 text-[10px] font-bold px-2 py-0.5 rounded mt-2">Stok aman</span>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center font-black text-lg">👕</div>
                </div>

                <!-- Card 4 -->
                <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Pelanggan Aktif</p>
                        <h3 class="text-2xl font-black text-gray-900 mt-1">115 User</h3>
                        <span class="inline-block bg-purple-50 text-purple-600 text-[10px] font-bold px-2 py-0.5 rounded mt-2">Terdaftar di Sistem</span>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-purple-50 text-purple-600 flex items-center justify-center font-black text-lg">👥</div>
                </div>
            </div>

            <!-- RECENT ORDERS TABLE -->
            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="p-6 sm:p-8 border-b border-gray-100 flex justify-between items-center">
                    <div>
                        <h3 class="text-base sm:text-lg font-black text-gray-900 uppercase tracking-tight">Pesanan Terbaru Masuk</h3>
                        <p class="text-xs text-gray-500 font-medium mt-0.5">Daftar transaksi pesanan dari pelanggan via website & WhatsApp.</p>
                    </div>
                    <a href="#" class="text-xs font-bold text-red-600 hover:underline uppercase tracking-wider">Lihat Semua →</a>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50 text-[10px] font-black uppercase tracking-widest text-gray-400 border-b border-gray-100">
                                <th class="py-4 px-6">ID Pesanan</th>
                                <th class="py-4 px-6">Pelanggan</th>
                                <th class="py-4 px-6">Metode & Pengiriman</th>
                                <th class="py-4 px-6">Total Tagihan</th>
                                <th class="py-4 px-6">Status</th>
                                <th class="py-4 px-6 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="text-xs font-medium divide-y divide-gray-100 text-gray-700">
                            <!-- Mockup Baris 1 -->
                            <tr class="hover:bg-gray-50/50 transition">
                                <td class="py-4 px-6 font-bold text-gray-900">#SM-202601</td>
                                <td class="py-4 px-6">
                                    <div class="font-bold text-gray-900">Aditya Panji Pratama</div>
                                    <div class="text-[10px] text-gray-400">082234548092</div>
                                </td>
                                <td class="py-4 px-6">
                                    <span class="block font-bold text-gray-900">Transfer Bank</span>
                                    <span class="text-[10px] text-gray-500">JNE REG (Malang)</span>
                                </td>
                                <td class="py-4 px-6 font-black text-red-600">Rp 870.000</td>
                                <td class="py-4 px-6">
                                    <span class="bg-amber-50 text-amber-600 font-bold px-2.5 py-1 rounded-full text-[10px] uppercase">Menunggu Pembayaran</span>
                                </td>
                                <td class="py-4 px-6 text-center">
                                    <button class="bg-gray-100 hover:bg-gray-900 hover:text-white text-gray-700 px-3 py-1.5 rounded-lg font-bold transition text-[10px] uppercase">Detail</button>
                                </td>
                            </tr>

                            <!-- Mockup Baris 2 -->
                            <tr class="hover:bg-gray-50/50 transition">
                                <td class="py-4 px-6 font-bold text-gray-900">#SM-202602</td>
                                <td class="py-4 px-6">
                                    <div class="font-bold text-gray-900">Bagus Karisma</div>
                                    <div class="text-[10px] text-gray-400">081234567890</div>
                                </td>
                                <td class="py-4 px-6">
                                    <span class="block font-bold text-gray-900">QRIS / E-Wallet</span>
                                    <span class="text-[10px] text-green-600 font-bold">Ambil di Store Malang</span>
                                </td>
                                <td class="py-4 px-6 font-black text-red-600">Rp 350.000</td>
                                <td class="py-4 px-6">
                                    <span class="bg-emerald-50 text-emerald-600 font-bold px-2.5 py-1 rounded-full text-[10px] uppercase">Selesai / Lunas</span>
                                </td>
                                <td class="py-4 px-6 text-center">
                                    <button class="bg-gray-100 hover:bg-gray-900 hover:text-white text-gray-700 px-3 py-1.5 rounded-lg font-bold transition text-[10px] uppercase">Detail</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>
</body>
</html>