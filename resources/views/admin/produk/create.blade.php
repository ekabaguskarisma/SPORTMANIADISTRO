<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Produk - Sport Mania Distro</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        [x-cloak] { display: none !important; }
        .tabular-nums { font-variant-numeric: tabular-nums; }
    </style>
</head>
<body class="bg-slate-50/90 text-slate-800 antialiased selection:bg-red-600 selection:text-white"
      x-data="{
          variants: [{ id: Date.now(), size: '', color: '', stock: 0 }],
          defaultColor: 'Hitam',
          price: {{ (int) old('price', 0) }},
          discountPrice: {{ (int) old('discount_price', 0) }},
          coverPreview: null,
          galleryPreviews: [],
          videoPreviews: [],

          get totalStock() {
              return this.variants.reduce((sum, v) => sum + (parseInt(v.stock) || 0), 0);
          },

          get discountPercent() {
              const p = parseFloat(this.price) || 0;
              const d = parseFloat(this.discountPrice) || 0;
              if (p > 0 && d > 0 && d < p) {
                  return Math.round(((p - d) / p) * 100);
              }
              return 0;
          },

          applyPreset(type) {
              const presets = {
                  clothing: ['S', 'M', 'L', 'XL', 'XXL'],
                  shoes: ['39', '40', '41', '42', '43', '44'],
                  accessory: ['All Size']
              };
              const sizes = presets[type] || [];
              const baseTime = Date.now();
              this.variants = sizes.map((sz, idx) => ({
                  id: baseTime + idx,
                  size: sz,
                  color: this.defaultColor || 'Hitam',
                  stock: 5
              }));
          },

          previewCover(event) {
              const file = event.target.files[0];
              this.coverPreview = file ? URL.createObjectURL(file) : null;
          },

          previewGallery(event) {
              this.galleryPreviews = Array.from(event.target.files).map(file => URL.createObjectURL(file));
          },

          previewVideos(event) {
              this.videoPreviews.forEach(item => URL.revokeObjectURL(item.url));
              this.videoPreviews = Array.from(event.target.files).map(file => ({
                  file: file,
                  name: file.name,
                  sizeMB: (file.size / (1024 * 1024)).toFixed(1),
                  url: URL.createObjectURL(file)
              }));
          },

          removeVideoPreview(idx) {
              if (this.videoPreviews[idx]) {
                  URL.revokeObjectURL(this.videoPreviews[idx].url);
              }
              this.videoPreviews.splice(idx, 1);
              if (this.$refs.videoInput) {
                  const dt = new DataTransfer();
                  this.videoPreviews.forEach(item => dt.items.add(item.file));
                  this.$refs.videoInput.files = dt.files;
              }
          },

          clearAllVideoPreviews() {
              this.videoPreviews.forEach(item => URL.revokeObjectURL(item.url));
              this.videoPreviews = [];
              if (this.$refs.videoInput) {
                  this.$refs.videoInput.value = '';
              }
          }
      }">

    <!-- Top Navigation Bar -->
    <header class="sticky top-0 z-30 bg-slate-950/95 backdrop-blur-md border-b border-slate-800 text-white">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 py-4 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-red-600 to-rose-700 flex items-center justify-center text-white font-black italic text-sm shadow-lg shadow-red-600/30">
                        SM
                    </div>
                    <span class="text-lg font-black tracking-tighter uppercase italic">
                        <span class="text-red-500">SPORT</span><span class="text-white">MANIA</span>
                    </span>
                </a>
                <span class="text-slate-600">/</span>
                <span class="text-xs font-bold text-slate-300">Tambah Produk Baru</span>
            </div>

            <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-300 hover:text-white bg-slate-900 hover:bg-slate-800 px-3.5 py-2 rounded-xl border border-slate-800 transition">
                ← Kembali ke Dashboard
            </a>
        </div>
    </header>

    <!-- Main Form Container -->
    <main class="max-w-5xl mx-auto px-4 sm:px-6 py-8 sm:py-10">
        <div class="bg-white rounded-3xl shadow-sm border border-slate-200/80 overflow-hidden">

            <!-- Header Banner Form -->
            <div class="bg-gradient-to-r from-slate-950 via-slate-900 to-slate-950 px-6 sm:px-8 py-6 border-b border-slate-800 flex flex-wrap justify-between items-center gap-4">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-widest text-red-500">Manajemen Katalog</span>
                    <h1 class="text-xl font-black text-white tracking-tight mt-0.5">Tambah Produk Baru</h1>
                    <p class="text-slate-400 text-xs mt-1">Gunakan Smart Preset untuk mengisi varian ukuran pakaian/sepatu secara instan.</p>
                </div>
                <div class="bg-slate-900/90 border border-slate-800 px-4 py-2.5 rounded-2xl text-right">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Total Stok Varian</span>
                    <span class="text-lg font-black text-emerald-400 tabular-nums" x-text="totalStock + ' pcs'"></span>
                </div>
            </div>

            <form action="{{ route('admin.produk.store') }}" method="POST" enctype="multipart/form-data" class="p-6 sm:p-8 space-y-7">
                @csrf

                @if ($errors->any())
                    <div class="p-4 bg-rose-50 border border-rose-200/80 text-rose-700 text-xs rounded-2xl font-medium">
                        <p class="font-black uppercase tracking-wider mb-1.5">Gagal Menyimpan Produk:</p>
                        <ul class="list-disc pl-4 space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <!-- Bagian 1: Informasi Dasar Produk -->
                <div class="space-y-4">
                    <h3 class="text-[11px] font-black uppercase tracking-widest text-slate-500 flex items-center gap-2">
                        <span class="w-1.5 h-1.5 rounded-full bg-red-600"></span>
                        Informasi Dasar Produk
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Nama Produk <span class="text-red-500">*</span></label>
                            <input type="text" name="name" value="{{ old('name') }}" required
                                   class="w-full text-sm bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 focus:bg-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition"
                                   placeholder="Contoh: Jersey Futsal Pro Edition">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Kategori <span class="text-red-500">*</span></label>
                            <select name="category_id" required
                                    class="w-full text-sm bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 focus:bg-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition font-semibold">
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}" {{ old('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Harga Normal (Rp) <span class="text-red-500">*</span></label>
                            <input type="number" name="price" x-model="price" required min="0"
                                   class="w-full text-sm bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 tabular-nums focus:bg-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition"
                                   placeholder="250000">
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="block text-xs font-bold text-slate-700">Harga Diskon <span class="text-slate-400 font-normal">(Opsional)</span></label>
                                <span x-show="discountPercent > 0" x-cloak
                                      class="text-[10px] font-black uppercase tracking-wider bg-rose-100 text-rose-700 px-2 py-0.5 rounded-md tabular-nums"
                                      x-text="'Hemat ' + discountPercent + '%'"></span>
                            </div>
                            <input type="number" name="discount_price" x-model="discountPrice" min="0"
                                   class="w-full text-sm bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 tabular-nums focus:bg-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition"
                                   placeholder="199000">
                        </div>
                    </div>
                </div>

                <!-- Bagian 2: Manajemen Varian & Smart Presets -->
                <div class="p-6 bg-slate-50/90 border border-slate-200/80 rounded-2xl space-y-4">
                    <div class="flex flex-wrap justify-between items-start gap-3">
                        <div>
                            <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Manajemen Varian & Stok</h3>
                            <p class="text-[11px] text-slate-500 mt-0.5">Pilih preset cepat berdasarkan jenis barang atau tambah baris manual.</p>
                        </div>
                        <button type="button"
                                @click="variants.push({ id: Date.now(), size: '', color: defaultColor || '', stock: 0 })"
                                class="bg-slate-900 hover:bg-red-600 text-white text-xs font-bold px-3.5 py-2 rounded-xl transition shadow-sm">
                            + Tambah Baris
                        </button>
                    </div>

                    <!-- Smart Variant Preset Bar -->
                    <div class="flex flex-wrap items-center gap-2 p-3 bg-white rounded-xl border border-slate-200/80">
                        <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 mr-1">Smart Preset:</span>
                        <input type="text" x-model="defaultColor" placeholder="Warna default"
                               class="w-28 text-xs font-semibold bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-red-500">
                        <button type="button" @click="applyPreset('clothing')"
                                class="text-[11px] font-bold bg-slate-100 hover:bg-slate-900 hover:text-white text-slate-700 px-3 py-1.5 rounded-lg transition">
                            👕 Pakaian (S–XXL)
                        </button>
                        <button type="button" @click="applyPreset('shoes')"
                                class="text-[11px] font-bold bg-slate-100 hover:bg-slate-900 hover:text-white text-slate-700 px-3 py-1.5 rounded-lg transition">
                            👟 Sepatu (39–44)
                        </button>
                        <button type="button" @click="applyPreset('accessory')"
                                class="text-[11px] font-bold bg-slate-100 hover:bg-slate-900 hover:text-white text-slate-700 px-3 py-1.5 rounded-lg transition">
                            🎒 Tas / All Size
                        </button>
                    </div>

                    <div class="space-y-3">
                        <template x-for="(variant, index) in variants" :key="variant.id">
                            <div class="flex flex-wrap md:flex-nowrap gap-3 items-end bg-white p-3.5 rounded-xl border border-slate-200/80 shadow-sm">
                                <div class="flex-1 min-w-[130px]">
                                    <label class="text-[11px] font-bold text-slate-500 uppercase">Ukuran</label>
                                    <input type="text" x-model="variant.size" name="variant_sizes[]" placeholder="Contoh: M / L / XL / 42"
                                           class="w-full text-xs font-semibold bg-slate-50 border border-slate-200 rounded-lg p-2.5 mt-1 focus:bg-white focus:outline-none focus:ring-2 focus:ring-red-500">
                                </div>
                                <div class="flex-1 min-w-[130px]">
                                    <label class="text-[11px] font-bold text-slate-500 uppercase">Warna</label>
                                    <input type="text" x-model="variant.color" name="variant_colors[]" placeholder="Contoh: Hitam / Merah"
                                           class="w-full text-xs font-semibold bg-slate-50 border border-slate-200 rounded-lg p-2.5 mt-1 focus:bg-white focus:outline-none focus:ring-2 focus:ring-red-500">
                                </div>
                                <div class="w-32">
                                    <div class="flex items-center justify-between">
                                        <label class="text-[11px] font-bold text-slate-500 uppercase">Stok <span class="text-red-500">*</span></label>
                                        <span x-show="(parseInt(variant.stock) || 0) === 0"
                                              class="text-[9px] font-black uppercase bg-rose-100 text-rose-600 px-1.5 py-0.5 rounded">Habis</span>
                                    </div>
                                    <input type="number" x-model="variant.stock" name="variant_stocks[]" required min="0"
                                           class="w-full text-xs font-bold bg-slate-50 border border-slate-200 rounded-lg p-2.5 mt-1 tabular-nums focus:bg-white focus:outline-none focus:ring-2 focus:ring-red-500">
                                </div>
                                <button type="button"
                                        @click="if(variants.length > 1) variants.splice(index, 1)"
                                        x-show="variants.length > 1"
                                        class="p-2.5 bg-rose-50 hover:bg-rose-600 text-rose-600 hover:text-white rounded-lg transition"
                                        title="Hapus Varian">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Bagian 3: Upload Foto Utama & Galeri dengan Live Preview -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-slate-50/90 p-6 rounded-2xl border border-slate-200/80">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2">Foto Utama / Cover Katalog <span class="text-red-500">*</span></label>
                        <template x-if="coverPreview">
                            <div class="mb-3">
                                <img :src="coverPreview" alt="Preview Cover" class="w-24 h-28 object-cover rounded-xl border border-slate-200 shadow-sm">
                            </div>
                        </template>
                        <input type="file" name="image" accept="image/*" required @change="previewCover($event)"
                               class="w-full text-xs bg-white border border-slate-200 rounded-xl p-2.5 file:mr-3 file:py-2 file:px-3.5 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-slate-900 file:text-white hover:file:bg-red-600 file:transition cursor-pointer">
                        <p class="text-[10px] text-slate-500 mt-1.5">Disarankan rasio vertikal 4:5 (JPG, PNG, WEBP).</p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2">Foto Galeri Tambahan <span class="text-slate-400 font-normal">(Bisa pilih banyak)</span></label>
                        <template x-if="galleryPreviews.length > 0">
                            <div class="flex flex-wrap gap-2 mb-3">
                                <template x-for="(src, idx) in galleryPreviews" :key="idx">
                                    <img :src="src" alt="Preview Galeri" class="w-14 h-16 object-cover rounded-lg border border-slate-200 shadow-sm">
                                </template>
                            </div>
                        </template>
                        <input type="file" name="images[]" accept="image/*" multiple @change="previewGallery($event)"
                               class="w-full text-xs bg-white border border-slate-200 rounded-xl p-2.5 file:mr-3 file:py-2 file:px-3.5 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-slate-700 file:text-white hover:file:bg-slate-900 file:transition cursor-pointer">
                        <p class="text-[10px] text-slate-500 mt-1.5">Tahan Ctrl/Shift untuk memilih lebih dari 1 foto detail.</p>
                    </div>
                </div>

                <!-- Bagian 3B: Multi-Video Katalog Produk -->
                <div class="bg-slate-50/90 p-6 rounded-2xl border border-slate-200/80 space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Video Katalog Produk <span class="text-slate-400 font-semibold normal-case">(Bisa pilih lebih dari 1)</span></h3>
                            <p class="text-[11px] text-slate-500 mt-0.5">Unggah satu atau beberapa video singkat (MP4, WebM, MOV maks. 50 MB/video) untuk slider galeri interaktif.</p>
                        </div>
                        <button type="button"
                                x-show="videoPreviews.length > 0"
                                x-cloak
                                @click="clearAllVideoPreviews()"
                                class="text-[11px] font-bold text-rose-600 hover:underline">
                            Hapus Semua Pilihan (<span x-text="videoPreviews.length"></span>)
                        </button>
                    </div>

                    <div>
                        <input type="file"
                               x-ref="videoInput"
                               name="videos[]"
                               multiple
                               accept="video/mp4,video/webm,video/quicktime"
                               @change="previewVideos($event)"
                               class="w-full text-xs bg-white border border-slate-200 rounded-xl p-2.5 file:mr-3 file:py-2 file:px-3.5 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-slate-900 file:text-white hover:file:bg-red-600 file:transition cursor-pointer">
                        <p class="text-[10px] text-slate-500 mt-1.5">Tahan Ctrl/Shift saat memilih file untuk mengunggah banyak video sekaligus.</p>
                    </div>

                    <!-- Grid Live Preview Multi-Video -->
                    <template x-if="videoPreviews.length > 0">
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 pt-1">
                            <template x-for="(vid, idx) in videoPreviews" :key="idx">
                                <div class="bg-white rounded-2xl border-2 border-emerald-500/80 overflow-hidden shadow-sm flex flex-col">
                                    <div class="relative bg-slate-950 aspect-video">
                                        <video :src="vid.url" controls muted playsinline class="w-full h-full object-contain"></video>
                                        <span class="absolute top-2 left-2 bg-emerald-600 text-white text-[10px] font-black uppercase px-2 py-0.5 rounded-md shadow">
                                            Video <span x-text="idx + 1"></span>
                                        </span>
                                        <button type="button"
                                                @click="removeVideoPreview(idx)"
                                                title="Batal unggah video ini"
                                                class="absolute top-2 right-2 w-6 h-6 rounded-lg bg-rose-600 hover:bg-rose-700 text-white flex items-center justify-center shadow transition">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                                        </button>
                                    </div>
                                    <div class="px-3 py-2 flex items-center justify-between gap-2 text-[11px]">
                                        <span class="font-bold text-slate-700 truncate" x-text="vid.name"></span>
                                        <span class="text-slate-400 font-semibold shrink-0" x-text="vid.sizeMB + ' MB'"></span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>

                    <div x-show="videoPreviews.length === 0" class="h-24 rounded-xl border border-dashed border-slate-300 bg-white flex flex-col items-center justify-center text-center p-4">
                        <span class="text-xs font-bold text-slate-500">Belum ada video dipilih</span>
                        <span class="text-[11px] text-slate-400 mt-0.5">Semua video yang diunggah akan tampil berurutan di galeri detail produk</span>
                    </div>
                </div>

                <!-- Bagian 4: Apparel Pilihan & Deskripsi -->
                <div class="space-y-4">
                    <div class="flex items-center gap-3 bg-amber-50/70 p-4 rounded-2xl border border-amber-200/70">
                        <input type="checkbox" name="is_featured" id="is_featured" value="1" {{ old('is_featured') ? 'checked' : '' }}
                               class="w-4 h-4 text-red-600 border-slate-300 rounded focus:ring-red-500 cursor-pointer">
                        <label for="is_featured" class="font-bold text-slate-900 cursor-pointer text-xs">
                            Jadikan "Apparel Pilihan" <span class="text-slate-500 font-medium">(Tampil menonjol di etalase dengan badge 🔥)</span>
                        </label>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">Deskripsi & Spesifikasi Produk</label>
                        <textarea name="description" rows="4"
                                  class="w-full text-sm bg-slate-50 border border-slate-200 rounded-2xl p-4 focus:bg-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition"
                                  placeholder="Tuliskan spesifikasi bahan (misal: Dry-Fit Milano), panduan ukuran, dan keunggulan produk...">{{ old('description') }}</textarea>
                    </div>
                </div>

                <!-- Tombol Aksi -->
                <div class="pt-6 border-t border-slate-100 flex items-center justify-end gap-3">
                    <a href="{{ route('admin.dashboard') }}" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-6 py-3 rounded-xl font-bold text-xs uppercase tracking-wider transition">
                        Batal
                    </a>
                    <button type="submit" class="bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-700 hover:to-rose-700 text-white px-7 py-3 rounded-xl font-black text-xs uppercase tracking-wider transition shadow-lg shadow-red-600/25 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                        Simpan Produk
                    </button>
                </div>
            </form>
        </div>
    </main>
</body>
</html>