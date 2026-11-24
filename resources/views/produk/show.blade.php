@php
    $relationsToLoad = ['variants', 'category', 'images'];
    if (method_exists($produk, 'reviews') && \Illuminate\Support\Facades\Schema::hasTable('reviews')) {
        $relationsToLoad[] = 'reviews.user';
    }
    $produk->loadMissing($relationsToLoad);

    $coverImageUrl = $produk->image
        ? (filter_var($produk->image, FILTER_VALIDATE_URL) ? $produk->image : asset('storage/' . $produk->image))
        : asset('image/LOGO.jpeg');

    // Menyatukan foto utama, video katalog (jika ada), dan foto galeri ke dalam satu list media
    $mediaItems = [
        [
            'type' => 'image',
            'url' => $coverImageUrl,
            'label' => 'Foto Utama',
        ]
    ];

    $rawVideo = $produk->video ?? $produk->video_path ?? $produk->video_url ?? $produk->catalog_video ?? null;
    if (!empty($rawVideo)) {
        $mediaItems[] = [
            'type' => 'video',
            'url' => filter_var($rawVideo, FILTER_VALIDATE_URL) ? $rawVideo : asset('storage/' . $rawVideo),
            'label' => 'Video Produk',
        ];
    }

    if ($produk->images && $produk->images->count() > 0) {
        foreach ($produk->images as $idx => $img) {
            $rawGalleryPath = $img->image_path ?? $img->image ?? $img->path ?? null;
            if (!empty($rawGalleryPath)) {
                $mediaItems[] = [
                    'type' => 'image',
                    'url' => filter_var($rawGalleryPath, FILTER_VALIDATE_URL) ? $rawGalleryPath : asset('storage/' . $rawGalleryPath),
                    'label' => 'Tampilan ' . ($idx + 2),
                ];
            }
        }
    }

    $hasVariants = $produk->variants && $produk->variants->count() > 0;
    $totalStock = $hasVariants
        ? (int) $produk->variants->sum('stock')
        : (int) ($produk->stock ?? 10);

    $firstAvailableIndex = 0;
    if ($hasVariants) {
        $foundIdx = $produk->variants->search(fn($v) => (int) ($v->stock ?? 0) > 0);
        if ($foundIdx !== false) {
            $firstAvailableIndex = $foundIdx;
        }
    }

    // Hitung jumlah terjual untuk produk ini
    $soldCount = 0;
    if (isset($produk->sold_count)) {
        $soldCount = (int) $produk->sold_count;
    } elseif (\Illuminate\Support\Facades\Schema::hasTable('order_items') && \Illuminate\Support\Facades\Schema::hasTable('orders')) {
        $qtyColumn = \Illuminate\Support\Facades\Schema::hasColumn('order_items', 'quantity')
            ? 'order_items.quantity'
            : (\Illuminate\Support\Facades\Schema::hasColumn('order_items', 'qty') ? 'order_items.qty' : null);
        if ($qtyColumn) {
            $soldCount = (int) \Illuminate\Support\Facades\DB::table('order_items')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->where('order_items.product_id', $produk->id)
                ->whereRaw('LOWER(orders.status) NOT IN (?, ?, ?, ?)', ['cancelled', 'dibatalkan', 'failed', 'batal'])
                ->sum($qtyColumn);
        }
    }

    $reviews = (method_exists($produk, 'reviews') ? ($produk->reviews ?? collect()) : collect())
        ->sortByDesc('created_at')
        ->values();
    $totalReviews = $reviews->count();
    $avgRatingFloat = $totalReviews > 0 ? (float) $reviews->avg('rating') : 0.0;
    $avgRating = number_format($avgRatingFloat, 1);

    $ratingCounts = [
        5 => $reviews->where('rating', 5)->count(),
        4 => $reviews->where('rating', 4)->count(),
        3 => $reviews->where('rating', 3)->count(),
        2 => $reviews->where('rating', 2)->count(),
        1 => $reviews->where('rating', 1)->count(),
    ];
@endphp
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $produk->name }} | Sport Mania Distro</title>
    
    <!-- Tailwind CSS, Font Inter & Alpine.js -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Inter', sans-serif; }
        [x-cloak] { display: none !important; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #fafafa; }
        ::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 4px; }
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        @keyframes shrinkBar {
            from { width: 100%; }
            to { width: 0%; }
        }
        .animate-toast-bar {
            animation: shrinkBar 3s linear forwards;
        }
    </style>
