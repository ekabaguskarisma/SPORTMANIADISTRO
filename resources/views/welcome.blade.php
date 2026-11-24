@php
    $products = $products ?? collect();
    $promoBanners = $promoBanners ?? collect();
    $trustedBrands = $trustedBrands ?? [];
    $heroSlides = $heroSlides ?? [];
    $curatedCategories = $curatedCategories ?? [];
    $featuredShowcase = $featuredShowcase ?? [];

    $resolveHomeMedia = function (?string $raw, string $fallback): string {
        if (blank($raw)) {
            return $fallback;
        }
        $trimmed = trim($raw);
        if (filter_var($trimmed, FILTER_VALIDATE_URL)) {
            return $trimmed;
        }
        $cleanPath = ltrim(preg_replace('#^storage/#', '', $trimmed), '/');
        return asset('storage/' . $cleanPath);
    };

    $defaultBrands = [
        ['code' => 'SM', 'name' => 'Sport Mania', 'subtitle' => 'Eksklusif Apparel', 'logo' => null],
        ['code' => 'NIKE', 'name' => 'Pro Combat', 'subtitle' => 'Authorized Partner', 'logo' => null],
        ['code' => 'ADIDAS', 'name' => 'Performance', 'subtitle' => 'Official License', 'logo' => null],
        ['code' => 'PUMA', 'name' => 'Speed Series', 'subtitle' => 'Verified Supplier', 'logo' => null],
    ];
    $brandsList = !empty($trustedBrands) ? array_values($trustedBrands) : $defaultBrands;

    $defaultLookbookSlides = [
        [
            'tag' => '#1 Best Seller',
            'subtitle' => 'Performance Apparel',
            'title' => 'PRO RUNNING 2026',
            'desc' => 'Dirancang dengan teknologi aerodinamis ringan untuk memaksimalkan kecepatan lari tanpa kompromi.',
            'cta_text' => 'Jelajahi Koleksi',
            'cta_url' => '#katalog',
            'image' => 'https://images.unsplash.com/photo-1517838277536-f5f99be501cd?q=80&w=2070&auto=format&fit=crop',
        ],
        [
            'tag' => '#2 High Performance',
            'subtitle' => 'Maximum Strength',
            'title' => 'ELITE TRAINING',
            'desc' => 'Dukungan perlengkapan angkat beban profesional untuk menjaga stabilitas dan performa latihan maksimal.',
            'cta_text' => 'Jelajahi Koleksi',
            'cta_url' => '#katalog',
            'image' => 'https://images.unsplash.com/photo-1581009146145-b5ef050c2e1e?q=80&w=2070&auto=format&fit=crop',
        ],
    ];
    $lookbookSlidesData = !empty($heroSlides)
        ? array_values(array_map(function ($s, $i) use ($resolveHomeMedia, $defaultLookbookSlides) {
            $fallbackSlide = $defaultLookbookSlides[$i % count($defaultLookbookSlides)];
            return [
                'tag' => $s['badge'] ?? $fallbackSlide['tag'],
                'subtitle' => $s['kicker'] ?? $fallbackSlide['subtitle'],
                'title' => $s['title'] ?? $fallbackSlide['title'],
                'desc' => $s['description'] ?? $fallbackSlide['desc'],
                'ctaText' => $s['cta_text'] ?? $fallbackSlide['cta_text'],
                'ctaUrl' => $s['cta_url'] ?? $fallbackSlide['cta_url'],
                'bgImage' => $resolveHomeMedia($s['image'] ?? '', $fallbackSlide['image']),
            ];
        }, $heroSlides, array_keys($heroSlides)))
        : $defaultLookbookSlides;

    $defaultCategories = [
        [
            'id' => 'women',
            'name' => 'WOMEN',
            'subtitle' => 'Elevated & Active',
            'tagline' => 'Koleksi Aktif & Stylish',
            'url' => '#katalog',
            'image' => 'https://images.unsplash.com/photo-1518310383802-640c2de311b2?q=80&w=1000&auto=format&fit=crop',
        ],
        [
            'id' => 'men',
            'name' => 'MEN',
            'subtitle' => 'Streetwear & Sport',
            'tagline' => 'Performa Maskulin & Modern',
            'url' => '#katalog',
            'image' => 'https://images.unsplash.com/photo-1526506118085-60ce8714f8c5?q=80&w=1000&auto=format&fit=crop',
        ],
        [
            'id' => 'sports',
            'name' => 'SPORTS',
            'subtitle' => 'High Performance',
            'tagline' => 'Gear Profesional untuk Atlit Juara',
            'url' => '#katalog',
            'image' => 'https://images.unsplash.com/photo-1517836357463-d25dfeac3438?q=80&w=1000&auto=format&fit=crop',
        ],
        [
            'id' => 'lifestyle',
            'name' => 'LIFESTYLE',
            'subtitle' => 'Daily Comfort',
            'tagline' => 'Kenyamanan Maksimal Setiap Hari',
            'url' => '#katalog',
            'image' => 'https://images.unsplash.com/photo-1556906781-9a412961c28c?q=80&w=1000&auto=format&fit=crop',
        ],
    ];
    $categoriesData = !empty($curatedCategories)
        ? array_values(array_map(function ($c, $i) use ($resolveHomeMedia, $defaultCategories) {
            $fallbackCat = $defaultCategories[$i % count($defaultCategories)];
            $title = $c['title'] ?? $fallbackCat['name'];
            $slugId = \Illuminate\Support\Str::slug($title) ?: $fallbackCat['id'];
            return [
                'id' => $slugId,
                'name' => $title,
                'subtitle' => $c['tag'] ?? $fallbackCat['subtitle'],
                'tagline' => $c['subtitle'] ?? $fallbackCat['tagline'],
                'url' => $c['url'] ?? '#katalog',
                'image' => $resolveHomeMedia($c['image'] ?? '', $fallbackCat['image']),
            ];
        }, $curatedCategories, array_keys($curatedCategories)))
        : $defaultCategories;

    $showcaseData = [
        'badge' => $featuredShowcase['badge'] ?? 'FEATURED SHOWCASE',
        'title' => $featuredShowcase['title'] ?? '',
        'description' => $featuredShowcase['description'] ?? '',
        'cta_text' => $featuredShowcase['cta_text'] ?? '',
        'cta_url' => $featuredShowcase['cta_url'] ?? '#katalog',
        'image' => !empty($featuredShowcase['image'])
            ? $resolveHomeMedia($featuredShowcase['image'], '')
            : '',
    ];

    $userAvatarUrl = null;
    if (\Illuminate\Support\Facades\Auth::check()) {
        $authUser = \Illuminate\Support\Facades\Auth::user();
        $userAvatarRaw = $authUser->avatar
            ?? $authUser->profile_photo
            ?? $authUser->photo
            ?? $authUser->image
            ?? null;

        if (filled($userAvatarRaw)) {
            $userAvatarUrl = filter_var($userAvatarRaw, FILTER_VALIDATE_URL)
                ? $userAvatarRaw
                : asset('storage/' . ltrim($userAvatarRaw, '/'));
        }
    }

    $allowedFilters = ['semua', 'pakaian', 'sepatu', 'tas', 'aksesoris', 'promo', 'women', 'men', 'sports', 'lifestyle'];
    $requestedCategory = strtolower(trim((string) request('category', 'semua')));
    $initialFilter = in_array($requestedCategory, $allowedFilters, true) ? $requestedCategory : 'semua';
    $initialSearch = trim((string) request('search', ''));

    // 1. Batch query jumlah terjual (1 query untuk semua produk, mencegah N+1)
    $soldCountsMap = [];
    if (\Illuminate\Support\Facades\Schema::hasTable('order_items') && \Illuminate\Support\Facades\Schema::hasTable('orders')) {
        $qtyColumn = \Illuminate\Support\Facades\Schema::hasColumn('order_items', 'quantity')
            ? 'order_items.quantity'
            : (\Illuminate\Support\Facades\Schema::hasColumn('order_items', 'qty') ? 'order_items.qty' : null);

        if ($qtyColumn) {
            $soldCountsMap = \Illuminate\Support\Facades\DB::table('order_items')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->whereNotIn('orders.status', ['cancelled', 'dibatalkan', 'failed', 'batal'])
                ->select('order_items.product_id', \Illuminate\Support\Facades\DB::raw("SUM({$qtyColumn}) as total_sold"))
                ->groupBy('order_items.product_id')
                ->pluck('total_sold', 'product_id')
                ->toArray();
        }
    }

    // 2. Batch query rating & jumlah ulasan jika belum di-eager-load dari controller
    $reviewStatsMap = [];
    if (\Illuminate\Support\Facades\Schema::hasTable('reviews')) {
        $reviewStatsMap = \Illuminate\Support\Facades\DB::table('reviews')
            ->select(
                'product_id',
                \Illuminate\Support\Facades\DB::raw('COUNT(*) as total_reviews'),
                \Illuminate\Support\Facades\DB::raw('AVG(rating) as avg_rating')
            )
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id')
            ->toArray();
    }

    $productsMeta = $products->map(function ($product, $idx) use ($soldCountsMap, $reviewStatsMap) {
        $catSlug = strtolower(optional($product->category)->slug ?? 'lainnya');
        $catName = optional($product->category)->name ?? 'Sport Apparel';
        $stat = $reviewStatsMap[$product->id] ?? null;

        $revCount = isset($product->reviews_count)
            ? (int) $product->reviews_count
            : ($product->relationLoaded('reviews') ? $product->reviews->count() : (int) ($stat->total_reviews ?? 0));

        $avgRev = $revCount > 0
            ? round((float) ($product->reviews_avg_rating ?? ($product->relationLoaded('reviews') ? $product->reviews->avg('rating') : ($stat->avg_rating ?? 0))), 1)
            : 0.0;

        $soldTotal = isset($product->sold_count)
            ? (int) $product->sold_count
            : (int) ($soldCountsMap[$product->id] ?? 0);

        $hasDiscount = $product->discount_price !== null && (float) $product->discount_price > 0;

        return [
            'id' => $product->id,
            'index' => $idx,
            'cat' => $catSlug,
            'search' => strtolower($product->name . ' ' . ($product->description ?? '') . ' ' . $catName),
            'promo' => (bool) ($hasDiscount || $product->is_featured),
            'price' => (float) ($hasDiscount ? $product->discount_price : $product->price),
            'rating' => $avgRev,
            'reviews' => $revCount,
            'sold' => $soldTotal,
        ];
    })->values()->toArray();

    // 3. Siapkan Data Promo Carousel (Semua promo aktif urut sort_order)
    $sortedPromos = $promoBanners->sortBy(fn($b) => (int) ($b->sort_order ?? 1))->values();

    $promoSlidesData = $sortedPromos->map(function ($b) {
        $isVideo = ($b->media_type ?? 'image') === 'video' && !empty($b->video_path);
        $rawMedia = $isVideo ? $b->video_path : ($b->image ?? null);
        $mediaUrl = $rawMedia
            ? (filter_var($rawMedia, FILTER_VALIDATE_URL) ? $rawMedia : asset('storage/' . $rawMedia))
            : 'https://images.unsplash.com/photo-1517838277536-f5f99be501cd?q=80&w=1600&auto=format&fit=crop';

        $dateLabel = $b->created_at
            ? \Carbon\Carbon::parse($b->created_at)->translatedFormat('d M Y')
            : 'PROMO 2026';

        return [
            'id'         => $b->id,
            'badge'      => $b->badge_text ?: 'PROMO SPESIAL',
            'subtitle'   => $b->subtitle ?: $dateLabel,
            'title'      => $b->title,
            'desc'       => $b->description ?: '',
            'btnText'    => $b->button_text ?: 'LIHAT PROMO',
            'link'       => $b->button_url ?: '#katalog',
            'caption'    => $b->image_caption ?: '',
            'mediaType'  => $isVideo ? 'video' : 'image',
            'mediaUrl'   => $mediaUrl,
        ];
    })->values()->toArray();
