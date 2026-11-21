<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div>
                <h2 class="font-black text-xl text-gray-900 uppercase tracking-tight">
                    Pengaturan <span class="text-red-600">Profil & Akun</span>
                </h2>
                <p class="text-xs text-gray-500 font-medium mt-0.5">
                    Kelola foto profil, data pengiriman utama, serta keamanan akun Sport Mania Distro Anda.
                </p>
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                
                <!-- Kolom Kiri: Informasi Profil & Alamat E-Commerce -->
                <div class="lg:col-span-7 p-6 sm:p-8 bg-white border border-gray-100 shadow-sm rounded-3xl">
                    <div class="w-full">
                        @include('profile.partials.update-profile-information-form')
                    </div>
                </div>

                <!-- Kolom Kanan: Ubah Kata Sandi & Hapus Akun -->
                <div class="lg:col-span-5 space-y-6">
                    <div class="p-6 sm:p-8 bg-white border border-gray-100 shadow-sm rounded-3xl">
                        <div class="w-full">
                            @include('profile.partials.update-password-form')
                        </div>
                    </div>

                    <div class="p-6 sm:p-8 bg-white border border-gray-100 shadow-sm rounded-3xl">
                        <div class="w-full">
                            @include('profile.partials.delete-user-form')
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</x-app-layout>