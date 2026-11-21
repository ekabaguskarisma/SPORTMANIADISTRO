<section>
    <header class="border-b border-gray-100 pb-4">
        <h2 class="text-sm font-black uppercase tracking-wider text-gray-900">
            Perbarui Kata Sandi
        </h2>

        <p class="mt-1 text-xs text-gray-500 leading-relaxed">
            Pastikan akun Anda menggunakan kombinasi kata sandi yang panjang dan acak agar tetap aman.
        </p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="mt-5 space-y-4">
        @csrf
        @method('put')

        <div>
            <label for="update_password_current_password" class="block text-[11px] font-bold uppercase tracking-wider text-gray-700 mb-1.5">
                Kata Sandi Saat Ini
            </label>
            <input id="update_password_current_password"
                   name="current_password"
                   type="password"
                   autocomplete="current-password"
                   placeholder="••••••••"
                   class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-xs font-medium text-gray-900 focus:outline-none focus:ring-2 focus:ring-red-600 focus:border-red-600 transition">
            <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-1.5 text-xs" />
        </div>

        <div>
            <label for="update_password_password" class="block text-[11px] font-bold uppercase tracking-wider text-gray-700 mb-1.5">
                Kata Sandi Baru
            </label>
            <input id="update_password_password"
                   name="password"
                   type="password"
                   autocomplete="new-password"
                   placeholder="Minimal 8 karakter"
                   class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-xs font-medium text-gray-900 focus:outline-none focus:ring-2 focus:ring-red-600 focus:border-red-600 transition">
            <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-1.5 text-xs" />
        </div>

        <div>
            <label for="update_password_password_confirmation" class="block text-[11px] font-bold uppercase tracking-wider text-gray-700 mb-1.5">
                Konfirmasi Kata Sandi Baru
            </label>
            <input id="update_password_password_confirmation"
                   name="password_confirmation"
                   type="password"
                   autocomplete="new-password"
                   placeholder="Ulangi kata sandi baru"
                   class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-xs font-medium text-gray-900 focus:outline-none focus:ring-2 focus:ring-red-600 focus:border-red-600 transition">
            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-1.5 text-xs" />
        </div>

        <div class="flex items-center gap-4 pt-2">
            <button type="submit"
                    class="bg-gray-900 hover:bg-red-600 text-white text-xs font-black uppercase tracking-wider px-5 py-3 rounded-xl transition shadow-sm cursor-pointer">
                Simpan Kata Sandi
            </button>

            @if (session('status') === 'password-updated')
                <p x-data="{ show: true }"
                   x-show="show"
                   x-transition
                   x-init="setTimeout(() => show = false, 2500)"
                   class="text-xs font-bold text-emerald-600">
                    ✓ Tersimpan.
                </p>
            @endif
        </div>
    </form>
</section>