@endphp
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sport Mania Distro - Pusat Sport Fashion & Apparel Original Terdepan di Malang.">
    <title>Sport Mania Distro | Premium Sportswear</title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
        'resources/css/pages/welcome.css',
    ])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <script>
        window.__SM_PRODUCTS_META__ = @json($productsMeta);
        window.__SM_PROMO_SLIDES__ = @json($promoSlidesData);
        window.__SM_LOOKBOOK_SLIDES__ = @json($lookbookSlidesData);
        window.__SM_EDITORIAL_CATEGORIES__ = @json($categoriesData);
        window.__SM_WELCOME_CONFIG__ = {
            initialFilter: @json($initialFilter),
            initialSearch: @json($initialSearch)
        };
    </script>
</head>
<body class="welcome-page bg-[#FAFAFA] text-gray-900 antialiased selection:bg-red-500 selection:text-white"
      x-data="welcomePage()"
      @cart-updated.window="syncCart()"
      @storage.window="if ($event.key === 'cart_items') syncCart()"
      @pageshow.window="syncCart()">

    <!-- FLOATING TOAST NOTIFICATION -->
    <div class="fixed top-6 right-6 z-50 max-w-sm w-full px-4 pointer-events-none"
         role="status"
         aria-live="polite"
         x-show="toast.show"
         x-transition:enter="transition ease-out duration-300 transform"
         x-transition:enter-start="translate-y-[-20px] opacity-0"
         x-transition:enter-end="translate-y-0 opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0 translate-y-[-20px]" x-cloak>
        <div class="flex items-center gap-3 p-4 rounded-2xl shadow-2xl border text-xs font-bold pointer-events-auto"
             :class="toast.type === 'success' ? 'bg-gray-900 text-white border-gray-800' : 'bg-red-600 text-white border-red-500'">
            <span class="flex h-2 w-2 rounded-full shrink-0" :class="toast.type === 'success' ? 'bg-green-400 animate-pulse' : 'bg-white animate-ping'"></span>
            <p class="flex-1" x-text="toast.message"></p>
        </div>
    </div>

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
                <a href="{{ route('order.track') }}" class="hover:text-white transition flex items-center gap-1.5 text-red-400 font-bold">
                    📦 Lacak Pesanan
                </a>
                <span class="text-gray-700">|</span>
                <a href="https://wa.me/6282234548092" target="_blank" rel="noopener noreferrer" class="hover:text-white transition flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-green-500" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                    Bantuan CS (WhatsApp)
                </a>
                <span class="text-gray-700">|</span>
                <a href="#katalog" class="hover:text-white transition">Katalog Produk</a>
            </div>
        </div>
    </div>

    <!-- Main Navbar -->
    <nav x-data="{ mobileSearchOpen: false }" class="bg-white/95 backdrop-blur-md border-b border-gray-100 sticky top-0 z-40 transition-all duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">

                <!-- Logo & Brand Name -->
                <div class="flex-shrink-0 flex items-center">
                    <a href="{{ route('home') }}" class="flex items-center gap-3 hover:opacity-80 transition group">
                        <div class="p-1 rounded-lg bg-gray-900 border border-gray-800 shadow-inner">
                            <img src="{{ asset('image/LOGO.jpeg') }}" alt="Logo Sport Mania" class="h-8 w-auto object-contain rounded">
                        </div>
                        <span class="text-xl lg:text-2xl font-black tracking-tighter uppercase italic">
                            <span class="text-red-600 group-hover:text-red-500 transition">SPORT</span><span class="text-gray-900">MANIA</span>
                        </span>
                    </a>
                </div>

                <!-- Navigasi Tengah -->
                <div class="hidden lg:flex space-x-6 items-center">
                    <a href="#" @click.prevent="resetFilters(); window.scrollTo({top: 0, behavior: 'smooth'})" class="text-gray-900 hover:text-red-600 font-bold transition text-xs uppercase tracking-wider">Beranda</a>
                    <a href="#katalog" @click="activeFilter = 'semua'" class="text-gray-700 hover:text-red-600 font-bold transition text-xs uppercase tracking-wider">Katalog</a>

                    <!-- Dropdown Kategori -->
                    <div class="relative" @mouseleave="openKategori = false">
                        <button type="button"
                                @mouseover="openKategori = true"
                                @click="openKategori = !openKategori"
                                :aria-expanded="openKategori.toString()"
                                class="flex items-center gap-1 text-gray-700 hover:text-red-600 font-bold transition text-xs uppercase tracking-wider focus:outline-none py-2">
                            <span>Kategori</span>
                            <svg class="w-3.5 h-3.5 transition-transform duration-200" :class="{'rotate-180': openKategori}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </button>

                        <div x-show="openKategori"
                             x-cloak
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 translate-y-1 scale-95"
                             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                             class="absolute left-0 mt-1 w-52 bg-white rounded-xl shadow-xl py-2 z-50 border border-gray-100 divide-y divide-gray-50">
                            <a href="#katalog" @click="activeFilter = 'pakaian'; openKategori = false" class="flex items-center px-4 py-2 text-xs font-bold text-gray-700 hover:bg-red-50 hover:text-red-600">👕 Pakaian Olahraga</a>
                            <a href="#katalog" @click="activeFilter = 'sepatu'; openKategori = false" class="flex items-center px-4 py-2 text-xs font-bold text-gray-700 hover:bg-red-50 hover:text-red-600">👟 Sepatu Olahraga</a>
                            <a href="#katalog" @click="activeFilter = 'tas'; openKategori = false" class="flex items-center px-4 py-2 text-xs font-bold text-gray-700 hover:bg-red-50 hover:text-red-600">🎒 Tas & Ransel</a>
                            <a href="#katalog" @click="activeFilter = 'aksesoris'; openKategori = false" class="flex items-center px-4 py-2 text-xs font-bold text-gray-700 hover:bg-red-50 hover:text-red-600">🥊 Aksesoris</a>
                        </div>
                    </div>

                    <!-- Tombol Promo Khusus di Navbar -->
                    <a href="#promo-showcase" class="text-red-600 hover:text-red-700 font-black text-xs uppercase tracking-wider transition flex items-center gap-1.5 px-3 py-1.5 bg-red-50 rounded-full border border-red-200 animate-pulse">
                        🔥 PROMO SPESIAL
                    </a>
                </div>

                <!-- Bagian Kanan: Search, Cart, Auth (Desktop) -->
                <div class="hidden lg:flex items-center space-x-4">
                    <div class="relative w-48 xl:w-56">
                        <input type="text"
                               x-model="searchQuery"
                               @input.debounce.250ms="scrollToCatalog()"
                               aria-label="Cari produk olahraga"
                               class="w-full bg-gray-100 text-xs text-gray-900 rounded-full pl-3.5 pr-8 py-2 focus:outline-none focus:ring-2 focus:ring-red-600 border border-transparent transition placeholder-gray-400 font-medium"
                               placeholder="Cari produk olahraga...">
                        <button type="button" @click="searchQuery = ''" x-show="searchQuery.length > 0" x-cloak class="absolute right-7 top-2 text-gray-400 hover:text-red-600 text-xs font-bold" title="Hapus pencarian" aria-label="Hapus pencarian">✕</button>
                        <button type="button" @click="scrollToCatalog()" class="absolute right-2.5 top-2 text-gray-400 hover:text-red-600" aria-label="Cari">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </button>
                    </div>

                    @if (Route::has('login'))
                        @auth
                            @if(Auth::user()->role === 'admin')
                                <a href="{{ route('admin.dashboard') }}" class="bg-gray-900 hover:bg-red-600 transition text-white px-3.5 py-1.5 rounded-lg font-bold text-xs uppercase">Panel Admin</a>
                            @elseif(Auth::user()->role === 'owner' && Route::has('owner.dashboard'))
                                <a href="{{ route('owner.dashboard') }}" class="bg-gray-900 hover:bg-red-600 transition text-white px-3.5 py-1.5 rounded-lg font-bold text-xs uppercase">Owner</a>
                            @else
                                <a href="{{ route('customer.cart') }}" class="relative text-gray-700 hover:text-red-600 p-1.5 rounded-full hover:bg-gray-100" title="Keranjang Belanja" aria-label="Keranjang Belanja">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                                    <span x-show="cartCount > 0" x-cloak class="absolute top-0 right-0 bg-red-600 text-white text-[9px] font-black px-1 py-0.5 rounded-full" x-text="cartCount"></span>
                                </a>
                            @endif

                            <div x-data="{ open: false }" class="relative border-l border-gray-200 pl-3">
                                <button type="button" @click="open = !open" @click.outside="open = false" class="flex items-center gap-2 text-gray-800 font-bold focus:outline-none">
                                    @if($userAvatarUrl)
                                        <img src="{{ $userAvatarUrl }}" alt="{{ Auth::user()->name }}" class="w-7 h-7 rounded-full object-cover border border-gray-200 shrink-0">
                                    @else
                                        <div class="w-7 h-7 rounded-full bg-red-100 text-red-600 flex items-center justify-center font-black text-xs shrink-0">
                                            {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                                        </div>
                                    @endif
                                    <span class="text-xs truncate max-w-[90px]">{{ Auth::user()->name }}</span>
                                </button>
                                <div x-show="open" x-cloak class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-xl py-2 z-50 border border-gray-100">
                                    <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">Profil Saya</a>
                                    <a href="{{ route('customer.orders') }}" class="block px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">Pesanan Saya</a>
                                    <form method="POST" action="{{ route('logout') }}" @submit="localStorage.removeItem('cart_items')">
                                        @csrf
                                        <button type="submit" class="block w-full text-left px-4 py-2 text-xs text-red-600 hover:bg-red-50 font-bold">Log Out</button>
                                    </form>
                                </div>
                            </div>
                        @else
                            <a href="{{ route('customer.cart') }}" class="relative text-gray-700 hover:text-red-600 p-1.5 rounded-full hover:bg-gray-100 mr-2" title="Keranjang Belanja" aria-label="Keranjang Belanja">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                                <span x-show="cartCount > 0" x-cloak class="absolute top-0 right-0 bg-red-600 text-white text-[9px] font-black px-1 py-0.5 rounded-full" x-text="cartCount"></span>
                            </a>
                            <div class="flex items-center space-x-2 border-l border-gray-200 pl-3">
                                <a href="{{ route('login') }}" class="text-gray-700 hover:text-red-600 font-bold text-xs uppercase px-2 py-1">Log in</a>
                                <a href="{{ route('register') }}" class="bg-gray-900 hover:bg-red-600 text-white px-4 py-2 rounded-lg font-bold text-xs uppercase tracking-wider shadow-sm transition">Daftar</a>
                            </div>
                        @endauth
                    @endif
                </div>

                <!-- Bagian Mobile: Tombol Search, Keranjang & Hamburger -->
                <div class="flex items-center gap-1.5 sm:gap-2.5 lg:hidden">
                    <!-- Tombol Search Mobile -->
                    <button type="button"
                            @click="mobileSearchOpen = !mobileSearchOpen; mobileMenuOpen = false; if(mobileSearchOpen) $nextTick(() => $refs.mobileSearchInput.focus())"
                            :class="mobileSearchOpen ? 'bg-red-50 text-red-600' : 'text-gray-700 hover:bg-gray-100'"
                            class="p-2 rounded-full transition"
                            title="Cari Produk"
                            aria-label="Cari Produk">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </button>

                    <!-- Tombol Keranjang Mobile -->
                    <a href="{{ route('customer.cart') }}" class="relative text-gray-700 hover:bg-gray-100 p-2 rounded-full transition" title="Keranjang Belanja" aria-label="Keranjang Belanja">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                        <span x-show="cartCount > 0" x-cloak class="absolute top-0.5 right-0.5 bg-red-600 text-white text-[9px] font-black px-1.5 py-0.5 rounded-full" x-text="cartCount"></span>
                    </a>

                    <!-- Tombol Hamburger Menu -->
                    <button type="button"
                            @click="mobileMenuOpen = !mobileMenuOpen; mobileSearchOpen = false"
                            :aria-expanded="mobileMenuOpen.toString()"
                            aria-label="Buka menu navigasi"
                            class="text-gray-700 hover:bg-gray-100 p-2 rounded-lg transition">
                        <svg x-show="!mobileMenuOpen" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                        <svg x-show="mobileMenuOpen" x-cloak class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
            </div>

            <!-- Bar Pencarian Cepat Khusus HP -->
            <div x-show="mobileSearchOpen"
                 x-cloak
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 -translate-y-2"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 class="lg:hidden pb-3.5 pt-2 border-t border-gray-100">
                <form @submit.prevent="mobileSearchOpen = false; scrollToCatalog()" class="flex items-center gap-2">
                    <div class="relative flex-1">
                        <input type="text"
                               x-ref="mobileSearchInput"
                               x-model="searchQuery"
                               placeholder="Cari jersey, sepatu, tas..."
                               aria-label="Cari produk olahraga"
                               class="w-full bg-gray-100 text-xs text-gray-900 rounded-xl pl-3.5 pr-8 py-2.5 focus:outline-none focus:ring-2 focus:ring-red-600 font-medium">
                        <button type="button"
                                x-show="searchQuery.length > 0"
                                x-cloak
                                @click="searchQuery = ''; $refs.mobileSearchInput.focus()"
                                aria-label="Hapus pencarian"
                                class="absolute right-2.5 top-2.5 text-gray-400 hover:text-gray-700 text-xs font-bold">✕</button>
                    </div>
                    <button type="submit"
                            class="bg-red-600 hover:bg-red-700 text-white px-4 py-2.5 rounded-xl font-black text-xs uppercase tracking-wider shrink-0 flex items-center gap-1.5 shadow-sm active:scale-95 transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        <span>Cari</span>
                    </button>
                </form>
            </div>

            <!-- Mobile Dropdown Menu -->
            <div x-show="mobileMenuOpen" x-cloak class="lg:hidden pb-5 border-t border-gray-100 pt-4 space-y-4">
                <div class="flex flex-wrap gap-2 px-2">
                    <a href="#katalog" @click="activeFilter = 'semua'; mobileMenuOpen = false" class="text-[11px] font-bold px-3 py-2 rounded-lg bg-gray-100 text-gray-700">Semua</a>
                    <a href="#katalog" @click="activeFilter = 'pakaian'; mobileMenuOpen = false" class="text-[11px] font-bold px-3 py-2 rounded-lg bg-gray-100 text-gray-700">👕 Pakaian</a>
                    <a href="#katalog" @click="activeFilter = 'sepatu'; mobileMenuOpen = false" class="text-[11px] font-bold px-3 py-2 rounded-lg bg-gray-100 text-gray-700">👟 Sepatu</a>
                    <a href="#katalog" @click="activeFilter = 'tas'; mobileMenuOpen = false" class="text-[11px] font-bold px-3 py-2 rounded-lg bg-gray-100 text-gray-700">🎒 Tas</a>
                    <a href="#katalog" @click="activeFilter = 'aksesoris'; mobileMenuOpen = false" class="text-[11px] font-bold px-3 py-2 rounded-lg bg-gray-100 text-gray-700">🥊 Aksesoris</a>
                    <a href="#promo-showcase" @click="mobileMenuOpen = false" class="text-[11px] font-black px-3 py-2 rounded-lg bg-red-600 text-white">🔥 Promo</a>
                </div>

                <div class="px-2">
                    <a href="{{ route('order.track') }}" class="flex items-center justify-center gap-2 w-full py-2.5 rounded-xl bg-gray-900 hover:bg-red-600 text-white text-xs font-black uppercase tracking-wider transition">
                        📦 Lacak Status Pesanan
                    </a>
                </div>

                <div class="border-t border-gray-100 pt-4 px-2 space-y-2">
                    @if (Route::has('login'))
                        @auth
                            <div class="flex items-center gap-3 mb-4 px-2">
                                @if($userAvatarUrl)
                                    <img src="{{ $userAvatarUrl }}" alt="{{ Auth::user()->name }}" class="w-10 h-10 rounded-full object-cover border border-gray-200 shrink-0">
                                @else
                                    <div class="w-10 h-10 rounded-full bg-red-100 text-red-600 flex items-center justify-center font-black text-sm shrink-0">
                                        {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                                    </div>
                                @endif
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
                            <a href="{{ route('customer.orders') }}" class="block w-full text-center bg-gray-100 text-gray-700 font-bold text-xs uppercase tracking-widest py-3 rounded-lg mb-2">Pesanan Saya</a>

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

    <!-- =========================================================================
         HERO SECTION UTAMA (YOUR JOURNEY OUR GEAR - TETAP UTUH DI ATAS)
    ========================================================================== -->
    <div class="relative bg-gray-900 overflow-hidden">
        <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(#ef4444 1px, transparent 1px); background-size: 24px 24px;"></div>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-28 relative z-10 flex flex-col items-center text-center">
            <span class="text-red-500 font-bold tracking-widest uppercase mb-3 text-sm">New Collection 2026</span>
            <h2 class="text-4xl md:text-6xl font-black text-white tracking-tight uppercase mb-6 leading-tight">
                Your Journey <br><span class="text-transparent bg-clip-text bg-gradient-to-r from-red-500 to-red-700">Our Gear</span>
            </h2>
            <p class="text-base text-gray-400 mb-8 max-w-xl mx-auto font-medium">
                Tingkatkan performa olahragamu dengan apparel premium. Kualitas juara, desain eksklusif, dan kenyamanan maksimal untuk setiap aktivitasmu.
            </p>
            <div class="flex flex-wrap justify-center gap-4">
                <a href="#katalog" class="bg-red-600 hover:bg-red-700 text-white font-bold px-7 py-3.5 rounded-md uppercase tracking-wider transition shadow-lg">
                    Lihat Katalog
                </a>
                <a href="#promo-showcase" @click="activeFilter = 'promo'" class="bg-white/10 hover:bg-white/20 text-white border border-white/20 font-bold px-7 py-3.5 rounded-md uppercase tracking-wider transition backdrop-blur-sm">
                    ⚡ Lihat Diskon & Promo
                </a>
            </div>
        </div>
    </div>

    <!-- =========================================================================
         SECTION PROMO ELEGAN (CENTER-PEEK ROUNDED CAROUSEL)
    ========================================================================== -->
    @if(count($promoSlidesData) > 0)
        <section id="promo-showcase"
                 class="relative pt-12 pb-4 overflow-hidden select-none"
                 x-data="welcomePromoCarousel()"
                 @mouseenter="paused = true"
                 @mouseleave="paused = false"
                 @touchstart.passive="handleTouchStart($event)"
                 @touchend.passive="handleTouchEnd($event)">

            <!-- Header Kecil Elegan di Atas Carousel -->
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-5 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <span class="w-2 h-2 rounded-full bg-red-600 animate-pulse"></span>
                    <span class="text-xs font-black uppercase tracking-[0.22em] text-gray-900">Promo & Highlight</span>
                </div>

                <div x-show="total > 1" x-cloak class="flex items-center gap-2">
                    <span class="text-[11px] font-bold text-gray-400 mr-1 hidden sm:inline">
                        <span class="text-gray-900 font-black" x-text="String(current + 1).padStart(2, '0')"></span>
                        /
                        <span x-text="String(total).padStart(2, '0')"></span>
                    </span>
                    <button type="button" @click="prev()" aria-label="Slide sebelumnya"
                            class="w-9 h-9 rounded-full bg-white hover:bg-gray-900 text-gray-700 hover:text-white border border-gray-200 shadow-sm flex items-center justify-center transition active:scale-95">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <button type="button" @click="next()" aria-label="Slide berikutnya"
                            class="w-9 h-9 rounded-full bg-white hover:bg-red-600 text-gray-700 hover:text-white border border-gray-200 hover:border-red-600 shadow-sm flex items-center justify-center transition active:scale-95">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>
            </div>

            <!-- Track Carousel: Peek Kiri + Slide Utama Tengah Rounded + Peek Kanan -->
            <div class="relative w-full flex items-center justify-center gap-4 sm:gap-6 px-4 sm:px-0">

                <!-- PEEK SLIDE KIRI -->
                <template x-if="total > 1">
                    <div @click="prev()"
                         title="Lihat promo sebelumnya"
                         class="hidden xl:block w-[11%] shrink-0 h-[360px] lg:h-[420px] rounded-r-3xl overflow-hidden relative cursor-pointer group bg-gray-900 shadow-md opacity-75 hover:opacity-95 transition-all duration-500">
                        <template x-if="slides[prevIndex].mediaType === 'video'">
                            <video :src="slides[prevIndex].mediaUrl" muted loop playsinline class="absolute inset-0 w-full h-full object-cover object-center scale-105 group-hover:scale-110 transition-transform duration-700"></video>
                        </template>
                        <template x-if="slides[prevIndex].mediaType !== 'video'">
                            <img :src="slides[prevIndex].mediaUrl" :alt="slides[prevIndex].title" class="absolute inset-0 w-full h-full object-cover object-center scale-105 group-hover:scale-110 transition-transform duration-700">
                        </template>
                        <div class="absolute inset-0 bg-gray-950/60 group-hover:bg-gray-950/40 transition-colors"></div>
                    </div>
                </template>

                <!-- SLIDE UTAMA TENGAH -->
                <div class="w-full max-w-6xl xl:w-[74%] shrink-0 h-[340px] sm:h-[400px] lg:h-[440px] rounded-3xl overflow-hidden relative bg-gray-950 shadow-[0_22px_50px_-15px_rgba(15,23,42,0.35)] border border-gray-200/80 group">

                    <template x-for="(slide, idx) in slides" :key="slide.id + '-' + idx">
                        <div x-show="current === idx"
                             x-transition:enter="transition ease-out duration-500"
                             x-transition:enter-start="opacity-0 scale-[1.02]"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-300"
                             x-transition:leave-start="opacity-100"
                             x-transition:leave-end="opacity-0"
                             class="absolute inset-0 w-full h-full">

                            <!-- LAYER 0: Media Background -->
                            <template x-if="slide.mediaType === 'video'">
                                <video :src="slide.mediaUrl"
                                       autoplay
                                       muted
                                       loop
                                       playsinline
                                       class="absolute inset-0 z-0 w-full h-full object-cover object-center"></video>
                            </template>

                            <template x-if="slide.mediaType !== 'video'">
                                <img :src="slide.mediaUrl"
                                     :alt="slide.title"
                                     class="absolute inset-0 z-0 w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-[1.8s] ease-out">
                            </template>

                            <!-- LAYER 10: Overlay Gelap Elegan agar Teks Putih Kontras -->
                            <div class="absolute inset-0 z-10 bg-gradient-to-t from-black/85 via-black/55 to-black/45"></div>

                            <!-- LAYER 20: Konten Tipografi Tengah -->
                            <div class="absolute inset-0 z-20 flex flex-col items-center justify-center text-center px-8 sm:px-16 lg:px-24">

                                <!-- Label Badge Merah -->
                                <span class="inline-flex items-center gap-1.5 text-[10px] sm:text-[11px] font-black uppercase tracking-[0.26em] text-red-400 bg-black/50 border border-white/15 backdrop-blur-md px-3.5 py-1 rounded-full mb-3"
                                      x-text="slide.badge"></span>

                                <!-- Judul Utama Promo -->
                                <h3 class="text-2xl sm:text-4xl lg:text-[42px] font-black text-white tracking-tight leading-[1.15] max-w-3xl drop-shadow-[0_6px_24px_rgba(0,0,0,0.8)]"
                                    x-text="slide.title"></h3>

                                <!-- Deskripsi Promo -->
                                <p x-show="slide.desc && slide.desc.trim() !== ''"
                                   class="text-xs sm:text-sm text-gray-200 font-medium max-w-xl mt-2.5 line-clamp-2 leading-relaxed drop-shadow"
                                   x-text="slide.desc"></p>

                                <!-- Subjudul Kecil -->
                                <div class="mt-3 sm:mt-4 flex items-center justify-center gap-2 text-[10px] sm:text-[11px] font-bold uppercase tracking-[0.2em] text-gray-300">
                                    <span>SPORTMANIA</span>
                                    <span class="text-red-500">•</span>
                                    <span x-text="slide.subtitle"></span>
                                </div>

                                <!-- Tombol CTA -->
                                <div class="mt-5 sm:mt-6">
                                    <a :href="slide.link"
                                       @click="if(slide.link === '#katalog' || slide.link === '/katalog') { $event.preventDefault(); activeFilter = 'promo'; scrollToCatalog(); }"
                                       class="inline-flex items-center gap-2 bg-white hover:bg-red-600 text-gray-950 hover:text-white font-black text-[10px] sm:text-[11px] uppercase tracking-[0.18em] px-6 py-2.5 sm:px-7 sm:py-3 rounded-lg shadow-xl transition-all duration-300 hover:scale-105 active:scale-95">
                                        <span x-text="slide.btnText"></span>
                                    </a>
                                </div>
                            </div>

                            <!-- LAYER 30: Caption Pojok Kiri Bawah -->
                            <div x-show="slide.caption && slide.caption.trim() !== ''"
                                 class="hidden sm:block absolute bottom-4 left-6 z-30 text-[10px] font-semibold tracking-wider text-white/80 bg-black/45 backdrop-blur-sm px-3 py-1 rounded-full border border-white/10"
                                 x-text="slide.caption"></div>
                        </div>
                    </template>

                    <!-- Tombol Panah Kiri & Kanan -->
                    <template x-if="total > 1">
                        <div>
                            <button type="button" @click="prev()" aria-label="Promo sebelumnya"
                                    class="absolute left-3 sm:left-5 top-1/2 -translate-y-1/2 z-30 w-9 h-9 sm:w-10 sm:h-10 rounded-full bg-black/45 hover:bg-red-600 text-white border border-white/25 backdrop-blur-md flex items-center justify-center transition-all duration-300">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
                            </button>
                            <button type="button" @click="next()" aria-label="Promo berikutnya"
                                    class="absolute right-3 sm:right-5 top-1/2 -translate-y-1/2 z-30 w-9 h-9 sm:w-10 sm:h-10 rounded-full bg-black/45 hover:bg-red-600 text-white border border-white/25 backdrop-blur-md flex items-center justify-center transition-all duration-300">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                            </button>
                        </div>
                    </template>

                    <!-- Indikator Dots -->
                    <template x-if="total > 1">
                        <div class="absolute bottom-4 inset-x-0 z-30 flex items-center justify-center gap-1.5">
                            <template x-for="(s, i) in slides" :key="i">
                                <button type="button" @click="goTo(i)" :aria-label="'Pilih promo ' + (i + 1)"
                                        class="h-1.5 rounded-full transition-all duration-300"
                                        :class="current === i ? 'w-7 bg-red-500' : 'w-1.5 bg-white/45 hover:bg-white/85'"></button>
                            </template>
                        </div>
                    </template>
                </div>

                <!-- PEEK SLIDE KANAN -->
                <template x-if="total > 1">
                    <div @click="next()"
                         title="Lihat promo berikutnya"
                         class="hidden xl:block w-[11%] shrink-0 h-[360px] lg:h-[420px] rounded-l-3xl overflow-hidden relative cursor-pointer group bg-gray-900 shadow-md opacity-75 hover:opacity-95 transition-all duration-500">
                        <template x-if="slides[nextIndex].mediaType === 'video'">
                            <video :src="slides[nextIndex].mediaUrl" muted loop playsinline class="absolute inset-0 w-full h-full object-cover object-center scale-105 group-hover:scale-110 transition-transform duration-700"></video>
                        </template>
                        <template x-if="slides[nextIndex].mediaType !== 'video'">
                            <img :src="slides[nextIndex].mediaUrl" :alt="slides[nextIndex].title" class="absolute inset-0 w-full h-full object-cover object-center scale-105 group-hover:scale-110 transition-transform duration-700">
                        </template>
                        <div class="absolute inset-0 bg-gray-950/60 group-hover:bg-gray-950/40 transition-colors"></div>
                    </div>
                </template>

            </div>
        </section>
    @endif

    <!-- Section Katalog Utama & Fitur Filter Produk -->
    <main id="katalog" class="max-w-7xl mx-auto py-16 px-4 sm:px-6 lg:px-8 relative z-20">
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-end mb-6 gap-4">
            <div>
                <h3 class="text-3xl font-black text-gray-900 uppercase tracking-tight">Katalog <span class="text-red-600 italic">Terbaru</span></h3>
                <p class="text-gray-500 mt-1 font-medium text-sm">Temukan koleksi apparel original terbaik pilihan kami.</p>
            </div>

            <div class="flex flex-wrap items-center gap-3 w-full lg:w-auto justify-between lg:justify-end">
                <!-- Filter Kategori -->
                <div class="flex flex-wrap gap-1.5 bg-white p-1.5 rounded-xl border border-gray-200 shadow-sm">
                    <button type="button" @click="activeFilter = 'semua'" :class="activeFilter === 'semua' ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100'" class="px-3.5 py-2 rounded-lg text-xs font-bold uppercase transition">Semua</button>
                    <button type="button" @click="activeFilter = 'pakaian'" :class="activeFilter === 'pakaian' ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100'" class="px-3.5 py-2 rounded-lg text-xs font-bold uppercase transition">Pakaian</button>
                    <button type="button" @click="activeFilter = 'sepatu'" :class="activeFilter === 'sepatu' ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100'" class="px-3.5 py-2 rounded-lg text-xs font-bold uppercase transition">Sepatu</button>
                    <button type="button" @click="activeFilter = 'tas'" :class="activeFilter === 'tas' ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100'" class="px-3.5 py-2 rounded-lg text-xs font-bold uppercase transition">Tas</button>
                    <button type="button" @click="activeFilter = 'aksesoris'" :class="activeFilter === 'aksesoris' ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100'" class="px-3.5 py-2 rounded-lg text-xs font-bold uppercase transition">Aksesoris</button>

                    <button type="button" @click="activeFilter = 'promo'" :class="activeFilter === 'promo' ? 'bg-red-600 text-white shadow-md' : 'bg-red-50 text-red-600 hover:bg-red-100 border border-red-200'" class="px-3.5 py-2 rounded-lg text-xs font-black uppercase tracking-wider transition flex items-center gap-1">
                        🔥 PROMO
                    </button>
                </div>

                <!-- Dropdown Urutkan -->
                <div class="bg-white px-3 py-1.5 rounded-xl border border-gray-200 shadow-sm flex items-center gap-2">
                    <label for="sort-products" class="text-[10px] font-black uppercase tracking-wider text-gray-400">Urut:</label>
                    <select id="sort-products" x-model="sortBy" class="text-xs font-bold text-gray-800 bg-transparent border-none focus:ring-0 py-1 pr-6 cursor-pointer">
                        <option value="terbaru">Terbaru</option>
                        <option value="terlaris">Terlaris</option>
                        <option value="rating">Rating Tertinggi</option>
                        <option value="harga_asc">Harga Terendah</option>
                        <option value="harga_desc">Harga Tertinggi</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Bar Indikator Filter / Pencarian Aktif -->
        <div x-show="activeFilter !== 'semua' || searchQuery.trim() !== ''" x-cloak
             class="mb-6 flex flex-wrap items-center justify-between gap-3 bg-white px-4 py-3 rounded-2xl border border-gray-200 text-xs">
            <div class="flex flex-wrap items-center gap-2 text-gray-600 font-medium">
                <span>Filter aktif:</span>
                <span x-show="activeFilter !== 'semua'" class="bg-gray-900 text-white font-bold uppercase px-2.5 py-1 rounded-lg text-[10px]" x-text="'Kategori: ' + activeFilter"></span>
                <span x-show="searchQuery.trim() !== ''" class="bg-red-50 text-red-600 border border-red-200 font-bold px-2.5 py-1 rounded-lg text-[11px]" x-text="'Kata kunci: ' + searchQuery"></span>
                <span class="text-gray-400 text-[11px]" x-text="'(' + visibleProductsCount + ' produk)'"></span>
            </div>
            <button type="button" @click="resetFilters()" class="text-red-600 hover:text-red-700 font-black uppercase tracking-wider text-[11px]">
                ✕ Reset Filter
            </button>
        </div>

        <!-- Grid Produk -->
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-6 mt-6 sm:mt-8">
            @forelse($products as $product)
            @php
                $catSlug = strtolower(optional($product->category)->slug ?? 'lainnya');
                $catName = optional($product->category)->name ?? 'Sport Apparel';
                $prodNameLower = strtolower($product->name . ' ' . ($product->description ?? '') . ' ' . $catName);
                $hasDiscount = $product->discount_price !== null && (float) $product->discount_price > 0;
                $isPromoOrFeatured = ($hasDiscount || $product->is_featured) ? 'true' : 'false';

                $stat = $reviewStatsMap[$product->id] ?? null;
                $reviewCount = isset($product->reviews_count)
                    ? (int) $product->reviews_count
                    : ($product->relationLoaded('reviews') ? $product->reviews->count() : (int) ($stat->total_reviews ?? 0));

                $avgRating = $reviewCount > 0
                    ? round((float) ($product->reviews_avg_rating ?? ($product->relationLoaded('reviews') ? $product->reviews->avg('rating') : ($stat->avg_rating ?? 0))), 1)
                    : 0;

                $soldCount = isset($product->sold_count)
                    ? (int) $product->sold_count
                    : (int) ($soldCountsMap[$product->id] ?? 0);

                $hasVariants = $product->variants && $product->variants->count() > 0;
                $totalStock = $hasVariants ? $product->variants->sum('stock') : ($product->stock ?? 10);
                $imageUrl = $product->image
                    ? (filter_var($product->image, FILTER_VALIDATE_URL) ? $product->image : asset('storage/' . $product->image))
                    : asset('image/LOGO.jpeg');

                $quickViewPayload = [
                    'id' => $product->id,
                    'name' => $product->name,
                    'category' => $catName,
                    'description' => $product->description,
                    'price' => (float) $product->price,
                    'discount_price' => $hasDiscount ? (float) $product->discount_price : null,
                    'image' => $imageUrl,
                    'stock' => (int) $totalStock,
                    'avg_rating' => (float) $avgRating,
                    'review_count' => (int) $reviewCount,
                    'sold_count' => (int) $soldCount,
                    'variants' => $hasVariants ? $product->variants->map(fn($v) => [
                        'id' => $v->id,
                        'size' => $v->size ?: 'All Size',
                        'color' => $v->color ?: '-',
                        'stock' => (int) ($v->stock ?? 0),
                    ])->values()->toArray() : [],
                ];
            @endphp

            <div data-cat="{{ $catSlug }}"
                 data-search="{{ $prodNameLower }}"
                 :style="'order: ' + getProductOrder({{ $product->id }})"
                 x-show="matchesCategoryFilter($el.dataset.cat, $el.dataset.search, {{ $isPromoOrFeatured }}) && ($el.dataset.search.includes(searchQuery.toLowerCase().trim()))"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 class="bg-white rounded-2xl sm:rounded-3xl p-3 sm:p-4 shadow-sm hover:shadow-[0_20px_40px_-15px_rgba(220,38,38,0.15)] transition-all duration-300 group relative overflow-hidden border-2 border-transparent hover:border-red-100 flex flex-col justify-between h-full">

                <div>
                    <span class="hidden sm:block absolute top-4 left-4 z-10 bg-white/90 backdrop-blur text-gray-900 text-[10px] font-black px-3 py-1.5 rounded-lg uppercase tracking-widest shadow-sm pointer-events-none">
                        {{ $catName }}
                    </span>

                    @if($totalStock <= 0)
                        <span class="absolute top-3 right-3 sm:top-4 sm:right-4 z-10 bg-gray-900 text-white text-[9px] sm:text-[10px] font-black px-2 py-1 sm:px-3 sm:py-1.5 rounded-lg uppercase tracking-wider shadow-md pointer-events-none">
                            STOK HABIS
                        </span>
                    @elseif($hasDiscount)
                        <span class="absolute top-3 right-3 sm:top-4 sm:right-4 z-10 bg-red-600 text-white text-[9px] sm:text-[10px] font-black px-2 py-1 sm:px-3 sm:py-1.5 rounded-lg uppercase tracking-wider shadow-md animate-pulse pointer-events-none">
                            ⚡ SALE
                        </span>
                    @endif

                    <div class="w-full aspect-[4/5] bg-gray-50 rounded-xl sm:rounded-2xl mb-3 sm:mb-4 flex items-center justify-center overflow-hidden relative">
                        <a href="{{ route('produk.show', $product->id) }}" class="block w-full h-full">
                            <img src="{{ $imageUrl }}" alt="{{ $product->name }}" loading="lazy" class="w-full h-full object-cover mix-blend-multiply group-hover:scale-110 transition-transform duration-500 {{ $totalStock <= 0 ? 'opacity-50 grayscale' : '' }}">
                        </a>
                        <button type="button"
                                @click.stop='openQuickView(@json($quickViewPayload))'
                                title="Pilih Cepat"
                                aria-label="Pilih cepat {{ $product->name }}"
                                class="flex items-center gap-1.5 absolute bottom-2.5 right-2.5 sm:right-auto sm:bottom-3 sm:left-1/2 sm:-translate-x-1/2 bg-gray-900/90 hover:bg-red-600 text-white text-[10px] font-black uppercase tracking-wider p-2 sm:px-3.5 sm:py-2 rounded-xl sm:opacity-0 sm:translate-y-2 group-hover:opacity-100 group-hover:translate-y-0 transition-all duration-300 shadow-lg backdrop-blur-sm whitespace-nowrap">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                            <span class="hidden sm:inline">Pilih Cepat</span>
                        </button>
                    </div>

                    <a href="{{ route('produk.show', $product->id) }}" class="block space-y-1.5 mt-1 sm:mt-2">
                        <h3 class="font-bold text-gray-900 group-hover:text-red-600 transition text-xs sm:text-sm truncate">{{ $product->name }}</h3>
                        <p class="hidden sm:block text-[11px] text-gray-400 line-clamp-1 font-medium">{{ $product->description }}</p>

                        <div class="flex flex-wrap items-center gap-1.5 pt-0.5 text-[10px] sm:text-[11px]">
                            <span class="inline-flex items-center gap-1">
                                <svg class="w-3.5 h-3.5 {{ $reviewCount > 0 ? 'text-amber-400 fill-amber-400' : 'text-gray-300 fill-gray-300' }}" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                </svg>
                                @if($reviewCount > 0)
                                    <span class="font-extrabold text-gray-800">{{ number_format($avgRating, 1) }}</span>
                                    <span class="text-gray-400 font-medium">({{ $reviewCount }})</span>
                                @else
                                    <span class="text-gray-400 font-medium">Baru</span>
                                @endif
                            </span>

                            <span class="text-gray-300">•</span>

                            <span class="font-semibold {{ $soldCount > 0 ? 'text-gray-600' : 'text-gray-400' }}">
                                {{ $soldCount > 0 ? number_format($soldCount, 0, ',', '.') . ' Terjual' : '0 Terjual' }}
                            </span>
                        </div>
                    </a>
                </div>

                <a href="{{ route('produk.show', $product->id) }}" class="flex items-center justify-between mt-2 sm:mt-4 pt-2 sm:pt-3 border-t border-gray-100">
                    <div>
                        @if($hasDiscount)
                            <p class="text-[9px] sm:text-[11px] text-gray-400 line-through">Rp {{ number_format($product->price, 0, ',', '.') }}</p>
                            <p class="font-black text-red-600 text-sm sm:text-base">Rp {{ number_format($product->discount_price, 0, ',', '.') }}</p>
                        @else
                            <p class="font-black text-gray-900 text-sm sm:text-base">Rp {{ number_format($product->price, 0, ',', '.') }}</p>
                        @endif
                    </div>
                    <div class="w-7 h-7 sm:w-10 sm:h-10 rounded-full bg-red-50 text-red-600 flex items-center justify-center sm:opacity-0 group-hover:opacity-100 sm:translate-x-2 group-hover:translate-x-0 transition-all duration-300">
                        <svg class="w-3 h-3 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </div>
                </a>
            </div>

            @empty
            <div class="col-span-full py-16 text-center bg-white rounded-3xl border border-gray-100">
                <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                </div>
                <h4 class="text-xl font-black text-gray-900 mb-2">Produk Belum Tersedia</h4>
                <p class="text-gray-500 font-medium">Kami sedang menyiapkan koleksi terbaik untuk Anda.</p>
            </div>
            @endforelse

            @if($products->isNotEmpty())
                <div x-show="visibleProductsCount === 0" x-cloak style="order: 99999;" class="col-span-full py-14 text-center bg-white rounded-3xl border border-gray-200/80 shadow-sm">
                    <div class="w-16 h-16 bg-red-50 text-red-600 rounded-2xl flex items-center justify-center mx-auto mb-4 text-2xl">🔍</div>
                    <h4 class="text-lg font-black text-gray-900 mb-1">Produk Tidak Ditemukan</h4>
                    <p class="text-xs text-gray-500 font-medium max-w-md mx-auto mb-5">Tidak ada produk yang cocok dengan kategori atau kata kunci pencarian saat ini.</p>
                    <button type="button" @click="resetFilters()" class="bg-gray-900 hover:bg-red-600 text-white text-xs font-bold uppercase tracking-wider px-6 py-3 rounded-xl transition">
                        Tampilkan Semua Produk
                    </button>
                </div>
            @endif
        </div>
    </main>

    <!-- Modal Detail Produk & Pilih Varian (Interaktif) -->
    <div x-show="selectedProduct !== null"
         x-cloak
         role="dialog"
         aria-modal="true"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm"
         @keydown.escape.window="selectedProduct = null">
        <div @click.outside="selectedProduct = null"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             class="bg-white rounded-2xl max-w-lg w-full overflow-hidden shadow-2xl border border-gray-100">

            <template x-if="selectedProduct">
                <div>
                    <div class="bg-gray-900 px-6 py-4 flex justify-between items-center">
                        <span class="text-xs font-black uppercase tracking-widest text-red-500" x-text="selectedProduct.category"></span>
                        <button type="button" @click="selectedProduct = null" class="text-gray-400 hover:text-white text-sm font-bold">✕ Tutup</button>
                    </div>

                    <div class="p-6 space-y-5">
                        <div class="flex gap-4 items-center">
                            <div class="w-20 h-20 sm:w-28 sm:h-28 rounded-xl bg-gray-50 flex items-center justify-center overflow-hidden shrink-0 border border-gray-200">
                                <template x-if="selectedProduct.image">
                                    <img :src="selectedProduct.image" :alt="selectedProduct.name" class="w-full h-full object-cover mix-blend-multiply">
                                </template>
                                <template x-if="!selectedProduct.image">
                                    <span class="text-4xl text-gray-200 font-black">SM</span>
                                </template>
                            </div>
                            <div class="min-w-0 flex-1">
                                <h3 class="text-xl font-black text-gray-900 truncate" x-text="selectedProduct.name"></h3>

                                <div class="flex items-center gap-2 mt-1 text-xs">
                                    <span class="inline-flex items-center gap-1 font-bold text-amber-500">
                                        <span>★</span>
                                        <span class="text-gray-900" x-text="selectedProduct.review_count > 0 ? Number(selectedProduct.avg_rating).toFixed(1) : '0.0'"></span>
                                        <span class="text-gray-400 font-medium" x-text="'(' + selectedProduct.review_count + ' ulasan)'"></span>
                                    </span>
                                    <span class="text-gray-300">•</span>
                                    <span class="text-gray-600 font-semibold" x-text="selectedProduct.sold_count + ' Terjual'"></span>
                                </div>

                                <p class="text-xs text-gray-500 mt-1 line-clamp-2" x-text="selectedProduct.description"></p>
                                <div class="mt-2">
                                    <template x-if="selectedProduct.discount_price">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs text-gray-400 line-through" x-text="'Rp ' + Number(selectedProduct.price).toLocaleString('id-ID')"></span>
                                            <span class="text-lg font-black text-red-600" x-text="'Rp ' + Number(selectedProduct.discount_price).toLocaleString('id-ID')"></span>
                                        </div>
                                    </template>
                                    <template x-if="!selectedProduct.discount_price">
                                        <span class="text-lg font-black text-red-600" x-text="'Rp ' + Number(selectedProduct.price).toLocaleString('id-ID')"></span>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <div x-show="selectedProduct.variants && selectedProduct.variants.length > 0">
                            <label class="block text-xs font-bold uppercase text-gray-700 mb-2">Pilih Varian (Ukuran & Warna):</label>
                            <div class="grid grid-cols-2 gap-2 max-h-40 overflow-y-auto pr-1">
                                <template x-for="(v, idx) in selectedProduct.variants" :key="v.id ?? idx">
                                    <button type="button"
                                            @click="selectVariant(idx)"
                                            :disabled="v.stock == 0"
                                            :class="selectedVariantIndex === idx ? 'border-red-600 bg-red-50 text-red-700' : (v.stock == 0 ? 'border-gray-100 bg-gray-50 text-gray-400 cursor-not-allowed' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50')"
                                            class="p-2.5 rounded-xl border text-left transition flex justify-between items-center">
                                        <div>
                                            <p class="text-xs font-bold" x-text="(v.size || 'All Size') + ' - ' + (v.color || '-')"></p>
                                            <p class="text-[10px]" :class="v.stock == 0 ? 'text-red-500 font-bold' : 'text-gray-500'" x-text="v.stock == 0 ? 'Stok Habis' : 'Stok: ' + v.stock"></p>
                                        </div>
                                        <span x-show="selectedVariantIndex === idx && v.stock > 0" class="text-red-600 font-black text-xs">✓</span>
                                    </button>
                                </template>
                            </div>
                        </div>

                        <div class="flex items-center justify-between bg-gray-50 p-3 rounded-xl border border-gray-200">
                            <div>
                                <span class="text-xs font-bold text-gray-700 block">Jumlah Pembelian</span>
                                <span class="text-[10px] text-gray-400 font-medium" x-text="'Maksimal: ' + maxAvailableStock + ' pcs'"></span>
                            </div>
                            <div class="flex items-center gap-3">
                                <button type="button" @click="if(qty > 1) qty--" :disabled="maxAvailableStock <= 0 || qty <= 1" class="w-7 h-7 rounded-lg bg-white border border-gray-300 font-bold text-sm hover:bg-gray-100 disabled:opacity-40">-</button>
                                <span class="font-black text-sm w-6 text-center" x-text="qty"></span>
                                <button type="button" @click="if(qty < maxAvailableStock) qty++; else showToast('Jumlah mencapai batas stok tersedia.', 'error');" :disabled="maxAvailableStock <= 0" class="w-7 h-7 rounded-lg bg-white border border-gray-300 font-bold text-sm hover:bg-gray-100 disabled:opacity-40">+</button>
                            </div>
                        </div>

                        <div class="flex flex-col sm:flex-row gap-3 pt-2">
                            <a :href="'/produk/' + selectedProduct.id" class="flex-1 bg-white border border-gray-300 hover:border-gray-900 text-gray-800 text-center py-3 rounded-xl font-bold text-[11px] sm:text-xs uppercase tracking-wider transition flex items-center justify-center">
                                Detail Penuh
                            </a>

                            @auth
                                <a :href="'https://wa.me/6282234548092?text=' + encodeURIComponent('Halo Admin Sport Mania Distro, saya ingin memesan ' + selectedProduct.name + (activeVariant ? ' (Varian: ' + (activeVariant.size || 'All Size') + ' - ' + (activeVariant.color || '-') + ')' : '') + ' sebanyak ' + qty + ' pcs.')"
                                   target="_blank"
                                   rel="noopener noreferrer"
                                   class="flex-1 bg-green-600 hover:bg-green-700 text-white text-center py-3 rounded-xl font-bold text-[11px] sm:text-xs uppercase tracking-wider transition shadow-md flex items-center justify-center gap-1.5">
                                    Pesan via WA
                                </a>
                                <button type="button"
                                        @click="addToCart()"
                                        :disabled="maxAvailableStock <= 0"
                                        class="flex-1 bg-gray-900 hover:bg-red-600 disabled:bg-gray-300 text-white text-center py-3 rounded-xl font-bold text-[11px] sm:text-xs uppercase tracking-wider transition shadow-md">
                                    + Keranjang
                                </button>
                            @else
                                <a href="{{ route('login') }}" class="flex-[2] bg-red-50 border border-red-200 text-red-600 hover:bg-red-600 hover:text-white text-center py-3 rounded-xl font-bold text-[11px] sm:text-xs uppercase tracking-wider transition shadow-sm flex items-center justify-center">
                                    Login untuk Membeli
                                </a>
                            @endauth
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- Section 3D Brand Showcase / Partners (Dinamis dari Admin + Support Logo Gambar) -->
    <section class="py-24 bg-gray-950 text-white overflow-hidden relative border-t border-gray-900">
        <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[800px] h-[350px] bg-red-600/10 blur-[140px] pointer-events-none rounded-full"></div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 text-center mb-14">
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-red-500/10 border border-red-500/20 text-red-500 text-xs font-black uppercase tracking-widest mb-4">
                <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></span>
                Official Partners & Brands
            </div>
            <h3 class="text-3xl sm:text-5xl font-black uppercase tracking-tight text-white">
                Dipercaya oleh <span class="text-transparent bg-clip-text bg-gradient-to-r from-red-500 to-red-600">Brand Olahraga Terbaik</span>
            </h3>
            <p class="text-gray-400 text-xs sm:text-sm mt-3 max-w-xl mx-auto font-medium">
                Koleksi original dan eksklusif yang dirancang khusus untuk mendukung performa maksimal atlet dan pecinta sport fashion.
            </p>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 sm:gap-6">
                @foreach($brandsList as $brand)
                    @php
                        $brandLogoUrl = $resolveHomeMedia($brand['logo'] ?? '', '');
                    @endphp
                    <div class="bg-gradient-to-b from-gray-900 to-gray-900/50 backdrop-blur-xl border border-gray-800/80 p-6 sm:p-8 rounded-3xl text-center transform hover:-translate-y-2 hover:scale-[1.02] transition-all duration-300 shadow-2xl hover:shadow-red-600/10 group relative overflow-hidden">
                        <div class="absolute inset-x-0 top-0 h-[2px] bg-gradient-to-r from-transparent via-red-500 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>

                        @if(filled($brandLogoUrl))
                            <div class="w-16 h-16 sm:w-20 sm:h-20 mx-auto mb-4 bg-white rounded-2xl flex items-center justify-center p-2.5 sm:p-3 border border-gray-200 group-hover:border-red-500/60 transition-colors shadow-inner overflow-hidden">
                                <img src="{{ $brandLogoUrl }}"
                                     alt="{{ $brand['name'] ?? 'Brand Partner' }}"
                                     loading="lazy"
                                     class="max-w-full max-h-full object-contain group-hover:scale-105 transition-transform duration-300">
                            </div>
                        @else
                            <div class="w-16 h-16 sm:w-20 sm:h-20 mx-auto mb-4 bg-gray-950 rounded-2xl flex items-center justify-center border border-gray-800 group-hover:border-red-500/50 transition-colors shadow-inner">
                                <span class="text-lg sm:text-xl font-black text-red-500 italic tracking-tighter truncate px-2">{{ $brand['code'] ?? 'SM' }}</span>
                            </div>
                        @endif

                        <h4 class="font-black text-xs sm:text-sm uppercase tracking-wider text-white">{{ $brand['name'] ?? '' }}</h4>
                        <p class="text-[10px] sm:text-xs text-gray-400 mt-1 font-medium">{{ $brand['subtitle'] ?? ($brand['description'] ?? '') }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- Section Full-Screen Lookbook Carousel (Dinamis dari Admin) -->
    <section class="relative w-full h-[85vh] sm:h-[90vh] bg-gray-950 text-white overflow-hidden flex items-center justify-center"
             x-data="welcomeLookbookCarousel()"
             @mouseenter="paused = true"
             @mouseleave="paused = false">

        <template x-for="(slide, index) in slides" :key="index">
            <div x-show="activeSlide === index"
                 x-transition:enter="transition ease-out duration-1000"
                 x-transition:enter-start="opacity-0 scale-105"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-700 absolute inset-0"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 class="absolute inset-0 w-full h-full">

                <img :src="slide.bgImage" :alt="slide.title" class="absolute inset-0 w-full h-full object-cover object-center filter brightness-[0.55] contrast-125">
                <div class="absolute inset-0 bg-gradient-to-r from-black/90 via-black/50 to-transparent"></div>
                <div class="absolute inset-0 bg-gradient-to-t from-gray-950 via-transparent to-black/40"></div>

                <div class="absolute inset-0 flex items-center justify-center pointer-events-none overflow-hidden select-none">
                    <span class="text-[18vw] sm:text-[15vw] font-black tracking-tighter text-white/[0.04] uppercase italic whitespace-nowrap leading-none" x-text="slide.title"></span>
                </div>

                <div class="absolute inset-0 flex items-center">
                    <div class="max-w-7xl mx-auto px-6 sm:px-8 lg:px-12 w-full">
                        <div class="max-w-2xl space-y-6">
                            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-red-600/20 border border-red-500/40 text-red-400 text-xs font-black uppercase tracking-widest backdrop-blur-md">
                                <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></span>
                                <span x-text="slide.tag"></span>
                            </div>

                            <div class="space-y-2">
                                <span class="block text-xs sm:text-sm font-bold tracking-[0.3em] text-gray-300 uppercase" x-text="slide.subtitle"></span>
                                <h2 class="text-4xl sm:text-7xl font-black text-white uppercase tracking-tight italic drop-shadow-2xl" x-text="slide.title"></h2>
                            </div>

                            <p class="text-gray-300 text-sm sm:text-base font-medium leading-relaxed max-w-lg drop-shadow-md" x-text="slide.desc"></p>

                            <div class="pt-4 flex items-center gap-4">
                                <a :href="slide.ctaUrl || '#katalog'"
                                   @click="if((slide.ctaUrl || '#katalog') === '#katalog') { $event.preventDefault(); scrollToCatalog(); }"
                                   class="bg-red-600 hover:bg-red-700 text-white font-bold text-xs uppercase tracking-widest px-8 py-4 rounded-xl transition-all duration-300 shadow-xl shadow-red-600/40 hover:scale-105 active:scale-95 flex items-center gap-2">
                                    <span x-text="slide.ctaText || 'Jelajahi Koleksi'"></span>
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <div class="absolute bottom-8 right-6 sm:right-12 z-30 flex items-center gap-3">
            <button type="button" @click="prev()" class="w-12 h-12 sm:w-14 sm:h-14 rounded-2xl bg-black/40 hover:bg-red-600 border border-white/20 hover:border-red-600 backdrop-blur-md flex items-center justify-center text-white transition-all duration-300 shadow-2xl active:scale-95 group" title="Sebelumnya" aria-label="Slide sebelumnya">
                <svg class="w-6 h-6 group-hover:-translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"></path></svg>
            </button>
            <button type="button" @click="next()" class="w-12 h-12 sm:w-14 sm:h-14 rounded-2xl bg-black/40 hover:bg-red-600 border border-white/20 hover:border-red-600 backdrop-blur-md flex items-center justify-center text-white transition-all duration-300 shadow-2xl active:scale-95 group" title="Berikutnya" aria-label="Slide berikutnya">
                <svg class="w-6 h-6 group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
            </button>
        </div>

        <div class="absolute bottom-0 inset-x-0 z-30 flex gap-1.5 p-2 bg-gradient-to-t from-gray-950 to-transparent">
            <template x-for="(slide, index) in slides" :key="index">
                <button type="button" @click="activeSlide = index"
                        :aria-label="'Pilih slide ' + (index + 1)"
                        class="h-1 flex-1 rounded-full transition-all duration-500"
                        :class="activeSlide === index ? 'bg-red-600' : 'bg-white/20 hover:bg-white/40'"></button>
            </template>
        </div>
    </section>

    <!-- Section Editorial Category Grid & Interactive Showcase (Dinamis dari Admin) -->
    <section class="py-24 bg-white text-gray-900 overflow-hidden relative border-t border-gray-100"
             x-data="welcomeEditorialCategories()">

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12">
            <div class="text-center space-y-3">
                <span class="text-xs font-black uppercase tracking-[0.3em] text-red-600">Curated Collections</span>
                <h3 class="text-3xl sm:text-5xl font-black uppercase tracking-tight text-gray-900">
                    Select a Category <span class="text-red-600">to Get Started</span>
                </h3>
                <p class="text-gray-500 text-xs sm:text-sm font-medium max-w-md mx-auto">
                    Jelajahi gaya dan kategori pilihanmu untuk menemukan apparel terbaik musim ini.
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
                <template x-for="cat in categories" :key="cat.id">
                    <div @click="activeCategory = cat.id"
                         class="group relative h-[380px] sm:h-[420px] rounded-3xl overflow-hidden cursor-pointer shadow-md hover:shadow-2xl transition-all duration-500 border-2"
                         :class="activeCategory === cat.id ? 'border-red-600 ring-4 ring-red-600/20 scale-[1.02]' : 'border-transparent'">

                        <img :src="cat.image" :alt="cat.name" loading="lazy" class="absolute inset-0 w-full h-full object-cover group-hover:scale-110 transition-transform duration-700">

                        <div class="absolute inset-0 bg-gradient-to-t via-black/30 to-transparent transition-opacity duration-300"
                             :class="activeCategory === cat.id ? 'bg-black/60' : 'bg-black/40 group-hover:bg-black/50'"></div>

                        <div class="absolute inset-0 p-6 sm:p-8 flex flex-col justify-between text-white z-10">
                            <div class="flex justify-between items-start">
                                <span class="text-[10px] font-black uppercase tracking-widest px-3 py-1 rounded-full backdrop-blur-md bg-white/20 text-white border border-white/20" x-text="cat.subtitle"></span>
                                <span class="w-8 h-8 rounded-full bg-white/20 backdrop-blur-md flex items-center justify-center text-xs font-bold transition-transform group-hover:rotate-45"
                                      :class="activeCategory === cat.id ? 'bg-red-600 text-white' : ''">↗</span>
                            </div>

                            <div class="space-y-1">
                                <h4 class="text-3xl sm:text-4xl font-black tracking-tighter uppercase italic" x-text="cat.name"></h4>
                                <p class="text-xs text-gray-300 font-medium opacity-90" x-text="cat.tagline"></p>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Interactive Showcase Section -->
            <div class="relative bg-gray-950 text-white rounded-3xl p-8 sm:p-12 overflow-hidden shadow-2xl border border-gray-900">
                <div class="absolute top-0 right-0 w-96 h-96 bg-red-600/15 blur-[120px] pointer-events-none rounded-full"></div>

                <template x-for="cat in categories" :key="cat.id">
                    <div x-show="activeCategory === cat.id"
                         x-transition:enter="transition ease-out duration-700 delay-100"
                         x-transition:enter-start="opacity-0 translate-y-6 scale-[0.98]"
                         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                         class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12 items-center relative z-10">

                        <div class="space-y-6">
                            <span class="inline-block bg-red-600 text-white text-[10px] font-black uppercase tracking-widest px-3.5 py-1.5 rounded-lg shadow-md">
                                {{ $showcaseData['badge'] ?: 'Featured Showcase' }}
                            </span>
                            <h3 class="text-3xl sm:text-5xl font-black uppercase tracking-tight"
                                x-text="{{ json_encode($showcaseData['title']) }} || ('Koleksi Eksklusif ' + cat.name)"></h3>
                            <p class="text-gray-400 text-sm sm:text-base font-medium leading-relaxed"
                               x-text="{{ json_encode($showcaseData['description']) }} || (cat.tagline + '. Dirancang khusus dengan standar material premium untuk memberikan kenyamanan dan estetika visual tingkat tinggi.')"></p>

                            <div class="pt-2">
                                <a :href="{{ json_encode($showcaseData['cta_url'] ?: '#katalog') }}"
                                   @click="activeFilter = cat.id; searchQuery = ''; scrollToCatalog()"
                                   class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white font-bold text-xs uppercase tracking-wider px-8 py-4 rounded-xl transition-all shadow-lg shadow-red-600/30 hover:scale-105 active:scale-95">
                                    <span>
                                        <template x-if="{{ json_encode($showcaseData['cta_text']) }}">
                                            <span x-text="{{ json_encode($showcaseData['cta_text']) }}"></span>
                                        </template>
                                        <template x-if="!{{ json_encode($showcaseData['cta_text']) }}">
                                            <span>Belanja Kategori <span x-text="cat.name"></span></span>
                                        </template>
                                    </span>
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                                </a>
                            </div>
                        </div>

                        <div class="w-full aspect-[16/10] bg-gray-900 rounded-2xl overflow-hidden relative shadow-2xl border border-gray-800">
                            <img :src="{{ json_encode($showcaseData['image']) }} || cat.image" :alt="cat.name" loading="lazy" class="w-full h-full object-cover opacity-90 hover:scale-105 transition-transform duration-700">
                            <div class="absolute inset-0 bg-gradient-to-t from-gray-950/80 via-transparent to-transparent"></div>
                            <div class="absolute bottom-4 left-4 right-4 flex justify-between items-center text-xs font-bold text-gray-300">
                                <span class="bg-black/60 backdrop-blur-md px-3 py-1.5 rounded-lg border border-white/10 text-[11px]" x-text="cat.subtitle + ' Lookbook'"></span>
                                <span class="bg-red-600 text-white px-2.5 py-1 rounded-md text-[11px]">Active View</span>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </section>

    <!-- DARK SECTION: STOREFRONT BANNER & BRAND STORY -->
    <section class="bg-[#050507] text-white relative overflow-hidden pt-24 pb-32 border-t border-gray-900 z-0">
        <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[1000px] h-[700px] bg-red-900/15 blur-[150px] pointer-events-none rounded-full"></div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 space-y-24">
            <div class="relative w-full h-[450px] sm:h-[650px] rounded-[2.5rem] sm:rounded-[3rem] overflow-hidden shadow-[0_30px_100px_rgba(220,38,38,0.15)] border border-white/10 group">
                <img src="https://images.unsplash.com/photo-1441986300917-64674bd600d8?q=80&w=1600&auto=format&fit=crop" alt="Sport Mania Store" loading="lazy" class="absolute inset-0 w-full h-full object-cover filter brightness-[0.4] contrast-[1.1] group-hover:scale-105 transition-transform duration-[2s] ease-out">
                <div class="absolute inset-0 bg-gradient-to-t from-[#050507] via-black/30 to-transparent"></div>

                <div class="absolute inset-0 flex flex-col items-center justify-center text-center p-6 sm:p-12 space-y-6">
                    <span class="inline-block bg-black/40 border border-white/20 text-red-400 text-[10px] sm:text-xs font-black uppercase tracking-[0.4em] px-6 py-2.5 rounded-full backdrop-blur-md shadow-2xl">
                        Flagship Store & Headquarters
                    </span>
                    <h2 class="text-5xl sm:text-7xl lg:text-8xl font-black uppercase tracking-tighter italic text-white drop-shadow-[0_10px_20px_rgba(0,0,0,0.8)] leading-none pt-2">
                        BUILT FOR <span class="text-transparent bg-clip-text bg-gradient-to-r from-red-500 to-red-700 pr-6">CHAMPIONS</span>
                    </h2>
                    <p class="text-gray-300 text-xs sm:text-sm font-semibold max-w-xl uppercase tracking-[0.3em] opacity-90">
                        Malang, East Java • Official Sport Fashion Studio
                    </p>
                </div>
            </div>

            <div class="max-w-3xl mx-auto text-center space-y-8 relative z-20">
                <div class="flex items-center justify-center gap-4">
                    <div class="p-2.5 rounded-2xl bg-white/10 border border-white/20 shadow-lg backdrop-blur-md">
                        <img src="{{ asset('image/LOGO.jpeg') }}" alt="Logo" class="h-8 w-auto object-contain rounded-xl">
                    </div>
                    <span class="text-3xl font-black tracking-tighter uppercase italic drop-shadow-md">
                        <span class="text-red-500">SPORT</span><span class="text-white">MANIA</span>
                    </span>
                </div>
                <p class="text-gray-300 text-sm sm:text-base font-medium leading-relaxed">
                    Sport Mania Distro adalah pusat apparel dan perlengkapan olahraga premium terdepan. Kami mendedikasikan diri untuk menyediakan produk original berkualitas tinggi yang memadukan teknologi performa mutakhir dengan estetika gaya hidup urban modern.
                </p>
                <div class="flex flex-wrap items-center justify-center gap-4 text-xs font-bold text-gray-200">
                    <span class="flex items-center gap-2.5 bg-white/10 hover:bg-white/15 transition-colors px-6 py-3 rounded-full border border-white/20 backdrop-blur-md shadow-lg">
                        🛡️ 100% Original Guaranteed
                    </span>
                    <span class="flex items-center gap-2.5 bg-white/10 hover:bg-white/15 transition-colors px-6 py-3 rounded-full border border-white/20 backdrop-blur-md shadow-lg">
                        ⚡ Instant WhatsApp Checkout
                    </span>
                </div>
            </div>
        </div>
    </section>

    <!-- LIGHT SECTION: EKSPLORASI POPULER -->
    <section class="relative bg-gray-50 text-gray-900 rounded-t-[3rem] -mt-10 z-20 pb-32 pt-24 shadow-[0_-20px_50px_rgba(0,0,0,0.5)]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 space-y-16">
            <div class="text-center space-y-4">
                <div class="inline-flex items-center justify-center gap-2 px-4 py-1.5 rounded-full bg-white border border-gray-200 shadow-sm">
                    <span class="w-2 h-2 rounded-full bg-red-600 animate-pulse"></span>
                    <span class="text-red-600 text-xs font-black uppercase tracking-[0.3em]">Discovery & Trends</span>
                </div>
                <h3 class="text-4xl sm:text-5xl font-black uppercase tracking-tight text-gray-900">
                    Eksplorasi <span class="text-transparent bg-clip-text bg-gradient-to-r from-red-600 to-red-800 italic pr-4">Populer</span>
                </h3>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 max-w-6xl mx-auto">
                <div class="lg:col-span-5 bg-white border border-gray-100 p-8 sm:p-10 rounded-[2.5rem] shadow-[0_20px_40px_-15px_rgba(0,0,0,0.05)] hover:shadow-[0_30px_60px_-15px_rgba(220,38,38,0.1)] hover:border-red-100 transition-all duration-500 flex flex-col justify-between group">
                    <div class="mb-8">
                        <h4 class="text-xs font-black uppercase tracking-[0.2em] text-gray-900 flex items-center justify-between">
                            Top Brands
                            <span class="text-[9px] text-gray-500 font-bold bg-gray-100 px-2.5 py-1 rounded-md">Official</span>
                        </h4>
                    </div>

                    <div class="space-y-3">
                        <a href="#katalog" @click="activeFilter = 'semua'; searchQuery = 'Nike'; scrollToCatalog()" class="group/link block bg-gray-50 hover:bg-red-50 rounded-2xl p-4 transition-colors">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-bold text-gray-700 group-hover/link:text-red-700 transition-colors">Nike Pro Combat</span>
                                <span class="text-red-600 font-black opacity-0 -translate-x-2 group-hover/link:opacity-100 group-hover/link:translate-x-0 transition-all duration-300">↗</span>
                            </div>
                        </a>
                        <a href="#katalog" @click="activeFilter = 'semua'; searchQuery = 'Adidas'; scrollToCatalog()" class="group/link block bg-gray-50 hover:bg-red-50 rounded-2xl p-4 transition-colors">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-bold text-gray-700 group-hover/link:text-red-700 transition-colors">Adidas Performance</span>
                                <span class="text-red-600 font-black opacity-0 -translate-x-2 group-hover/link:opacity-100 group-hover/link:translate-x-0 transition-all duration-300">↗</span>
                            </div>
                        </a>
                        <a href="#katalog" @click="activeFilter = 'semua'; searchQuery = 'Puma'; scrollToCatalog()" class="group/link block bg-gray-50 hover:bg-red-50 rounded-2xl p-4 transition-colors">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-bold text-gray-700 group-hover/link:text-red-700 transition-colors">Puma Speed Series</span>
                                <span class="text-red-600 font-black opacity-0 -translate-x-2 group-hover/link:opacity-100 group-hover/link:translate-x-0 transition-all duration-300">↗</span>
                            </div>
                        </a>
                        <a href="#katalog" @click="activeFilter = 'semua'; searchQuery = 'Sport Mania'; scrollToCatalog()" class="group/link block bg-gray-50 hover:bg-red-50 rounded-2xl p-4 transition-colors border border-transparent hover:border-red-200">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-bold text-red-600 transition-colors">Sport Mania Original Excl.</span>
                                <span class="text-red-600 font-black opacity-0 -translate-x-2 group-hover/link:opacity-100 group-hover/link:translate-x-0 transition-all duration-300">↗</span>
                            </div>
                        </a>
                    </div>
                </div>

                <div class="lg:col-span-7 bg-white border border-gray-100 p-8 sm:p-10 rounded-[2.5rem] shadow-[0_20px_40px_-15px_rgba(0,0,0,0.05)] hover:shadow-[0_30px_60px_-15px_rgba(220,38,38,0.1)] hover:border-red-100 transition-all duration-500 group relative overflow-hidden">
                    <div class="mb-8">
                        <h4 class="text-xs font-black uppercase tracking-[0.2em] text-gray-900 flex items-center justify-between">
                            Trending Keywords
                            <span class="text-[9px] text-gray-500 font-bold bg-gray-100 px-2.5 py-1 rounded-md">Live</span>
                        </h4>
                    </div>

                    <div class="flex flex-wrap gap-3 sm:gap-4 relative z-10">
                        <button type="button" @click="activeFilter = 'semua'; searchQuery = 'Jersey'; scrollToCatalog()" class="bg-gray-50 hover:bg-red-600 text-gray-700 hover:text-white border border-gray-200 hover:border-red-600 px-6 py-3.5 rounded-2xl text-sm font-bold transition-all duration-300">
                            #JerseyDryFit
                        </button>
                        <button type="button" @click="activeFilter = 'semua'; searchQuery = 'Sepatu'; scrollToCatalog()" class="bg-gray-50 hover:bg-red-600 text-gray-700 hover:text-white border border-gray-200 hover:border-red-600 px-6 py-3.5 rounded-2xl text-sm font-bold transition-all duration-300">
                            #SepatuLariPro
                        </button>
                        <button type="button" @click="activeFilter = 'semua'; searchQuery = 'Hoodie'; scrollToCatalog()" class="bg-gray-50 hover:bg-red-600 text-gray-700 hover:text-white border border-gray-200 hover:border-red-600 px-6 py-3.5 rounded-2xl text-sm font-bold transition-all duration-300">
                            #OversizedHoodie
                        </button>
                        <button type="button" @click="activeFilter = 'semua'; searchQuery = 'Tas'; scrollToCatalog()" class="bg-gray-50 hover:bg-red-600 text-gray-700 hover:text-white border border-gray-200 hover:border-red-600 px-6 py-3.5 rounded-2xl text-sm font-bold transition-all duration-300">
                            #SportDuffelBag
                        </button>
                        <button type="button" @click="activeFilter = 'semua'; searchQuery = 'Sarung Tangan'; scrollToCatalog()" class="bg-gray-50 hover:bg-red-600 text-gray-700 hover:text-white border border-gray-200 hover:border-red-600 px-6 py-3.5 rounded-2xl text-sm font-bold transition-all duration-300">
                            #SarungTanganGym
                        </button>
                        <button type="button" @click="activeFilter = 'semua'; searchQuery = 'Legging'; scrollToCatalog()" class="bg-gray-50 hover:bg-red-600 text-gray-700 hover:text-white border border-gray-200 hover:border-red-600 px-6 py-3.5 rounded-2xl text-sm font-bold transition-all duration-300">
                            #HighWaistLegging
                        </button>
                    </div>

                    <div class="absolute -bottom-4 -right-2 text-[7rem] font-black text-gray-50 pointer-events-none italic tracking-tighter">
                        TRENDS
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FOOTER SECTION -->
    <footer x-data="welcomeFooter()"
            class="bg-[#09090b] text-white pt-24 pb-12 rounded-t-[3rem] -mt-12 relative z-30 shadow-[0_-20px_50px_rgba(0,0,0,0.2)]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12 pb-16 border-b border-gray-800/60">
                <!-- KOLOM 1 -->
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
                        <p class="font-bold text-white uppercase tracking-wider">Layanan Pelanggan Sport Mania</p>
                        <p>E-mail: support@sportmaniadistro.com</p>
                        <p>Telepon: 0822-3454-8092</p>
                        <p class="text-red-500 font-semibold">WhatsApp CS: +62 822-3454-8092</p>
                    </div>
                    <div>
                        <p class="font-bold text-white uppercase tracking-wider text-xs mb-3">Temukan Kami</p>
                        <div class="flex flex-wrap gap-2.5">
                            <button type="button" @click="openInfo('tentang')" title="Facebook Sport Mania" class="w-9 h-9 rounded-xl bg-white/5 hover:bg-red-600 border border-white/10 flex items-center justify-center text-white text-xs font-bold transition cursor-pointer">f</button>
                            <button type="button" @click="openInfo('tentang')" title="Instagram Sport Mania" class="w-9 h-9 rounded-xl bg-white/5 hover:bg-red-600 border border-white/10 flex items-center justify-center text-white text-xs font-bold transition cursor-pointer">ig</button>
                            <button type="button" @click="openInfo('tentang')" title="YouTube Sport Mania" class="w-9 h-9 rounded-xl bg-white/5 hover:bg-red-600 border border-white/10 flex items-center justify-center text-white text-xs font-bold transition cursor-pointer">yt</button>
                            <a href="https://wa.me/6282234548092" target="_blank" rel="noopener noreferrer" title="WhatsApp CS" class="w-9 h-9 rounded-xl bg-white/5 hover:bg-red-600 border border-white/10 flex items-center justify-center text-white text-xs font-bold transition">wa</a>
                        </div>
                    </div>
                </div>

                <!-- KOLOM 2 -->
                <div class="space-y-4">
                    <h4 class="font-black text-xs uppercase tracking-[0.2em] text-white">Layanan</h4>
                    <ul class="space-y-3 text-xs text-gray-400 font-medium">
                        <li><button type="button" @click="openInfo('faq')" class="hover:text-white transition text-left cursor-pointer">Bantuan & FAQ</button></li>
                        <li><button type="button" @click="openInfo('retur')" class="hover:text-white transition text-left cursor-pointer">Cara Pengembalian</button></li>
                        <li><a href="#katalog" class="hover:text-white transition">Product Index & Varian</a></li>
                        <li><a href="#promo-showcase" class="hover:text-white transition">Promo Partner Kami</a></li>
                        <li><a href="https://wa.me/6282234548092" target="_blank" rel="noopener noreferrer" class="hover:text-white transition">Konfirmasi Pembayaran</a></li>
                        <li><a href="https://wa.me/6282234548092" target="_blank" rel="noopener noreferrer" class="hover:text-white transition">Hubungi Kami (WhatsApp)</a></li>
                        <li><button type="button" @click="openInfo('ongkir')" class="hover:text-white transition text-left cursor-pointer">Pengiriman & Ongkir</button></li>
                        <li><a href="{{ route('order.track') }}" class="hover:text-white transition text-red-400 font-bold">Lacak Status Pesanan</a></li>
                    </ul>
                </div>

                <!-- KOLOM 3 -->
                <div class="space-y-4">
                    <h4 class="font-black text-xs uppercase tracking-[0.2em] text-white">Tentang Kami</h4>
                    <ul class="space-y-3 text-xs text-gray-400 font-medium">
                        <li><button type="button" @click="openInfo('tentang')" class="hover:text-white transition text-left cursor-pointer">Tentang Sport Mania</button></li>
                        <li><a href="https://wa.me/6282234548092?text=Halo%20Admin%20Sport%20Mania,%20saya%20tertarik%20kerja%20sama%20brand." target="_blank" rel="noopener noreferrer" class="hover:text-white transition">Promosikan Brand Anda</a></li>
                        <li><button type="button" @click="openInfo('tentang')" class="hover:text-white transition text-left cursor-pointer">Pers / Media</button></li>
                        <li><button type="button" @click="openInfo('tentang')" class="hover:text-white transition text-left cursor-pointer">Karir & Internship</button></li>
                        <li><button type="button" @click="openInfo('tentang')" class="hover:text-white transition text-left cursor-pointer">Sustainability Strategy</button></li>
                        <li><button type="button" @click="openInfo('syarat')" class="hover:text-white transition text-left cursor-pointer">Persyaratan & Ketentuan</button></li>
                        <li><button type="button" @click="openInfo('privasi')" class="hover:text-white transition text-left cursor-pointer">Kebijakan Privasi</button></li>
                    </ul>
                </div>

                <!-- KOLOM 4 -->
                <div class="space-y-6">
                    <div>
                        <h4 class="font-black text-xs uppercase tracking-[0.2em] text-white mb-2">Berlangganan Newsletter</h4>
                        <p class="text-xs text-gray-400 mb-4">Dapatkan info promo spesial dan peluncuran produk terbaru.</p>
                        <form @submit.prevent="submitNewsletter('Pria')" class="space-y-3">
                            <input type="email" x-model="newsletterEmail" placeholder="Alamat email Kamu" required aria-label="Alamat email untuk newsletter" class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-xs text-white placeholder-gray-500 focus:outline-none focus:border-red-500">
                            <div class="flex gap-2">
                                <button type="submit" @click="newsletterPref = 'Pria'" class="flex-1 bg-white hover:bg-red-600 text-black hover:text-white font-bold text-xs uppercase tracking-wider py-2.5 rounded-xl transition cursor-pointer">Pria</button>
                                <button type="button" @click="if ($el.closest('form').reportValidity()) submitNewsletter('Wanita')" class="flex-1 bg-white hover:bg-red-600 text-black hover:text-white font-bold text-xs uppercase tracking-wider py-2.5 rounded-xl transition cursor-pointer">Wanita</button>
                            </div>
                            <p x-show="newsletterSuccess" x-transition x-cloak class="text-[11px] font-bold text-emerald-400" x-text="'✓ Terima kasih telah berlangganan newsletter Sport Mania (' + newsletterPref + ')!'"></p>
                        </form>
                    </div>
                    <div class="space-y-3 pt-2">
                        <h4 class="font-black text-xs uppercase tracking-[0.2em] text-white">Download App Kami</h4>
                        <div class="flex flex-col gap-2.5">
                            <button type="button" @click="openInfo('app')" class="w-full text-left bg-white/5 border border-white/10 rounded-xl p-2.5 flex items-center gap-3 cursor-pointer hover:bg-white/10 transition">
                                <span class="text-xl">🤖</span>
                                <div>
                                    <p class="text-[9px] text-gray-400 uppercase font-bold">Get It On</p>
                                    <p class="text-xs font-bold text-white">Google Play</p>
                                </div>
                            </button>
                            <button type="button" @click="openInfo('app')" class="w-full text-left bg-white/5 border border-white/10 rounded-xl p-2.5 flex items-center gap-3 cursor-pointer hover:bg-white/10 transition">
                                <span class="text-xl">🍏</span>
                                <div>
                                    <p class="text-[9px] text-gray-400 uppercase font-bold">Download on the</p>
                                    <p class="text-xs font-bold text-white">App Store</p>
                                </div>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="pt-8 flex flex-col sm:flex-row justify-between items-center gap-4 text-xs text-gray-500 font-medium">
                <div class="flex flex-wrap gap-6">
                    <button type="button" @click="openInfo('tentang')" class="hover:text-white transition cursor-pointer">Tentang Sport Mania</button>
                    <button type="button" @click="openInfo('privasi')" class="hover:text-white transition cursor-pointer">Kebijakan Privasi</button>
                    <button type="button" @click="openInfo('syarat')" class="hover:text-white transition cursor-pointer">Persyaratan dan Ketentuan</button>
                </div>
                <p>© 2026 Eka Bagus Karisma & Aditya Panji Pratama. Proyek Tugas Akhir S1 Pendidikan Teknik Informatika UM.</p>
            </div>
        </div>

        <!-- MODAL INFORMASI FOOTER -->
        <div x-show="infoModal.open"
             x-cloak
             role="dialog"
             aria-modal="true"
             @keydown.escape.window="infoModal.open = false"
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm">
            <div @click.outside="infoModal.open = false"
                 class="bg-white text-gray-900 rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl border border-gray-100 space-y-4">
                <div class="flex items-start justify-between gap-4 border-b border-gray-100 pb-4">
                    <div>
                        <span class="inline-block bg-red-50 text-red-600 text-[10px] font-black uppercase tracking-widest px-2.5 py-1 rounded-lg" x-text="infoModal.badge"></span>
                        <h3 class="text-lg font-black text-gray-900 mt-1.5" x-text="infoModal.title"></h3>
                    </div>
                    <button type="button" @click="infoModal.open = false" class="w-8 h-8 rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-600 flex items-center justify-center font-bold text-xs cursor-pointer" aria-label="Tutup modal">✕</button>
                </div>
                <p class="text-xs text-gray-600 leading-relaxed whitespace-pre-line" x-text="infoModal.content"></p>
                <div class="pt-2 flex justify-end gap-2">
                    <a href="https://wa.me/6282234548092" target="_blank" rel="noopener noreferrer" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold uppercase tracking-wider px-4 py-2.5 rounded-xl transition">
                        Chat CS WhatsApp
                    </a>
                    <button type="button" @click="infoModal.open = false" class="bg-gray-900 hover:bg-red-600 text-white text-xs font-bold uppercase tracking-wider px-5 py-2.5 rounded-xl transition cursor-pointer">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>