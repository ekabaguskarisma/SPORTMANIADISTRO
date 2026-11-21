<section class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-sm">
    <header class="border-b border-gray-100 pb-4 mb-6">
        <h2 class="text-xl font-black text-gray-900 uppercase tracking-tight">
            {{ __('Informasi Profil & Pengaturan Akun') }}
        </h2>
        <p class="text-xs font-medium text-gray-500 mt-1">
            {{ __("Kelola informasi data diri, foto profil, dan alamat pengiriman utama untuk transaksi yang lebih cepat.") }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('patch')

        <!-- Bagian Upload & Pratinjau Foto Profil (Alpine.js) -->
        <div class="flex items-center gap-6 p-4 bg-gray-50/80 rounded-2xl border border-gray-100" 
             x-data="{ photoPreview: '{{ $user->avatar ? asset('storage/' . $user->avatar) : null }}' }">
            
            <div class="relative shrink-0">
                <!-- Pratinjau Gambar -->
                <template x-if="photoPreview">
                    <img :src="photoPreview" class="w-20 h-20 rounded-2xl object-cover border-2 border-white shadow-md">
                </template>
                <template x-if="!photoPreview">
                    <div class="w-20 h-20 rounded-2xl bg-gray-900 text-white flex items-center justify-center font-black text-2xl shadow-md uppercase tracking-wider">
                        {{ strtoupper(substr($user->name, 0, 2)) }}
                    </div>
                </template>
            </div>

            <div class="flex-1">
                <label class="block text-xs font-bold uppercase text-gray-700 mb-1">Foto Profil / Avatar</label>
                <input type="file" name="avatar" accept="image/*" 
                       @change="const file = $event.target.files[0]; if (file) { const reader = new FileReader(); reader.onload = (e) => photoPreview = e.target.result; reader.readAsDataURL(file); }"
                       class="w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-gray-900 file:text-white hover:file:bg-red-600 file:transition cursor-pointer">
                <p class="text-[10px] text-gray-400 mt-1">Format: JPG, PNG, atau WEBP. Maksimal 2MB.</p>
            </div>
        </div>

        <!-- Informasi Utama -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div>
                <label class="block text-xs font-bold uppercase text-gray-700 mb-1">Nama Lengkap</label>
                <input type="text" name="name" value="{{ old('name', $user->name) }}" required class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-xs font-medium focus:ring-2 focus:ring-red-600 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-gray-700 mb-1">Alamat Email</label>
                <input type="email" name="email" value="{{ old('email', $user->email) }}" required class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-xs font-medium focus:ring-2 focus:ring-red-600 focus:outline-none">
            </div>
        </div>

        <!-- Informasi Kontak & Alamat Pengiriman -->
        <div class="bg-gray-50/70 p-6 rounded-2xl border border-gray-100 space-y-4">
            <h3 class="text-xs font-black uppercase tracking-wider text-gray-900">Alamat & Kontak Pengiriman E-Commerce</h3>
            
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase text-gray-700 mb-1">No. WhatsApp / Telepon</label>
                    <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" placeholder="08xxxxxxxxxx" class="w-full bg-white border border-gray-200 rounded-xl px-4 py-3 text-xs font-medium focus:ring-2 focus:ring-red-600 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase text-gray-700 mb-1">Kota / Kabupaten</label>
                    <input type="text" name="city" value="{{ old('city', $user->city) }}" placeholder="Contoh: Malang" class="w-full bg-white border border-gray-200 rounded-xl px-4 py-3 text-xs font-medium focus:ring-2 focus:ring-red-600 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase text-gray-700 mb-1">Kode Pos</label>
                    <input type="text" name="postal_code" value="{{ old('postal_code', $user->postal_code) }}" placeholder="65145" class="w-full bg-white border border-gray-200 rounded-xl px-4 py-3 text-xs font-medium focus:ring-2 focus:ring-red-600 focus:outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-gray-700 mb-1">Alamat Lengkap</label>
                <textarea name="address" rows="3" placeholder="Jalan, Nomor Rumah, RT/RW, Kecamatan..." class="w-full bg-white border border-gray-200 rounded-xl p-4 text-xs font-medium focus:ring-2 focus:ring-red-600 focus:outline-none">{{ old('address', $user->address) }}</textarea>
            </div>
        </div>

        <!-- Tombol Aksi -->
        <div class="flex items-center gap-4 pt-2">
            <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-8 py-3.5 rounded-xl font-bold text-xs uppercase tracking-widest transition shadow-lg shadow-red-600/20">
                {{ __('Simpan Perubahan') }}
            </button>

            @if (session('status') === 'profile-updated')
                <p x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 2500)" class="text-xs font-bold text-green-600 flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                    {{ __('Perubahan berhasil disimpan!') }}
                </p>
            @endif
        </div>
    </form>
</section>