</head>
<body class="bg-[#FAFAFA] text-gray-900 antialiased selection:bg-red-500 selection:text-white flex flex-col min-h-screen"
      x-data="{ 
          mobileMenuOpen: false,
          mobileSearchOpen: false,
          openKategori: false,
          sizeGuideOpen: false,
          searchQuery: '',
          qty: 1,
          selectedVariantIndex: {{ $firstAvailableIndex }},
          activeTab: 'deskripsi',
          selectedRatingFilter: 'all',
          ratingCounts: {{ \Illuminate\Support\Js::from($ratingCounts) }},
          mediaItems: {{ \Illuminate\Support\Js::from($mediaItems) }},
          activeMediaIndex: 0,
          touchStartX: 0,
          touchEndX: 0,
          coverImage: {{ \Illuminate\Support\Js::from($coverImageUrl) }},
          product: {{ \Illuminate\Support\Js::from($produk->toArray()) }},
          
          cartItems: JSON.parse(localStorage.getItem('cart_items')) || [],

          get currentMedia() {
              return this.mediaItems[this.activeMediaIndex] || { type: 'image', url: this.coverImage, label: 'Foto Utama' };
          },

          setActiveMedia(index) {
              if (!this.mediaItems || this.mediaItems.length === 0) return;
              const total = this.mediaItems.length;
              this.activeMediaIndex = ((index % total) + total) % total;
              this.$nextTick(() => {
                  const thumbEl = document.getElementById('media-thumb-' + this.activeMediaIndex);
                  if (thumbEl) {
                      thumbEl.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                  }
              });
          },

          nextMedia() {
              this.setActiveMedia(this.activeMediaIndex + 1);
          },

          prevMedia() {
              this.setActiveMedia(this.activeMediaIndex - 1);
          },

          handleTouchStart(e) {
              this.touchStartX = e.changedTouches[0].screenX;
          },

          handleTouchEnd(e) {
              this.touchEndX = e.changedTouches[0].screenX;
              const diff = this.touchStartX - this.touchEndX;
              if (Math.abs(diff) > 40) {
                  if (diff > 0) this.nextMedia();
                  else this.prevMedia();
              }
          },

          syncCart() {
              this.cartItems = JSON.parse(localStorage.getItem('cart_items')) || [];
          },

          get cartCount() {
              return this.cartItems.reduce((acc, item) => acc + Number(item.qty || 0), 0);
          },

          get currentMaxStock() {
              if (this.product.variants && this.product.variants.length > 0) {
                  return Number(this.product.variants[this.selectedVariantIndex]?.stock ?? 0);
              }
              return Number(this.product.stock ?? 99);
          },

          jumpToReviews() {
              this.activeTab = 'ulasan';
              this.$nextTick(() => {
                  this.$refs.detailTabs?.scrollIntoView({ behavior: 'smooth', block: 'start' });
              });
          },

          triggerNotify(type, title, message) {
              window.dispatchEvent(new CustomEvent('notify', { 
                  detail: { type: type, title: title, message: message } 
              }));
          },

          incrementQty() {
              if (this.currentMaxStock <= 0) {
                  this.triggerNotify('error', 'Stok Habis', 'Maaf, varian produk ini sedang tidak tersedia.');
                  return;
              }
              if (this.qty >= this.currentMaxStock) {
                  this.triggerNotify('warning', 'Batas Maksimal', 'Stok tersedia hanya ' + this.currentMaxStock + ' pcs.');
                  return;
              }
              this.qty++;
          },

          saveSelectionToCart(errorTitle) {
              if (this.currentMaxStock <= 0) {
                  this.triggerNotify('error', errorTitle, 'Stok untuk varian yang dipilih sudah habis!');
                  return null;
              }

              const variant = this.product.variants && this.product.variants.length > 0 
                  ? this.product.variants[this.selectedVariantIndex] 
                  : { id: null, size: 'All Size', color: '-' };

              const variantId = variant.id ?? null;
              const sizeVal = variant.size || 'All Size';
              const colorVal = variant.color || '-';
              const requestedQty = parseInt(this.qty, 10) || 1;
              const unitPrice = Number(this.product.discount_price || this.product.price);

              const existingItem = this.cartItems.find(
                  i => i.id === this.product.id && (i.variant_id ?? null) === variantId && i.size === sizeVal && i.color === colorVal
              );
              const totalRequested = (existingItem ? Number(existingItem.qty) : 0) + requestedQty;

              if (totalRequested > this.currentMaxStock) {
                  this.triggerNotify('warning', 'Stok Terbatas', 'Total di keranjang melebihi sisa stok (' + this.currentMaxStock + ' pcs).');
                  return null;
              }

              if (existingItem) {
                  existingItem.qty = totalRequested;
                  existingItem.variant_id = variantId;
                  existingItem.image = this.coverImage;
                  existingItem.maxStock = this.currentMaxStock;
                  existingItem.price = unitPrice;
              } else {
                  this.cartItems.push({
                      id: this.product.id,
                      variant_id: variantId,
                      name: this.product.name,
                      category: this.product.category ? this.product.category.name : 'Umum',
                      image: this.coverImage,
                      size: sizeVal,
                      color: colorVal,
                      price: unitPrice,
                      qty: requestedQty,
                      maxStock: this.currentMaxStock
                  });
              }

              localStorage.setItem('cart_items', JSON.stringify(this.cartItems));
              window.dispatchEvent(new CustomEvent('cart-updated', { detail: this.cartItems }));
              return { size: sizeVal, color: colorVal, qty: requestedQty };
          },

          addToCart() {
              const added = this.saveSelectionToCart('Gagal Menambahkan');
              if (!added) return;
              this.triggerNotify('success', 'Masuk Keranjang!', added.qty + 'x ' + this.product.name + ' (' + added.size + ') berhasil ditambahkan.');
          },

          checkoutNow() {
              const added = this.saveSelectionToCart('Gagal Checkout');
              if (!added) return;
              window.location.href = '{{ route('customer.checkout') }}';
          }
      }"
      @keydown.window.arrow-left="if (!['INPUT', 'TEXTAREA'].includes($event.target.tagName)) prevMedia()"
      @keydown.window.arrow-right="if (!['INPUT', 'TEXTAREA'].includes($event.target.tagName)) nextMedia()"
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
                
                <!-- Kiri: Tombol Kembali + Logo & Brand Name -->
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

                <!-- Navigasi Tengah -->
                <div class="hidden lg:flex space-x-6 items-center">
                    <a href="{{ route('home') }}" class="text-gray-900 hover:text-red-600 font-bold transition text-xs uppercase tracking-wider">Beranda</a>
                    <a href="{{ route('home') }}#katalog" class="text-gray-700 hover:text-red-600 font-bold transition text-xs uppercase tracking-wider">Katalog</a>
                    
                    <!-- Dropdown Kategori -->
                    <div class="relative" @mouseleave="openKategori = false">
                        <button @mouseover="openKategori = true" @click="openKategori = !openKategori" 
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
                            <a href="{{ route('home', ['category' => 'pakaian']) }}#katalog" @click="openKategori = false" class="flex items-center px-4 py-2 text-xs font-bold text-gray-700 hover:bg-red-50 hover:text-red-600">👕 Pakaian Olahraga</a>
                            <a href="{{ route('home', ['category' => 'sepatu']) }}#katalog" @click="openKategori = false" class="flex items-center px-4 py-2 text-xs font-bold text-gray-700 hover:bg-red-50 hover:text-red-600">👟 Sepatu Olahraga</a>
                            <a href="{{ route('home', ['category' => 'tas']) }}#katalog" @click="openKategori = false" class="flex items-center px-4 py-2 text-xs font-bold text-gray-700 hover:bg-red-50 hover:text-red-600">🎒 Tas & Ransel</a>
                            <a href="{{ route('home', ['category' => 'aksesoris']) }}#katalog" @click="openKategori = false" class="flex items-center px-4 py-2 text-xs font-bold text-gray-700 hover:bg-red-50 hover:text-red-600">🥊 Aksesoris</a>
                        </div>
                    </div>

                    <a href="{{ route('home', ['category' => 'promo']) }}#katalog" class="text-red-600 hover:text-red-700 font-black text-xs uppercase tracking-wider transition flex items-center gap-1.5 px-3 py-1.5 bg-red-50 rounded-full border border-red-200 animate-pulse">
                        🔥 PROMO SPESIAL
                    </a>
                </div>

                <!-- Bagian Kanan: Search, Cart, Auth (Desktop) -->
                <div class="hidden lg:flex items-center space-x-4">
                    <form action="{{ route('home') }}#katalog" method="GET" class="relative w-48 xl:w-56">
                        <input type="text" 
                               name="search"
                               x-model="searchQuery"
                               class="w-full bg-gray-100 text-xs text-gray-900 rounded-full pl-3.5 pr-8 py-2 focus:outline-none focus:ring-2 focus:ring-red-600 border border-transparent transition placeholder-gray-400 font-medium" 
                               placeholder="Cari produk olahraga...">
                        <button type="submit" class="absolute right-2.5 top-2 text-gray-400 hover:text-red-600">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </button>
                    </form>
                    
                    @if (Route::has('login'))
                        @auth
                            @php
                                $authRole = strtolower((string) (Auth::user()->role ?? ''));
                            @endphp
                            @if($authRole === 'admin')
                                <a href="{{ route('admin.dashboard') }}" class="bg-gray-900 hover:bg-red-600 transition text-white px-3.5 py-1.5 rounded-lg font-bold text-xs uppercase">Panel Admin</a>
                            @elseif($authRole === 'owner' && Route::has('owner.dashboard'))
                                <a href="{{ route('owner.dashboard') }}" class="bg-gray-900 text-white px-3 py-1.5 rounded-lg font-bold text-xs uppercase">Owner</a>
                            @else
                                <a href="{{ route('customer.cart') }}" class="relative text-gray-700 hover:text-red-600 p-1.5 rounded-full hover:bg-gray-100" title="Keranjang Belanja">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                                    <span x-show="cartCount > 0" x-cloak class="absolute top-0 right-0 bg-red-600 text-white text-[9px] font-black px-1 py-0.5 rounded-full" x-text="cartCount"></span>
                                </a>
                            @endif

                            <div x-data="{ open: false }" class="relative border-l border-gray-200 pl-3">
                                <button @click="open = !open" @click.outside="open = false" class="flex items-center gap-1.5 text-gray-800 font-bold focus:outline-none">
                                    <div class="w-7 h-7 rounded-full bg-red-100 text-red-600 flex items-center justify-center font-black text-xs">
                                        {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                                    </div>
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
                            <a href="{{ route('customer.cart') }}" class="relative text-gray-700 hover:text-red-600 p-1.5 rounded-full hover:bg-gray-100 mr-2" title="Keranjang Belanja">
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
                            title="Cari Produk">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </button>

                    <!-- Tombol Keranjang Mobile -->
                    <a href="{{ route('customer.cart') }}" class="relative text-gray-700 hover:bg-gray-100 p-2 rounded-full transition" title="Keranjang Belanja">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                        <span x-show="cartCount > 0" x-cloak class="absolute top-0.5 right-0.5 bg-red-600 text-white text-[9px] font-black px-1.5 py-0.5 rounded-full" x-text="cartCount"></span>
                    </a>

                    <!-- Tombol Hamburger Menu -->
                    <button type="button"
                            @click="mobileMenuOpen = !mobileMenuOpen; mobileSearchOpen = false"
                            class="text-gray-700 hover:bg-gray-100 p-2 rounded-lg transition"
                            title="Menu">
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
                <form action="{{ route('home') }}#katalog" method="GET" class="flex items-center gap-2">
                    <div class="relative flex-1">
                        <input type="text"
                               name="search"
                               x-ref="mobileSearchInput"
                               x-model="searchQuery"
                               placeholder="Cari jersey, sepatu, tas..."
                               class="w-full bg-gray-100 text-xs text-gray-900 rounded-xl pl-3.5 pr-8 py-2.5 focus:outline-none focus:ring-2 focus:ring-red-600 font-medium">
                        <button type="button"
                                x-show="searchQuery.length > 0"
                                @click="searchQuery = ''; $refs.mobileSearchInput.focus()"
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
                <div class="px-2 space-y-2">
                    <p class="text-[10px] font-black uppercase tracking-wider text-gray-400">Menu & Kategori</p>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('home') }}" class="text-[11px] font-bold px-3 py-2 rounded-lg bg-gray-100 text-gray-700 hover:bg-red-50 hover:text-red-600">Beranda</a>
                        <a href="{{ route('home') }}#katalog" class="text-[11px] font-bold px-3 py-2 rounded-lg bg-gray-100 text-gray-700 hover:bg-red-50 hover:text-red-600">Katalog</a>
                        <a href="{{ route('home', ['category' => 'pakaian']) }}#katalog" class="text-[11px] font-bold px-3 py-2 rounded-lg bg-gray-100 text-gray-700 hover:bg-red-50 hover:text-red-600">👕 Pakaian</a>
                        <a href="{{ route('home', ['category' => 'sepatu']) }}#katalog" class="text-[11px] font-bold px-3 py-2 rounded-lg bg-gray-100 text-gray-700 hover:bg-red-50 hover:text-red-600">👟 Sepatu</a>
                        <a href="{{ route('home', ['category' => 'tas']) }}#katalog" class="text-[11px] font-bold px-3 py-2 rounded-lg bg-gray-100 text-gray-700 hover:bg-red-50 hover:text-red-600">🎒 Tas</a>
                        <a href="{{ route('home', ['category' => 'aksesoris']) }}#katalog" class="text-[11px] font-bold px-3 py-2 rounded-lg bg-gray-100 text-gray-700 hover:bg-red-50 hover:text-red-600">🥊 Aksesoris</a>
                        <a href="{{ route('home', ['category' => 'promo']) }}#katalog" class="text-[11px] font-black px-3 py-2 rounded-lg bg-red-600 text-white">🔥 Promo Spesial</a>
                    </div>
                </div>

                <div class="border-t border-gray-100 pt-4 px-2 space-y-2">
                    @if (Route::has('login'))
                        @auth
                            @php
                                $mobileAuthRole = strtolower((string) (Auth::user()->role ?? ''));
                            @endphp
                            <div class="flex items-center gap-3 mb-4 px-2">
                                <div class="w-10 h-10 rounded-full bg-red-100 text-red-600 flex items-center justify-center font-black text-sm">
                                    {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-sm font-bold text-gray-900">{{ Auth::user()->name }}</span>
                                    <span class="text-[10px] text-gray-500 uppercase font-semibold">{{ Auth::user()->role }}</span>
                                </div>
                            </div>
                            
                            @if($mobileAuthRole === 'admin')
                                <a href="{{ route('admin.dashboard') }}" class="block w-full text-center bg-gray-900 text-white font-bold text-xs uppercase tracking-widest py-3 rounded-lg mb-2">Panel Admin</a>
                            @elseif($mobileAuthRole === 'owner' && Route::has('owner.dashboard'))
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

    <!-- Main Content Detail Produk -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 flex-grow w-full">
        
        <!-- Breadcrumbs -->
        <nav class="flex text-xs text-gray-500 font-medium mb-8 overflow-x-auto whitespace-nowrap hide-scrollbar">
            <ol class="inline-flex items-center space-x-2">
                <li><a href="{{ route('home') }}" class="hover:text-red-600 transition">Beranda</a></li>
                <li><span class="mx-1.5 text-gray-300">/</span></li>
                <li><a href="{{ route('home') }}#katalog" class="hover:text-red-600 transition">Katalog</a></li>
                <li><span class="mx-1.5 text-gray-300">/</span></li>
                <li class="text-gray-900 font-bold truncate max-w-[180px] sm:max-w-xs" aria-current="page">{{ $produk->name }}</li>
            </ol>
        </nav>

        <div class="lg:grid lg:grid-cols-12 lg:gap-12 xl:gap-16 items-start">
            
            <!-- Kolom Kiri: Stage Foto/Video Utama + Galeri Thumbnail Horizontal di Bawah (6 Kolom) -->
            <div class="lg:col-span-6 flex flex-col gap-3.5">
                
                <!-- Stage Utama (Foto & Video + Kontrol Geser Kiri/Kanan + Swipe Touch) -->
                <div @touchstart.passive="handleTouchStart($event)"
                     @touchend.passive="handleTouchEnd($event)"
                     class="w-full aspect-[4/5] bg-white rounded-3xl overflow-hidden relative shadow-sm border border-gray-100 p-3 sm:p-4 group flex items-center justify-center select-none">
                    
                    @if($totalStock <= 0)
                        <span class="absolute top-6 left-6 z-20 bg-gray-900 text-white text-[10px] font-black px-3.5 py-1.5 rounded-xl uppercase tracking-wider shadow-lg pointer-events-none">
                            STOK HABIS
                        </span>
                    @elseif($produk->discount_price)
                        <span class="absolute top-6 left-6 z-20 bg-red-600 text-white text-[10px] font-black px-3.5 py-1.5 rounded-xl uppercase tracking-wider shadow-lg animate-pulse pointer-events-none">
                            ⚡ SALE
                        </span>
                    @endif

                    <!-- Indikator Slide & Label Media -->
                    <div x-show="mediaItems.length > 1"
                         class="absolute top-6 right-6 z-20 flex items-center gap-1.5 bg-gray-900/80 backdrop-blur-md text-white text-[10px] font-bold px-3 py-1.5 rounded-xl shadow pointer-events-none">
                        <span x-show="currentMedia.type === 'video'" class="w-2 h-2 rounded-full bg-red-500 animate-ping"></span>
                        <span x-text="currentMedia.label"></span>
                        <span class="text-gray-400">•</span>
                        <span x-text="(activeMediaIndex + 1) + '/' + mediaItems.length"></span>
                    </div>

                    <!-- Tampilan Gambar -->
                    <template x-if="currentMedia.type === 'image'">
                        <img :src="currentMedia.url"
                             :alt="currentMedia.label || '{{ $produk->name }}'"
                             class="w-full h-full object-cover mix-blend-multiply group-hover:scale-105 transition-transform duration-700 rounded-2xl {{ $totalStock <= 0 ? 'opacity-60 grayscale' : '' }}">
                    </template>

                    <!-- Tampilan Video Katalog -->
                    <template x-if="currentMedia.type === 'video'">
                        <video :src="currentMedia.url"
                               controls
                               autoplay
                               muted
                               loop
                               playsinline
                               class="w-full h-full object-cover rounded-2xl bg-black"></video>
                    </template>

                    <!-- Tombol Panah Geser Kiri & Kanan di Area Gambar -->
                    <template x-if="mediaItems.length > 1">
                        <div>
                            <button type="button"
                                    @click="prevMedia()"
                                    title="Geser Sebelumnya"
                                    class="absolute left-5 top-1/2 -translate-y-1/2 z-20 w-10 h-10 rounded-full bg-white/90 hover:bg-gray-900 text-gray-900 hover:text-white border border-gray-200 shadow-lg backdrop-blur-sm flex items-center justify-center opacity-90 sm:opacity-0 group-hover:opacity-100 transition-all duration-200 active:scale-95">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"></path></svg>
                            </button>

                            <button type="button"
                                    @click="nextMedia()"
                                    title="Geser Berikutnya"
                                    class="absolute right-5 top-1/2 -translate-y-1/2 z-20 w-10 h-10 rounded-full bg-white/90 hover:bg-gray-900 text-gray-900 hover:text-white border border-gray-200 shadow-lg backdrop-blur-sm flex items-center justify-center opacity-90 sm:opacity-0 group-hover:opacity-100 transition-all duration-200 active:scale-95">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                            </button>

                            <!-- Dots Indikator Bawah (Muncul saat bukan mode video agar tidak menghalangi kontrol player) -->
                            <div x-show="currentMedia.type !== 'video'"
                                 class="absolute bottom-6 left-1/2 -translate-x-1/2 z-20 flex items-center gap-1.5 bg-black/45 backdrop-blur-md px-3 py-1.5 rounded-full">
                                <template x-for="(item, idx) in mediaItems" :key="idx">
                                    <button type="button"
                                            @click="setActiveMedia(idx)"
                                            :class="activeMediaIndex === idx ? 'w-5 bg-red-500' : 'w-1.5 bg-white/60 hover:bg-white'"
                                            class="h-1.5 rounded-full transition-all duration-300"></button>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Bar Galeri Thumbnail Horizontal di Bawah (Kiri <-> Kanan) -->
                <div x-show="mediaItems.length > 1"
                     class="w-full flex items-center gap-2.5 bg-white p-2.5 rounded-2xl border border-gray-100 shadow-sm">
                    
                    <!-- Tombol Geser Kiri -->
                    <button type="button"
                            @click="prevMedia()"
                            title="Sebelumnya"
                            class="w-9 h-16 sm:h-20 rounded-xl bg-gray-50 hover:bg-gray-900 text-gray-600 hover:text-white border border-gray-200 flex items-center justify-center transition shrink-0 active:scale-95">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"></path></svg>
                    </button>

                    <!-- Daftar Thumbnail Horizontal -->
                    <div class="flex-1 flex flex-row items-center gap-2.5 overflow-x-auto py-1 px-0.5 hide-scrollbar scroll-smooth">
                        <template x-for="(item, idx) in mediaItems" :key="idx">
                            <button type="button"
                                    :id="'media-thumb-' + idx"
                                    @mouseenter="setActiveMedia(idx)"
                                    @click="setActiveMedia(idx)"
                                    :class="activeMediaIndex === idx ? 'border-red-600 ring-2 ring-red-600/20 opacity-100 scale-[1.02]' : 'border-gray-200 opacity-65 hover:opacity-100'"
                                    class="w-16 h-16 sm:w-20 sm:h-20 shrink-0 rounded-xl overflow-hidden bg-gray-50 border-2 p-1 transition-all cursor-pointer relative group">
                                
                                <template x-if="item.type === 'video'">
                                    <div class="w-full h-full rounded-lg overflow-hidden bg-gray-900 relative">
                                        <video :src="item.url + '#t=0.5'" muted preload="metadata" class="w-full h-full object-cover opacity-80"></video>
                                        <span class="absolute inset-0 flex items-center justify-center bg-black/30">
                                            <span class="w-6 h-6 rounded-full bg-red-600 text-white flex items-center justify-center shadow text-[10px]">▶</span>
                                        </span>
                                    </div>
                                </template>

                                <template x-if="item.type === 'image'">
                                    <img :src="item.url" :alt="item.label" class="w-full h-full object-cover rounded-lg">
                                </template>
                            </button>
                        </template>
                    </div>

                    <!-- Tombol Geser Kanan -->
                    <button type="button"
                            @click="nextMedia()"
                            title="Berikutnya"
                            class="w-9 h-16 sm:h-20 rounded-xl bg-gray-50 hover:bg-gray-900 text-gray-600 hover:text-white border border-gray-200 flex items-center justify-center transition shrink-0 active:scale-95">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    </button>
                </div>
            </div>

            <!-- Kolom Kanan: Detail & Interaksi (6 Kolom) -->
            <div class="mt-10 lg:mt-0 lg:col-span-6 space-y-8 bg-white p-6 sm:p-8 rounded-3xl border border-gray-100 shadow-sm">
                
                <!-- Judul, Rating Bintang, Terjual & Harga -->
                <div class="space-y-3 border-b border-gray-100 pb-6">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="inline-block bg-red-50 text-red-600 border border-red-100 text-[10px] font-black uppercase tracking-widest px-3 py-1.5 rounded-lg">
                            {{ optional($produk->category)->name ?? 'Sport Apparel' }}
                        </span>

                        <div class="flex flex-wrap items-center gap-2">
                            <button type="button"
                                    @click="jumpToReviews()"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-50/70 hover:bg-amber-50 border border-amber-200/70 transition group">
                                <div class="flex items-center gap-0.5">
                                    @for($i = 1; $i <= 5; $i++)
                                        <svg class="w-3.5 h-3.5 {{ $totalReviews > 0 && $i <= round($avgRatingFloat) ? 'text-amber-400' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                        </svg>
                                    @endfor
                                </div>
                                <span class="text-xs font-black text-gray-900">{{ $avgRating }}</span>
                                <span class="text-[11px] text-gray-500 font-semibold group-hover:text-red-600 transition">({{ $totalReviews }} ulasan)</span>
                            </button>

                            <span class="inline-flex items-center px-3 py-1.5 rounded-xl bg-gray-100 text-gray-700 text-[11px] font-bold">
                                {{ number_format($soldCount, 0, ',', '.') }} Terjual
                            </span>
                        </div>
                    </div>

                    <h1 class="text-3xl sm:text-4xl font-black text-gray-900 uppercase tracking-tight italic leading-tight">
                        {{ $produk->name }}
                    </h1>
                    
                    <div class="flex items-center gap-4 pt-2">
                        @if($produk->discount_price)
                            <span class="text-3xl font-black text-red-600">Rp {{ number_format($produk->discount_price, 0, ',', '.') }}</span>
                            <span class="text-base text-gray-400 font-bold line-through">Rp {{ number_format($produk->price, 0, ',', '.') }}</span>
                        @else
                            <span class="text-3xl font-black text-gray-900">Rp {{ number_format($produk->price, 0, ',', '.') }}</span>
                        @endif
                    </div>
                </div>

                <!-- Pilihan Varian -->
                @if($hasVariants)
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <label class="text-xs font-black uppercase tracking-wider text-gray-900">Pilih Varian & Ukuran</label>
                        <button type="button" @click="sizeGuideOpen = true" class="text-[11px] text-red-600 font-bold hover:underline flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"></path></svg>
                            Panduan Ukuran
                        </button>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-3">
                        <template x-for="(v, idx) in product.variants" :key="v.id ?? idx">
                            <button type="button"
                                    @click="selectedVariantIndex = idx; if(qty > v.stock && v.stock > 0) qty = Math.max(1, Number(v.stock));"
                                    :disabled="Number(v.stock) == 0"
                                    :class="selectedVariantIndex === idx ? 'border-gray-900 bg-gray-900 text-white shadow-md' : (Number(v.stock) == 0 ? 'border-gray-100 bg-gray-50 text-gray-300 cursor-not-allowed opacity-60' : 'border-gray-200 bg-white text-gray-800 hover:border-gray-900')"
                                    class="p-3.5 rounded-2xl border-2 text-left transition-all flex justify-between items-center">
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-wider" x-text="(v.size || 'All Size') + ' | ' + (v.color || '-')"></p>
                                    <p class="text-[10px] mt-0.5" :class="selectedVariantIndex === idx ? 'text-gray-300' : (Number(v.stock) == 0 ? 'text-red-500 font-bold' : 'text-gray-400')" x-text="Number(v.stock) == 0 ? 'Stok Habis' : 'Stok: ' + v.stock"></p>
                                </div>
                                <span x-show="selectedVariantIndex === idx" class="text-white font-black text-xs">✓</span>
                            </button>
                        </template>
                    </div>
                </div>
                @endif

                <!-- Kuantitas & Aksi -->
                <div class="space-y-6 pt-2">
                    <div class="flex items-center justify-between bg-gray-50 p-3 rounded-2xl border border-gray-100">
                        <div>
                            <label class="text-xs font-black uppercase tracking-wider text-gray-700 block">Kuantitas Pembelian</label>
                            <span class="text-[10px] text-gray-400 font-semibold" x-text="currentMaxStock > 0 ? ('Tersedia ' + currentMaxStock + ' pcs') : 'Stok habis'"></span>
                        </div>
                        <div class="flex items-center gap-3 bg-white border border-gray-200 p-1.5 rounded-xl shadow-sm">
                            <button type="button" @click="if(qty > 1) qty--" class="w-7 h-7 rounded-lg bg-gray-50 border border-gray-200 font-bold hover:bg-gray-100 flex items-center justify-center transition">-</button>
                            <span class="font-black text-sm w-6 text-center" x-text="qty"></span>
                            <button type="button" @click="incrementQty()" class="w-7 h-7 rounded-lg bg-gray-50 border border-gray-200 font-bold hover:bg-gray-100 flex items-center justify-center transition">+</button>
                        </div>
                    </div>

                    <!-- Tombol Aksi -->
                    <div class="flex flex-col sm:flex-row gap-3 pt-2">
                        @auth
                            <button type="button" @click="addToCart()"
                                    :disabled="currentMaxStock <= 0"
                                    class="flex-1 bg-gray-900 hover:bg-gray-800 disabled:bg-gray-300 disabled:cursor-not-allowed text-white text-center py-4 rounded-2xl font-black text-xs uppercase tracking-widest transition shadow-lg flex items-center justify-center gap-2 active:scale-[0.98]">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                                + Keranjang
                            </button>
                            
                            <button type="button" @click="checkoutNow()"
                                    :disabled="currentMaxStock <= 0"
                                    class="flex-1 bg-red-600 hover:bg-red-700 disabled:bg-gray-300 disabled:shadow-none disabled:cursor-not-allowed text-white text-center py-4 rounded-2xl font-black text-xs uppercase tracking-widest transition shadow-lg shadow-red-600/30 flex items-center justify-center gap-2 active:scale-[0.98]">
                                <span>Checkout Sekarang</span>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                            </button>
                        @else
                            <a href="{{ route('login') }}" 
                               @click.prevent="
                                   triggerNotify('warning', 'Login Diperlukan', 'Mengarahkan ke halaman login untuk melanjutkan pembelian...');
                                   setTimeout(() => { window.location.href = '{{ route('login') }}'; }, 1200);
                               "
                               class="w-full bg-red-50 border-2 border-red-200 text-red-600 hover:bg-red-600 hover:text-white text-center py-4 rounded-2xl font-black text-xs uppercase tracking-widest transition shadow-sm">
                                Login untuk Membeli
                            </a>
                        @endauth
                    </div>
                </div>

                <!-- Tabs Informasi Tambahan -->
                <div x-ref="detailTabs" class="pt-6 border-t border-gray-100 scroll-mt-24">
                    <div class="flex gap-6 border-b border-gray-100 mb-6 overflow-x-auto whitespace-nowrap hide-scrollbar">
                        <button @click="activeTab = 'deskripsi'" :class="activeTab === 'deskripsi' ? 'border-b-2 border-red-600 text-red-600 font-black' : 'text-gray-400 hover:text-gray-800 font-bold'" class="pb-3 text-xs uppercase tracking-wider transition">Deskripsi</button>
                        <button @click="activeTab = 'pengiriman'" :class="activeTab === 'pengiriman' ? 'border-b-2 border-red-600 text-red-600 font-black' : 'text-gray-400 hover:text-gray-800 font-bold'" class="pb-3 text-xs uppercase tracking-wider transition">Pengiriman</button>
                        <button @click="activeTab = 'ulasan'" :class="activeTab === 'ulasan' ? 'border-b-2 border-red-600 text-red-600 font-black' : 'text-gray-400 hover:text-gray-800 font-bold'" class="pb-3 text-xs uppercase tracking-wider transition flex items-center gap-1.5">
                            <span>Ulasan</span>
                            <span class="px-2 py-0.5 rounded-full text-[10px]" :class="activeTab === 'ulasan' ? 'bg-red-100 text-red-600' : 'bg-gray-100 text-gray-500'">{{ $totalReviews }}</span>
                        </button>
                    </div>
                    
                    <div x-show="activeTab === 'deskripsi'" class="text-gray-600 text-xs sm:text-sm leading-relaxed space-y-3">
                        <p class="whitespace-pre-line">{{ $produk->description }}</p>
                        <ul class="list-disc pl-4 space-y-1 text-gray-500">
                            <li>100% Original Brand Guaranteed</li>
                            <li>Material premium menyerap keringat</li>
                            <li>Desain ergonomis untuk performa maksimal</li>
                        </ul>
                    </div>

                    <div x-show="activeTab === 'pengiriman'" class="text-gray-600 text-xs sm:text-sm leading-relaxed space-y-3" x-cloak>
                        <p><strong>Pengiriman dari:</strong> Kota Malang, Jawa Timur.</p>
                        <p>Pesanan dikonfirmasi sebelum jam 15:00 WIB dikirim di hari yang sama melalui kurir terpercaya (JNE, SiCepat) atau dapat diambil langsung di Store Malang.</p>
                    </div>

                    <!-- Tab Ulasan Dinamis dari Database -->
                    <div x-show="activeTab === 'ulasan'" class="space-y-5" x-cloak>
                        
                        <!-- Ringkasan Skor & Distribusi Bintang -->
                        <div class="bg-gray-50 p-5 rounded-2xl border border-gray-100 grid grid-cols-1 sm:grid-cols-12 gap-5 items-center">
                            <div class="sm:col-span-5 text-center sm:border-r sm:border-gray-200 sm:pr-4">
                                <div class="flex items-baseline justify-center gap-1">
                                    <span class="text-4xl font-black text-gray-900">{{ $avgRating }}</span>
                                    <span class="text-xs font-bold text-gray-400">/ 5.0</span>
                                </div>
                                <div class="flex items-center justify-center gap-1 my-2">
                                    @for($i = 1; $i <= 5; $i++)
                                        <svg class="w-4 h-4 {{ $totalReviews > 0 && $i <= round($avgRatingFloat) ? 'text-amber-400' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                        </svg>
                                    @endfor
                                </div>
                                <p class="text-[10px] font-black uppercase tracking-widest {{ $totalReviews > 0 ? 'text-emerald-600' : 'text-gray-400' }}">
                                    {{ $totalReviews > 0 ? $totalReviews . ' Ulasan Terverifikasi' : 'Belum Ada Penilaian' }}
                                </p>
                            </div>

                            <div class="sm:col-span-7 space-y-1.5">
                                @foreach([5, 4, 3, 2, 1] as $star)
                                    @php
                                        $countStar = $ratingCounts[$star];
                                        $percentStar = $totalReviews > 0 ? round(($countStar / $totalReviews) * 100) : 0;
                                    @endphp
                                    <button type="button"
                                            @click="selectedRatingFilter = (selectedRatingFilter === {{ $star }} ? 'all' : {{ $star }})"
                                            class="w-full flex items-center gap-2.5 text-left group">
                                        <span class="text-[11px] font-bold text-gray-600 w-7 shrink-0 flex items-center gap-0.5 group-hover:text-red-600">
                                            {{ $star }} <span class="text-amber-400">★</span>
                                        </span>
                                        <div class="flex-1 h-2 bg-gray-200 rounded-full overflow-hidden">
                                            <div class="h-full bg-amber-400 rounded-full transition-all duration-500" style="width: {{ $percentStar }}%"></div>
                                        </div>
                                        <span class="text-[10px] font-bold text-gray-400 w-6 text-right shrink-0">{{ $countStar }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <!-- Filter Bintang -->
                        @if($totalReviews > 0)
                            <div class="flex flex-wrap gap-1.5">
                                <button type="button"
                                        @click="selectedRatingFilter = 'all'"
                                        :class="selectedRatingFilter === 'all' ? 'bg-gray-900 text-white border-gray-900' : 'bg-white text-gray-600 border-gray-200 hover:border-gray-900'"
                                        class="px-3 py-1.5 rounded-xl border text-[11px] font-bold transition">
                                    Semua ({{ $totalReviews }})
                                </button>
                                @foreach([5, 4, 3, 2, 1] as $star)
                                    <button type="button"
                                            @click="selectedRatingFilter = {{ $star }}"
                                            :class="selectedRatingFilter === {{ $star }} ? 'bg-gray-900 text-white border-gray-900' : 'bg-white text-gray-600 border-gray-200 hover:border-gray-900'"
                                            class="px-3 py-1.5 rounded-xl border text-[11px] font-bold transition">
                                        {{ $star }} ★ ({{ $ratingCounts[$star] }})
                                    </button>
                                @endforeach
                            </div>
                        @endif

                        <!-- Daftar Komentar Pembeli -->
                        <div class="space-y-3 max-h-96 overflow-y-auto pr-1">
                            @forelse($reviews as $review)
                                @php
                                    $reviewerName = optional($review->user)->name ?? 'Pelanggan Sport Mania';
                                    $reviewerInitial = strtoupper(substr($reviewerName, 0, 1));
                                    $reviewRating = (int) $review->rating;
                                @endphp
                                <div x-show="selectedRatingFilter === 'all' || selectedRatingFilter === {{ $reviewRating }}"
                                     class="bg-gray-50/80 p-4 rounded-2xl border border-gray-100 space-y-2.5">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="flex items-center gap-3">
                                            <div class="w-9 h-9 rounded-xl bg-gray-900 text-white flex items-center justify-center font-black text-xs shrink-0">
                                                {{ $reviewerInitial }}
                                            </div>
                                            <div>
                                                <div class="flex flex-wrap items-center gap-1.5">
                                                    <h4 class="text-xs font-bold text-gray-900">{{ $reviewerName }}</h4>
                                                    <span class="inline-flex items-center gap-1 bg-emerald-50 text-emerald-700 border border-emerald-200/80 text-[9px] font-black uppercase px-2 py-0.5 rounded-md">
                                                        ✓ Pembeli Terverifikasi
                                                    </span>
                                                </div>
                                                <span class="text-[10px] text-gray-400 font-medium block mt-0.5">
                                                    {{ optional($review->created_at)->format('d M Y • H:i') ?? 'Baru saja' }}
                                                </span>
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-0.5 bg-white px-2.5 py-1 rounded-xl border border-gray-200/80 shrink-0" title="Rating {{ $reviewRating }}/5">
                                            @for($s = 1; $s <= 5; $s++)
                                                <svg class="w-3.5 h-3.5 {{ $s <= $reviewRating ? 'text-amber-400' : 'text-gray-200' }}" fill="currentColor" viewBox="0 0 20 20">
                                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                                </svg>
                                            @endfor
                                        </div>
                                    </div>

                                    @if(filled($review->comment))
                                        <p class="text-xs text-gray-700 leading-relaxed font-medium pl-12">
                                            "{{ $review->comment }}"
                                        </p>
                                    @else
                                        <p class="text-[11px] text-gray-400 italic pl-12">
                                            Pembeli memberikan rating bintang tanpa ulasan tertulis.
                                        </p>
                                    @endif
                                </div>
                            @empty
                                <div class="text-center py-8 bg-gray-50 rounded-2xl border border-dashed border-gray-200 px-4">
                                    <p class="text-xs font-bold text-gray-500 uppercase tracking-wider">Belum ada ulasan untuk produk ini.</p>
                                    <p class="text-[11px] text-gray-400 mt-1">Ulasan akan muncul otomatis setelah pembeli menyelesaikan pesanan dan memberikan penilaian.</p>
                                </div>
                            @endforelse

                            @if($totalReviews > 0)
                                <div x-show="selectedRatingFilter !== 'all' && (ratingCounts[selectedRatingFilter] || 0) === 0"
                                     x-cloak
                                     class="text-center py-6 bg-gray-50 rounded-2xl border border-dashed border-gray-200">
                                    <p class="text-xs font-bold text-gray-400" x-text="'Belum ada ulasan dengan rating ' + selectedRatingFilter + ' bintang.'"></p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <!-- Section Produk Serupa -->
    @if(isset($relatedProducts) && $relatedProducts->count() > 0)
    <section class="bg-white py-16 border-t border-gray-100 mt-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
            <div class="text-center">
                <h3 class="text-2xl sm:text-3xl font-black uppercase tracking-tight text-gray-900">Produk <span class="text-red-600 italic">Serupa</span></h3>
                <p class="text-gray-400 text-xs mt-1 font-medium">Lengkapi performa dan gayamu dengan koleksi pilihan lainnya.</p>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 sm:gap-6">
                @foreach($relatedProducts as $rel)
                @php
                    $relImg = $rel->image 
                        ? (filter_var($rel->image, FILTER_VALIDATE_URL) ? $rel->image : asset('storage/' . $rel->image)) 
                        : asset('image/LOGO.jpeg');
                @endphp
                <a href="{{ route('produk.show', $rel->id) }}" class="bg-gray-50 rounded-2xl p-4 shadow-sm hover:shadow-xl hover:bg-white transition-all duration-300 group flex flex-col border border-transparent hover:border-red-100">
                    <div class="w-full aspect-[4/5] bg-white rounded-xl mb-3 flex items-center justify-center overflow-hidden relative border border-gray-100">
                        <img src="{{ $relImg }}" alt="{{ $rel->name }}" class="w-full h-full object-cover mix-blend-multiply group-hover:scale-110 transition-transform duration-500">
                    </div>
                    <div class="space-y-1 flex-grow">
                        <h3 class="font-bold text-gray-900 text-xs sm:text-sm truncate">{{ $rel->name }}</h3>
                        <p class="text-[10px] text-gray-400 uppercase font-bold tracking-widest">{{ optional($rel->category)->name ?? 'Apparel' }}</p>
                    </div>
                    <div class="mt-3 pt-3 border-t border-gray-200/60 flex items-center justify-between">
                        @if($rel->discount_price)
                            <p class="font-black text-red-600 text-sm">Rp {{ number_format($rel->discount_price, 0, ',', '.') }}</p>
                        @else
                            <p class="font-black text-gray-900 text-sm">Rp {{ number_format($rel->price, 0, ',', '.') }}</p>
                        @endif
                        <span class="w-7 h-7 rounded-full bg-red-50 text-red-600 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">↗</span>
                    </div>
                </a>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 py-10 mt-auto">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <img src="{{ asset('image/LOGO.jpeg') }}" alt="Logo" class="h-10 w-auto mx-auto mb-4 object-contain grayscale opacity-60">
            <p class="text-gray-500 text-[10px] sm:text-xs font-medium">© 2026 Eka Bagus Karisma & Aditya Panji Pratama. Proyek Tugas Akhir S1 Pendidikan Teknik Informatika.</p>
        </div>
    </footer>

    <!-- Modal Panduan Ukuran -->
    <div x-show="sizeGuideOpen"
         x-cloak
         @keydown.escape.window="sizeGuideOpen = false"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/70 backdrop-blur-sm">
        <div @click.outside="sizeGuideOpen = false"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             class="bg-white rounded-3xl max-w-md w-full overflow-hidden shadow-2xl border border-gray-100">
            <div class="bg-gray-900 text-white px-6 py-4 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-black uppercase tracking-wider">Panduan Ukuran Standar</h3>
                    <p class="text-[11px] text-gray-400">Toleransi jahitan ± 1–2 cm</p>
                </div>
                <button type="button" @click="sizeGuideOpen = false" class="w-8 h-8 rounded-xl bg-gray-800 hover:bg-red-600 text-gray-300 hover:text-white flex items-center justify-center transition">✕</button>
            </div>
            <div class="p-6">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-gray-200 text-[10px] font-black uppercase tracking-wider text-gray-500">
                            <th class="py-2.5">Ukuran</th>
                            <th class="py-2.5 text-center">Lebar Dada</th>
                            <th class="py-2.5 text-center">Panjang Badan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 font-semibold text-gray-800">
                        <tr><td class="py-2.5 font-black text-red-600">S</td><td class="py-2.5 text-center">48 cm</td><td class="py-2.5 text-center">68 cm</td></tr>
                        <tr><td class="py-2.5 font-black text-red-600">M</td><td class="py-2.5 text-center">50 cm</td><td class="py-2.5 text-center">70 cm</td></tr>
                        <tr><td class="py-2.5 font-black text-red-600">L</td><td class="py-2.5 text-center">52 cm</td><td class="py-2.5 text-center">72 cm</td></tr>
                        <tr><td class="py-2.5 font-black text-red-600">XL</td><td class="py-2.5 text-center">54 cm</td><td class="py-2.5 text-center">74 cm</td></tr>
                        <tr><td class="py-2.5 font-black text-red-600">XXL</td><td class="py-2.5 text-center">57 cm</td><td class="py-2.5 text-center">76 cm</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Toast Notification Component -->
    <div x-data="{ 
            show: false, 
            type: 'success', 
            title: 'Berhasil!', 
            message: '', 
            timeout: null,
            barKey: 0
         }"
         @notify.window="
            type = $event.detail.type || 'success';
            title = $event.detail.title || 'Notifikasi';
            message = typeof $event.detail === 'string' ? $event.detail : $event.detail.message;
            barKey++;
            show = true;
            clearTimeout(timeout);
            timeout = setTimeout(() => { show = false }, 3000);
         "
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