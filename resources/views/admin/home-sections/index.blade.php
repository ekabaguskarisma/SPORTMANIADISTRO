<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Tampilan Beranda | Admin Sport Mania</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 antialiased"
      x-data="{
          activeTab: 'brands',
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
                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-red-600 flex items-center justify-center font-black italic text-sm">SM</div>
                    <span class="font-black tracking-tighter uppercase italic text-base">
                        <span class="text-red-500">SPORT</span>MANIA ADMIN
                    </span>
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
                <span class="text-[10px] font-black uppercase tracking-widest text-red-600">Konten Halaman Utama</span>
                <h1 class="text-2xl font-black uppercase tracking-tight text-slate-900 mt-0.5">
                    Edit Tampilan <span class="text-red-600">Beranda</span>
                </h1>
                <p class="text-xs text-slate-500 font-medium mt-1">
                    Atur kartu brand terpercaya, hero banner slider, kategori pilihan, dan showcase unggulan di halaman depan.
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
        </div>

        <form action="{{ route('admin.home-sections.update') }}" method="POST" enctype="multipart/form-data"
              class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 sm:p-8 space-y-8">
            @csrf
            @method('PUT')

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