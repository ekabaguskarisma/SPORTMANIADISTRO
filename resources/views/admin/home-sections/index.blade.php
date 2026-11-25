<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Tampilan Beranda | {{ $siteIdentity['site_name'] ?? 'Sport Mania' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
@php
    $rawIdentity = $siteIdentity ?? [
        'site_name' => 'SPORT MANIA',
        'site_tagline' => 'DISTRO ADMIN',
        'site_code' => 'SM',
        'site_logo' => null,
    ];
    $headerLogoUrl = !empty($rawIdentity['site_logo'])
        ? (filter_var($rawIdentity['site_logo'], FILTER_VALIDATE_URL) ? $rawIdentity['site_logo'] : asset('storage/' . ltrim($rawIdentity['site_logo'], '/')))
        : null;

    $rawStoreLocation = $storeLocation ?? [
        'free_shipping_enabled' => true,
        'min_order_free_shipping_enabled' => true,
        'min_order_free_shipping_amount' => 250000,
        'store_address' => 'Jl. MT. Haryono No.129, Dinoyo, Kec. Lowokwaru, Kota Malang, Jawa Timur 65144',
        'store_lat' => -7.9398,
        'store_lng' => 112.6083,
        'free_shipping_radius_km' => 10,
        'google_maps_url' => 'https://maps.google.com/?q=-7.9398,112.6083',
    ];
@endphp
<body class="bg-slate-50 text-slate-900 antialiased"
      x-data="{
          activeTab: 'identity',
          mapInstance: null,
          mapMarker: null,
          mapCircle: null,
          location: {
              free_shipping_enabled: @js((bool) ($rawStoreLocation['free_shipping_enabled'] ?? true)),
              min_order_free_shipping_enabled: @js((bool) ($rawStoreLocation['min_order_free_shipping_enabled'] ?? true)),
              min_order_free_shipping_amount: @js((float) ($rawStoreLocation['min_order_free_shipping_amount'] ?? 250000)),
              store_address: @js($rawStoreLocation['store_address'] ?? 'Jl. MT. Haryono No.129, Dinoyo, Kec. Lowokwaru, Kota Malang, Jawa Timur 65144'),
              store_lat: @js((float) ($rawStoreLocation['store_lat'] ?? -7.9398)),
              store_lng: @js((float) ($rawStoreLocation['store_lng'] ?? 112.6083)),
              free_shipping_radius_km: @js((float) ($rawStoreLocation['free_shipping_radius_km'] ?? 10)),
              google_maps_url: @js($rawStoreLocation['google_maps_url'] ?? '')
          },
          initStoreMap() {
              if (typeof L === 'undefined') return;
              this.$nextTick(() => {
                  const container = document.getElementById('admin-store-map');
                  if (!container) return;

                  const lat = Number(this.location.store_lat) || -7.9398;
                  const lng = Number(this.location.store_lng) || 112.6083;
                  const radiusMeters = this.location.free_shipping_enabled
                      ? (Number(this.location.free_shipping_radius_km) || 10) * 1000
                      : 0;

                  if (!this.mapInstance) {
                      this.mapInstance = L.map(container).setView([lat, lng], 13);
                      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                          maxZoom: 19,
                          attribution: '© OpenStreetMap'
                      }).addTo(this.mapInstance);

                      this.mapMarker = L.marker([lat, lng], { draggable: true }).addTo(this.mapInstance);
                      this.mapCircle = L.circle([lat, lng], {
                          color: '#dc2626',
                          fillColor: '#ef4444',
                          fillOpacity: 0.15,
                          radius: radiusMeters
                      }).addTo(this.mapInstance);

                      this.mapMarker.on('dragend', (e) => {
                          const pos = e.target.getLatLng();
                          this.updateCoordinates(pos.lat, pos.lng, false);
                      });

                      this.mapInstance.on('click', (e) => {
                          this.updateCoordinates(e.latlng.lat, e.latlng.lng, false);
                      });
                  }

                  setTimeout(() => {
                      this.mapInstance.invalidateSize();
                      this.syncMapShape(true);
                  }, 120);
              });
          },
          updateCoordinates(lat, lng, recenter = true) {
              const fixedLat = Number(Number(lat).toFixed(7));
              const fixedLng = Number(Number(lng).toFixed(7));
              this.location.store_lat = fixedLat;
              this.location.store_lng = fixedLng;
              this.location.google_maps_url = `https://maps.google.com/?q=${fixedLat},${fixedLng}`;
              this.syncMapShape(recenter);
          },
          syncMapShape(recenter = false) {
              if (!this.mapInstance || !this.mapMarker || !this.mapCircle) return;
              const lat = Number(this.location.store_lat) || -7.9398;
              const lng = Number(this.location.store_lng) || 112.6083;
              const radiusMeters = this.location.free_shipping_enabled
                  ? Math.max(0, Number(this.location.free_shipping_radius_km) || 0) * 1000
                  : 0;

              this.mapMarker.setLatLng([lat, lng]);
              this.mapCircle.setLatLng([lat, lng]);
              this.mapCircle.setRadius(radiusMeters);
              if (recenter) {
                  this.mapInstance.setView([lat, lng], 13);
              }
          },
          useCurrentLocation() {
              if (!navigator.geolocation) {
                  alert('Browser tidak mendukung deteksi lokasi GPS.');
                  return;
              }
              navigator.geolocation.getCurrentPosition(
                  (pos) => {
                      this.updateCoordinates(pos.coords.latitude, pos.coords.longitude, true);
                  },
                  () => {
                      alert('Gagal mengambil lokasi GPS. Pastikan izin lokasi browser aktif.');
                  }
              );
          },
          identity: {
              site_name: @js($rawIdentity['site_name'] ?? 'SPORT MANIA'),
              site_tagline: @js($rawIdentity['site_tagline'] ?? 'DISTRO ADMIN'),
              site_code: @js($rawIdentity['site_code'] ?? 'SM'),
              site_logo: @js($rawIdentity['site_logo'] ?? ''),
              previewUrl: @js($headerLogoUrl ?? ''),
              remove_logo: false
          },
          handleIdentityLogoChange(event) {
              const file = event.target.files[0];
              if (!file) return;
              this.identity.remove_logo = false;
              this.identity.previewUrl = URL.createObjectURL(file);
          },
          clearIdentityLogo() {
              this.identity.site_logo = '';
              this.identity.previewUrl = '';
              this.identity.remove_logo = true;
              const fileInput = document.getElementById('identity-logo-input');
              if (fileInput) fileInput.value = '';
          },
          resolvePreview(raw) {
              if (!raw) return '';
              if (raw.startsWith('http://') || raw.startsWith('https://') || raw.startsWith('blob:')) return raw;
              return '/storage/' + raw.replace(/^\/+/, '').replace(/^storage\//, '');
          },
          brands: (@js($trustedBrands) || []).map(b => ({
              code: b.code || '',
              name: b.name || '',
              subtitle: b.subtitle || b.description || '',
              logo: b.logo || '',
              previewUrl: b.logo
                  ? (b.logo.startsWith('http') ? b.logo : '/storage/' + b.logo.replace(/^\/+/, '').replace(/^storage\//, ''))
                  : '',
              remove_logo: false
          })),
          slides: @js($heroSlides),
          categories: @js($curatedCategories),
          showcase: @js($featuredShowcase),

          addBrand() {
              this.brands.push({
                  code: 'NEW',
                  name: 'BRAND BARU',
                  subtitle: 'Partner Resmi',
                  logo: '',
                  previewUrl: '',
                  remove_logo: false
              });
          },
          removeBrand(idx) {
              if (this.brands.length > 1) this.brands.splice(idx, 1);
          },
          handleBrandLogoChange(event, idx) {
              const file = event.target.files[0];
              if (!file) return;
              this.brands[idx].remove_logo = false;
              this.brands[idx].previewUrl = URL.createObjectURL(file);
          },
          clearBrandLogo(idx) {
              this.brands[idx].logo = '';
              this.brands[idx].previewUrl = '';
              this.brands[idx].remove_logo = true;
              const fileInput = document.getElementById('brand-logo-input-' + idx);
              if (fileInput) fileInput.value = '';
          },

          addSlide() {
              this.slides.push({
                  badge: '#NEW ARRIVAL',
                  kicker: 'SPORT COLLECTION',
                  title: 'KOLEKSI TERBARU',
                  description: 'Deskripsi singkat campaign terbaru Sport Mania Distro.',
                  cta_text: 'JELAJAHI KOLEKSI →',
                  cta_url: '#katalog',
                  image: ''
              });
          },
          removeSlide(idx) {
              if (this.slides.length > 1) this.slides.splice(idx, 1);
          },

          addCategory() {
              this.categories.push({
                  tag: 'NEW CATEGORY',
                  title: 'KATEGORI BARU',
                  subtitle: 'Pilihan terbaik untukmu',
                  url: '#katalog',
                  image: ''
              });
          },
          removeCategory(idx) {
              if (this.categories.length > 1) this.categories.splice(idx, 1);
          }
      }">

    <!-- Top Bar -->
    <header class="sticky top-0 z-30 bg-slate-950 text-white border-b border-slate-800">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2.5">
                    <template x-if="identity.previewUrl">
                        <img :src="identity.previewUrl" alt="Logo" class="w-8 h-8 rounded-lg object-cover bg-white p-0.5">
                    </template>
                    <template x-if="!identity.previewUrl">
                        <div class="w-8 h-8 rounded-lg bg-red-600 flex items-center justify-center font-black italic text-sm" x-text="(identity.site_code || 'SM').slice(0, 4)"></div>
                    </template>
                    <span class="font-black tracking-tighter uppercase italic text-base" x-text="identity.site_name || 'SPORT MANIA'"></span>
                </a>
                <span class="text-slate-600">/</span>
                <span class="text-xs font-bold text-slate-300">Edit Tampilan Beranda</span>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ url('/') }}" target="_blank"
                   class="text-xs font-bold bg-slate-800 hover:bg-slate-700 text-slate-200 px-3.5 py-2 rounded-xl transition">
                    Lihat Beranda ↗
                </a>
                <a href="{{ route('admin.dashboard') }}"
                   class="text-xs font-bold bg-red-600 hover:bg-red-700 text-white px-3.5 py-2 rounded-xl transition">
                    ← Dashboard
                </a>
            </div>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-4 sm:px-6 py-8">
        <div class="mb-6 flex flex-col sm:flex-row sm:items-end justify-between gap-4">
            <div>
                <span class="text-[10px] font-black uppercase tracking-widest text-red-600">Konten & Identitas Toko</span>
                <h1 class="text-2xl font-black uppercase tracking-tight text-slate-900 mt-0.5">
                    Edit Tampilan <span class="text-red-600">Beranda & Logo</span>
                </h1>
                <p class="text-xs text-slate-500 font-medium mt-1">
                    Ubah logo utama toko, nama brand, kartu brand terpercaya, hero banner slider, kategori pilihan, showcase, dan lokasi store.
                </p>
            </div>
        </div>

        @if(session('success'))
            <div class="mb-6 bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs font-bold p-4 rounded-2xl">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="mb-6 bg-rose-50 border border-rose-200 text-rose-700 text-xs font-bold p-4 rounded-2xl">
                <p class="uppercase tracking-wider mb-1">Periksa kembali isian form:</p>
                <ul class="list-disc pl-4 space-y-0.5 font-semibold">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Navigasi Bagian -->
        <div class="flex flex-wrap gap-2 mb-6">
            <button type="button" @click="activeTab = 'identity'"
                    :class="activeTab === 'identity' ? 'bg-red-600 text-white shadow-sm' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-100'"
                    class="px-4 py-2.5 rounded-xl text-xs font-black uppercase tracking-wider transition">
                0. Logo & Identitas Toko
            </button>
            <button type="button" @click="activeTab = 'brands'"
                    :class="activeTab === 'brands' ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-100'"
                    class="px-4 py-2.5 rounded-xl text-xs font-black uppercase tracking-wider transition">
                1. Trusted Brands
            </button>
            <button type="button" @click="activeTab = 'slides'"
                    :class="activeTab === 'slides' ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-100'"
                    class="px-4 py-2.5 rounded-xl text-xs font-black uppercase tracking-wider transition">
                2. Hero Banner Slider
            </button>
            <button type="button" @click="activeTab = 'categories'"
                    :class="activeTab === 'categories' ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-100'"
                    class="px-4 py-2.5 rounded-xl text-xs font-black uppercase tracking-wider transition">
                3. Curated Categories
            </button>
            <button type="button" @click="activeTab = 'showcase'"
                    :class="activeTab === 'showcase' ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-100'"
                    class="px-4 py-2.5 rounded-xl text-xs font-black uppercase tracking-wider transition">
                4. Featured Showcase
            </button>
            <button type="button" @click="activeTab = 'location'; initStoreMap()"
                    :class="activeTab === 'location' ? 'bg-red-600 text-white shadow-sm' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-100'"
                    class="px-4 py-2.5 rounded-xl text-xs font-black uppercase tracking-wider transition">
                5. Lokasi Store & Ongkir
            </button>
        </div>

        <form action="{{ route('admin.home-sections.update') }}" method="POST" enctype="multipart/form-data"
              class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 sm:p-8 space-y-8">
            @csrf
            @method('PUT')

            <!-- TAB 0: LOGO & IDENTITAS TOKO -->
            <section x-show="activeTab === 'identity'" x-cloak class="space-y-6">
                <div class="border-b border-slate-100 pb-4">
                    <h2 class="text-base font-black uppercase text-slate-900">Logo Utama & Nama Toko</h2>
                    <p class="text-xs text-slate-500">Pengaturan ini otomatis mengganti logo kotak merah <strong>SM</strong> dan nama <strong>SPORT MANIA DISTRO ADMIN</strong> di sidebar admin maupun halaman website.</p>
                </div>

                <!-- Live Preview Sidebar Admin -->
                <div class="p-5 rounded-2xl bg-slate-950 text-white border border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 block mb-2">Preview Tampilan di Sidebar Admin</span>
                        <div class="inline-flex items-center gap-3 px-4 py-3 rounded-2xl bg-slate-900 border border-slate-800">
                            <template x-if="identity.previewUrl">
                                <img :src="identity.previewUrl" alt="Logo Preview" class="w-10 h-10 rounded-xl object-cover bg-white p-0.5 shadow-lg">
                            </template>
                            <template x-if="!identity.previewUrl">
                                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-red-600 to-rose-700 flex items-center justify-center font-black text-white text-sm shadow-lg shadow-red-600/30"
                                     x-text="(identity.site_code || 'SM').slice(0, 4)"></div>
                            </template>
                            <div class="leading-tight">
                                <span class="block font-black text-white tracking-tight text-sm uppercase" x-text="identity.site_name || 'SPORT MANIA'"></span>
                                <span class="block text-[10px] font-bold tracking-widest uppercase text-red-500" x-text="identity.site_tagline || 'DISTRO ADMIN'"></span>
                            </div>
                        </div>
                    </div>
                    <p class="text-[11px] text-slate-400 max-w-xs">
                        Jika tidak mengunggah gambar logo, sistem akan menampilkan inisial kode monogram berlatar merah.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Nama Toko / Brand Utama *</label>
                        <input type="text" name="identity[site_name]" x-model="identity.site_name" required
                               placeholder="Contoh: SPORT MANIA"
                               class="w-full text-xs font-black bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Subjudul Panel Admin</label>
                        <input type="text" name="identity[site_tagline]" x-model="identity.site_tagline"
                               placeholder="Contoh: DISTRO ADMIN"
                               class="w-full text-xs font-bold bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Kode Monogram Fallback (Maks 4 Huruf) *</label>
                        <input type="text" name="identity[site_code]" x-model="identity.site_code" maxlength="4" required
                               placeholder="Contoh: SM"
                               class="w-full uppercase text-xs font-black bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5">
                    </div>
                </div>

                <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 flex flex-col sm:flex-row items-start sm:items-center gap-4">
                    <div class="w-16 h-16 rounded-2xl bg-white border border-slate-200 flex items-center justify-center overflow-hidden shrink-0">
                        <template x-if="identity.previewUrl">
                            <img :src="identity.previewUrl" alt="Logo Toko" class="max-w-full max-h-full object-contain p-1">
                        </template>
                        <template x-if="!identity.previewUrl">
                            <span class="text-sm font-black text-red-600 uppercase" x-text="(identity.site_code || 'SM').slice(0, 4)"></span>
                        </template>
                    </div>

                    <div class="flex-1 w-full">
                        <div class="flex items-center justify-between gap-2 mb-1.5">
                            <label class="block text-xs font-bold text-slate-700">Upload Gambar Logo Toko (PNG, JPG, WEBP, SVG)</label>
                            <button type="button"
                                    x-show="identity.previewUrl"
                                    @click="clearIdentityLogo()"
                                    class="text-xs font-bold text-rose-600 hover:underline">
                                Hapus Logo & Pakai Inisial
                            </button>
                        </div>
                        <input type="hidden" name="identity[site_logo]" x-model="identity.site_logo">
                        <input type="hidden" name="identity[remove_logo]" :value="identity.remove_logo ? 1 : 0">
                        <input type="file"
                               id="identity-logo-input"
                               name="identity[logo_file]"
                               accept="image/*"
                               @change="handleIdentityLogoChange($event)"
                               class="w-full text-xs bg-white border border-slate-200 rounded-xl p-2 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-slate-900 file:text-white hover:file:bg-red-600">
                        <p class="text-[11px] text-slate-400 mt-1">Disarankan rasio persegi (1:1) dengan background transparan (PNG/SVG) agar terlihat tajam di header maupun sidebar.</p>
                    </div>
                </div>
            </section>

            <!-- TAB 1: TRUSTED BRANDS -->
            <section x-show="activeTab === 'brands'" x-cloak class="space-y-4">
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-4">
                    <div>
                        <h2 class="text-base font-black uppercase text-slate-900">Dipercaya Oleh Brand Olahraga Terbaik</h2>
                        <p class="text-xs text-slate-500">Kartu partner / brand yang tampil di beranda. Upload logo gambar atau gunakan kode singkat.</p>
                    </div>
                    <button type="button" @click="addBrand()"
                            class="bg-slate-900 hover:bg-red-600 text-white text-xs font-bold px-4 py-2 rounded-xl transition">
                        + Tambah Brand
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <template x-for="(brand, idx) in brands" :key="idx">
                        <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-[11px] font-black uppercase text-slate-500">Kartu Brand #<span x-text="idx + 1"></span></span>
                                <button type="button" x-show="brands.length > 1" @click="removeBrand(idx)"
                                        class="text-xs font-bold text-rose-600 hover:underline">Hapus</button>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-600 mb-1">Kode / Logo Singkat</label>
                                    <input type="text" :name="`brands[${idx}][code]`" x-model="brand.code" required
                                           class="w-full text-xs font-bold bg-white border border-slate-200 rounded-xl px-3 py-2">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-600 mb-1">Nama Brand</label>
                                    <input type="text" :name="`brands[${idx}][name]`" x-model="brand.name" required
                                           class="w-full text-xs font-bold bg-white border border-slate-200 rounded-xl px-3 py-2">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-600 mb-1">Keterangan</label>
                                    <input type="text" :name="`brands[${idx}][subtitle]`" x-model="brand.subtitle"
                                           class="w-full text-xs font-semibold bg-white border border-slate-200 rounded-xl px-3 py-2">
                                </div>
                            </div>

                            <!-- Upload Logo Brand -->
                            <div class="pt-3 border-t border-slate-200/80 flex items-center gap-3">
                                <div class="w-12 h-12 rounded-xl bg-white border border-slate-200 flex items-center justify-center overflow-hidden shrink-0">
                                    <template x-if="brand.previewUrl">
                                        <img :src="brand.previewUrl" :alt="brand.name" class="max-w-full max-h-full object-contain p-1">
                                    </template>
                                    <template x-if="!brand.previewUrl">
                                        <span class="text-[11px] font-black text-slate-700 uppercase" x-text="(brand.code || 'SM').slice(0, 4)"></span>
                                    </template>
                                </div>

                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between gap-2 mb-1">
                                        <label class="block text-[11px] font-bold text-slate-600">Upload Logo Gambar (Opsional)</label>
                                        <button type="button"
                                                x-show="brand.previewUrl"
                                                @click="clearBrandLogo(idx)"
                                                class="text-[10px] font-bold text-rose-600 hover:underline">
                                            Hapus Logo
                                        </button>
                                    </div>
                                    <input type="hidden" :name="`brands[${idx}][logo]`" x-model="brand.logo">
                                    <input type="hidden" :name="`brands[${idx}][remove_logo]`" :value="brand.remove_logo ? 1 : 0">
                                    <input type="file"
                                           :id="`brand-logo-input-${idx}`"
                                           :name="`brands[${idx}][logo_file]`"
                                           accept="image/*"
                                           @change="handleBrandLogoChange($event, idx)"
                                           class="w-full text-xs bg-white border border-slate-200 rounded-xl p-1.5 file:mr-2 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-[11px] file:font-bold file:bg-slate-900 file:text-white hover:file:bg-red-600">
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </section>

            <!-- TAB 2: HERO BANNER SLIDER -->
            <section x-show="activeTab === 'slides'" x-cloak class="space-y-4">
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-4">
                    <div>
                        <h2 class="text-base font-black uppercase text-slate-900">Hero Banner Slider Utama</h2>
                        <p class="text-xs text-slate-500">Atur teks campaign (seperti PRO RUNNING 2026 / ELITE TRAINING), tombol CTA, dan gambar latar.</p>
                    </div>
                    <button type="button" @click="addSlide()"
                            class="bg-slate-900 hover:bg-red-600 text-white text-xs font-bold px-4 py-2 rounded-xl transition">
                        + Tambah Slide
                    </button>
                </div>

                <div class="space-y-4">
                    <template x-for="(slide, idx) in slides" :key="idx">
                        <div class="p-5 rounded-2xl bg-slate-50 border border-slate-200 space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-black uppercase text-red-600">Slide #<span x-text="idx + 1"></span></span>
                                <button type="button" x-show="slides.length > 1" @click="removeSlide(idx)"
                                        class="text-xs font-bold text-rose-600 hover:underline">Hapus Slide</button>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-600 mb-1">Badge Atas</label>
                                    <input type="text" :name="`slides[${idx}][badge]`" x-model="slide.badge"
                                           class="w-full text-xs font-bold bg-white border border-slate-200 rounded-xl px-3 py-2">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-600 mb-1">Label Kecil (Kicker)</label>
                                    <input type="text" :name="`slides[${idx}][kicker]`" x-model="slide.kicker"
                                           class="w-full text-xs font-bold bg-white border border-slate-200 rounded-xl px-3 py-2">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-600 mb-1">Judul Utama</label>
                                    <input type="text" :name="`slides[${idx}][title]`" x-model="slide.title" required
                                           class="w-full text-xs font-black bg-white border border-slate-200 rounded-xl px-3 py-2">
                                </div>
                            </div>

                            <div>
                                <label class="block text-[11px] font-bold text-slate-600 mb-1">Deskripsi Singkat</label>
                                <textarea :name="`slides[${idx}][description]`" x-model="slide.description" rows="2"
                                          class="w-full text-xs font-medium bg-white border border-slate-200 rounded-xl px-3 py-2"></textarea>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-600 mb-1">Teks Tombol CTA</label>
                                    <input type="text" :name="`slides[${idx}][cta_text]`" x-model="slide.cta_text"
                                           class="w-full text-xs font-bold bg-white border border-slate-200 rounded-xl px-3 py-2">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-600 mb-1">Link Tujuan CTA</label>
                                    <input type="text" :name="`slides[${idx}][cta_url]`" x-model="slide.cta_url"
                                           class="w-full text-xs font-semibold bg-white border border-slate-200 rounded-xl px-3 py-2">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-600 mb-1">Upload Gambar Slide (Opsional)</label>
                                    <input type="hidden" :name="`slides[${idx}][image]`" x-model="slide.image">
                                    <input type="file" :name="`slides[${idx}][image_file]`" accept="image/*"
                                           class="w-full text-xs bg-white border border-slate-200 rounded-xl p-1.5">
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </section>

            <!-- TAB 3: CURATED CATEGORIES -->
            <section x-show="activeTab === 'categories'" x-cloak class="space-y-4">
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-4">
                    <div>
                        <h2 class="text-base font-black uppercase text-slate-900">Select a Category to Get Started</h2>
                        <p class="text-xs text-slate-500">Kartu kategori utama (WOMEN, MEN, SPORTS, LIFESTYLE).</p>
                    </div>
                    <button type="button" @click="addCategory()"
                            class="bg-slate-900 hover:bg-red-600 text-white text-xs font-bold px-4 py-2 rounded-xl transition">
                        + Tambah Kategori
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <template x-for="(cat, idx) in categories" :key="idx">
                        <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-black uppercase text-slate-700">Kategori #<span x-text="idx + 1"></span></span>
                                <button type="button" x-show="categories.length > 1" @click="removeCategory(idx)"
                                        class="text-xs font-bold text-rose-600 hover:underline">Hapus</button>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-600 mb-1">Tag Kecil</label>
                                    <input type="text" :name="`categories[${idx}][tag]`" x-model="cat.tag"
                                           class="w-full text-xs font-bold bg-white border border-slate-200 rounded-xl px-3 py-2">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-600 mb-1">Judul Kategori</label>
                                    <input type="text" :name="`categories[${idx}][title]`" x-model="cat.title" required
                                           class="w-full text-xs font-black bg-white border border-slate-200 rounded-xl px-3 py-2">
                                </div>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-600 mb-1">Subjudul</label>
                                    <input type="text" :name="`categories[${idx}][subtitle]`" x-model="cat.subtitle"
                                           class="w-full text-xs font-medium bg-white border border-slate-200 rounded-xl px-3 py-2">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-600 mb-1">Link Tujuan</label>
                                    <input type="text" :name="`categories[${idx}][url]`" x-model="cat.url"
                                           class="w-full text-xs font-semibold bg-white border border-slate-200 rounded-xl px-3 py-2">
                                </div>
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-slate-600 mb-1">Upload Gambar Kategori (Opsional)</label>
                                <input type="hidden" :name="`categories[${idx}][image]`" x-model="cat.image">
                                <input type="file" :name="`categories[${idx}][image_file]`" accept="image/*"
                                       class="w-full text-xs bg-white border border-slate-200 rounded-xl p-1.5">
                            </div>
                        </div>
                    </template>
                </div>
            </section>

            <!-- TAB 4: FEATURED SHOWCASE -->
            <section x-show="activeTab === 'showcase'" x-cloak class="space-y-4">
                <div class="border-b border-slate-100 pb-4">
                    <h2 class="text-base font-black uppercase text-slate-900">Featured Showcase (Koleksi Eksklusif)</h2>
                    <p class="text-xs text-slate-500">Banner gelap spotlight di bawah bagian kategori.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Badge</label>
                        <input type="text" name="showcase[badge]" x-model="showcase.badge"
                               class="w-full text-xs font-bold bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Judul Showcase</label>
                        <input type="text" name="showcase[title]" x-model="showcase.title" required
                               class="w-full text-xs font-black bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5">
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-600 mb-1">Deskripsi</label>
                    <textarea name="showcase[description]" x-model="showcase.description" rows="3"
                              class="w-full text-xs font-medium bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5"></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Teks Tombol</label>
                        <input type="text" name="showcase[cta_text]" x-model="showcase.cta_text"
                               class="w-full text-xs font-bold bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Link Tujuan</label>
                        <input type="text" name="showcase[cta_url]" x-model="showcase.cta_url"
                               class="w-full text-xs font-semibold bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Upload Gambar Showcase (Opsional)</label>
                        <input type="hidden" name="showcase[image]" x-model="showcase.image">
                        <input type="file" name="showcase[image_file]" accept="image/*"
                               class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl p-2">
                    </div>
                </div>
            </section>

            <!-- TAB 5: LOKASI STORE & GRATIS ONGKIR -->
            <section x-show="activeTab === 'location'" x-cloak class="space-y-6">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-4">
                    <div>
                        <h2 class="text-base font-black uppercase text-slate-900">Titik Lokasi Store & Pengaturan Promo Ongkir</h2>
                        <p class="text-xs text-slate-500">Atur promo gratis ongkir berdasarkan radius Malang Kota maupun nominal minimal belanja pelanggan.</p>
                    </div>
                    <button type="button" @click="useCurrentLocation()"
                            class="bg-slate-900 hover:bg-red-600 text-white text-xs font-bold px-4 py-2.5 rounded-xl transition shrink-0">
                        📍 Gunakan Lokasi GPS Saya
                    </button>
                </div>

                <!-- 2 KARTU PENGATURAN PROMO ONGKIR -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <!-- Promo 1: Gratis Ongkir Radius Malang Kota -->
                    <div class="p-4 sm:p-5 rounded-2xl border transition-colors flex flex-col justify-between gap-3"
                         :class="location.free_shipping_enabled ? 'bg-emerald-50/70 border-emerald-200' : 'bg-slate-50 border-slate-200'">
                        <div class="flex items-start justify-between gap-3">
                            <div class="space-y-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-xs font-black uppercase tracking-wider text-slate-900">
                                        1. Gratis Ongkir Radius Malang
                                    </span>
                                    <span class="text-[10px] font-black uppercase px-2.5 py-0.5 rounded-full"
                                          :class="location.free_shipping_enabled ? 'bg-emerald-600 text-white' : 'bg-slate-300 text-slate-700'"
                                          x-text="location.free_shipping_enabled ? 'AKTIF' : 'NONAKTIF'"></span>
                                </div>
                                <p class="text-[11px] text-slate-600 leading-relaxed">
                                    Menampilkan peta & GPS di halaman Checkout untuk gratis ongkir dalam radius tertentu dari titik store.
                                </p>
                            </div>

                            <label class="relative inline-flex items-center cursor-pointer shrink-0">
                                <input type="checkbox"
                                       name="location[free_shipping_enabled]"
                                       value="1"
                                       x-model="location.free_shipping_enabled"
                                       @change="syncMapShape(false)"
                                       class="sr-only peer">
                                <div class="w-12 h-7 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-5 peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-emerald-600"></div>
                            </label>
                        </div>
                    </div>

                    <!-- Promo 2: Gratis Ongkir Minimal Belanja -->
                    <div class="p-4 sm:p-5 rounded-2xl border transition-colors flex flex-col justify-between gap-3"
                         :class="location.min_order_free_shipping_enabled ? 'bg-emerald-50/70 border-emerald-200' : 'bg-slate-50 border-slate-200'">
                        <div class="flex items-start justify-between gap-3">
                            <div class="space-y-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-xs font-black uppercase tracking-wider text-slate-900">
                                        2. Gratis Ongkir Minimal Belanja
                                    </span>
                                    <span class="text-[10px] font-black uppercase px-2.5 py-0.5 rounded-full"
                                          :class="location.min_order_free_shipping_enabled ? 'bg-emerald-600 text-white' : 'bg-slate-300 text-slate-700'"
                                          x-text="location.min_order_free_shipping_enabled ? 'AKTIF' : 'NONAKTIF'"></span>
                                </div>
                                <p class="text-[11px] text-slate-600 leading-relaxed">
                                    Otomatis menggratiskan ongkir JNE REG & SiCepat BEST jika total belanja mencapai nominal minimal.
                                </p>
                            </div>

                            <label class="relative inline-flex items-center cursor-pointer shrink-0">
                                <input type="checkbox"
                                       name="location[min_order_free_shipping_enabled]"
                                       value="1"
                                       x-model="location.min_order_free_shipping_enabled"
                                       class="sr-only peer">
                                <div class="w-12 h-7 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-5 peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-emerald-600"></div>
                            </label>
                        </div>

                        <div class="pt-2 border-t border-slate-200/70">
                            <label class="block text-[11px] font-bold text-slate-700 mb-1">
                                Nominal Minimal Belanja Gratis Ongkir (Rp) *
                            </label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-3.5 flex items-center text-xs font-black text-slate-500">Rp</span>
                                <input type="number"
                                       step="1000"
                                       min="0"
                                       name="location[min_order_free_shipping_amount]"
                                       x-model.number="location.min_order_free_shipping_amount"
                                       :disabled="!location.min_order_free_shipping_enabled"
                                       required
                                       placeholder="250000"
                                       class="w-full text-xs font-black bg-white border border-slate-200 rounded-xl pl-10 pr-3.5 py-2 disabled:opacity-50">
                                <template x-if="!location.min_order_free_shipping_enabled">
                                    <input type="hidden" name="location[min_order_free_shipping_amount]" :value="location.min_order_free_shipping_amount">
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Peta Interaktif -->
                <div class="rounded-2xl overflow-hidden border border-slate-200 shadow-inner bg-slate-100">
                    <div id="admin-store-map" class="w-full h-72 sm:h-80 z-10"></div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Latitude Store *</label>
                        <input type="number" step="any" name="location[store_lat]"
                               x-model.number="location.store_lat"
                               @input="updateCoordinates(location.store_lat, location.store_lng, true)" required
                               class="w-full text-xs font-black bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Longitude Store *</label>
                        <input type="number" step="any" name="location[store_lng]"
                               x-model.number="location.store_lng"
                               @input="updateCoordinates(location.store_lat, location.store_lng, true)" required
                               class="w-full text-xs font-black bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Radius Gratis Ongkir Malang Kota (km) *</label>
                        <input type="number" step="0.5" min="0" max="100" name="location[free_shipping_radius_km]"
                               x-model.number="location.free_shipping_radius_km"
                               @input="syncMapShape(false)"
                               :disabled="!location.free_shipping_enabled"
                               required
                               class="w-full text-xs font-black bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 disabled:opacity-50">
                        <template x-if="!location.free_shipping_enabled">
                            <input type="hidden" name="location[free_shipping_radius_km]" :value="location.free_shipping_radius_km">
                        </template>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Alamat Lengkap Store *</label>
                        <input type="text" name="location[store_address]"
                               x-model="location.store_address" required
                               placeholder="Contoh: Jl. MT. Haryono No.129, Dinoyo, Kec. Lowokwaru, Kota Malang, Jawa Timur 65144"
                               class="w-full text-xs font-semibold bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Link Google Maps Store (Opsional)</label>
                        <input type="url" name="location[google_maps_url]"
                               x-model="location.google_maps_url"
                               placeholder="https://maps.google.com/?q=-7.9398,112.6083"
                               class="w-full text-xs font-semibold bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5">
                    </div>
                </div>
            </section>

            <!-- Tombol Simpan -->
            <div class="pt-6 border-t border-slate-100 flex items-center justify-end gap-3">
                <a href="{{ route('admin.dashboard') }}"
                   class="px-5 py-3 rounded-xl text-xs font-bold uppercase tracking-wider bg-slate-100 hover:bg-slate-200 text-slate-700 transition">
                    Batal
                </a>
                <button type="submit"
                        class="px-6 py-3 rounded-xl text-xs font-black uppercase tracking-wider bg-red-600 hover:bg-red-700 text-white shadow-lg shadow-red-600/25 transition">
                    Simpan Perubahan Beranda
                </button>
            </div>
        </form>
    </main>
</body>
</html>