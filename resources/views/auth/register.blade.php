<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun | Sport Mania Distro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="h-full bg-gray-950 text-gray-900 antialiased selection:bg-red-500 selection:text-white flex items-center justify-center p-0 sm:p-6 lg:p-8">

    <!-- Container Utama (Background Gradasi Gelap Penuh) -->
    <div class="w-full h-full sm:h-auto sm:max-w-5xl sm:rounded-3xl bg-gradient-to-br from-gray-900 via-gray-950 to-black shadow-2xl overflow-hidden relative flex flex-col lg:flex-row border border-gray-800/85">
        
        <!-- Aksen Cahaya Latar Belakang -->
        <div class="absolute -top-32 -left-32 w-96 h-96 bg-red-600/15 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-32 -right-32 w-96 h-96 bg-red-600/10 rounded-full blur-3xl pointer-events-none"></div>

        <!-- Sisi Kiri: Branding & Teks Sporty -->
        <div class="lg:w-1/2 relative p-8 sm:p-12 lg:p-16 flex flex-col justify-between z-10">
            <div>
                <a href="/" class="inline-flex items-center gap-3 group">
                    <div class="p-1.5 rounded-xl bg-gray-900 border border-gray-700/80 shadow-inner group-hover:border-red-500 transition">
                        <img src="{{ asset('image/LOGO.jpeg') }}" alt="Logo" class="h-8 w-auto object-contain rounded">
                    </div>
                    <span class="text-xl font-black tracking-tighter uppercase italic text-white">
                        <span class="text-red-600">SPORT</span><span>MANIA</span>
                    </span>
                </a>
            </div>

            <div class="my-10 lg:my-0 space-y-4">
                <span class="inline-block bg-red-600/20 text-red-500 border border-red-500/30 text-[10px] font-black uppercase tracking-widest px-3 py-1 rounded-full">
                    Join Member
                </span>
                <h2 class="text-3xl sm:text-4xl font-black text-white uppercase tracking-tighter italic leading-tight">
                    Bergabung Menjadi <span class="text-red-600">Member Juara.</span>
                </h2>
                <p class="text-gray-400 text-xs sm:text-sm leading-relaxed">
                    Dapatkan akses penuh ke katalog eksklusif, pantau pesanan dengan mudah, dan rasakan pengalaman belanja perlengkapan olahraga terbaik.
                </p>
            </div>

            <div class="text-[11px] text-gray-500 font-medium">
                © 2026 Sport Mania Distro. All rights reserved.
            </div>
        </div>

        <!-- Sisi Kanan: Form Register (Berwarna Putih & Rounded Menutupi Sisi Kanan Gradasi) -->
        <div class="lg:w-1/2 bg-[#FAFAFA] p-8 sm:p-10 lg:p-12 flex flex-col justify-center relative z-20 lg:rounded-l-[2.5rem] shadow-2xl">
            <div class="w-full max-w-sm mx-auto">
                <div class="mb-6">
                    <h2 class="text-2xl sm:text-3xl font-black text-gray-900 uppercase tracking-tight">Buat Akun Baru</h2>
                    <p class="text-xs sm:text-sm text-gray-500 mt-1 font-medium">Lengkapi data diri untuk mendaftar.</p>
                </div>

                <form method="POST" action="{{ route('register') }}" class="space-y-3.5">
                    @csrf

                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">Nama Lengkap</label>
                        <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name"
                               class="w-full text-sm bg-white border border-gray-200 rounded-xl px-4 py-2.5 focus:outline-none focus:border-gray-900 transition shadow-sm">
                        @error('name')
                            <p class="text-xs text-red-600 mt-1 font-semibold">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">Email</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username"
                               class="w-full text-sm bg-white border border-gray-200 rounded-xl px-4 py-2.5 focus:outline-none focus:border-gray-900 transition shadow-sm">
                        @error('email')
                            <p class="text-xs text-red-600 mt-1 font-semibold">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">Password</label>
                        <input id="password" type="password" name="password" required autocomplete="new-password"
                               class="w-full text-sm bg-white border border-gray-200 rounded-xl px-4 py-2.5 focus:outline-none focus:border-gray-900 transition shadow-sm">
                        @error('password')
                            <p class="text-xs text-red-600 mt-1 font-semibold">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">Konfirmasi Password</label>
                        <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"
                               class="w-full text-sm bg-white border border-gray-200 rounded-xl px-4 py-2.5 focus:outline-none focus:border-gray-900 transition shadow-sm">
                        @error('password_confirmation')
                            <p class="text-xs text-red-600 mt-1 font-semibold">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" class="w-full bg-gray-900 hover:bg-red-600 text-white font-black text-xs uppercase tracking-widest py-3.5 rounded-xl transition-all shadow-xl hover:shadow-red-600/30 flex items-center justify-center gap-2 mt-2 cursor-pointer">
                        Daftar Sekarang
                    </button>
                </form>

                <!-- Pemisah -->
                <div class="mt-5 relative">
                    <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-gray-200"></div></div>
                    <div class="relative flex justify-center text-sm"><span class="px-2.5 bg-[#FAFAFA] text-[10px] font-bold text-gray-400 uppercase tracking-widest">Atau</span></div>
                </div>
                
                <!-- Tombol Daftar Google -->
                <a href="{{ route('auth.google') }}" class="mt-5 w-full bg-white border border-gray-200 hover:border-gray-300 hover:bg-gray-50 text-gray-700 font-bold text-xs py-3.5 rounded-xl transition shadow-sm flex items-center justify-center gap-3">
                    <svg class="w-4 h-4" viewBox="0 0 24 24"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
                    Daftar dengan Google
                </a>

                <div class="text-center pt-3 border-t border-gray-200 mt-4">
                    <p class="text-xs text-gray-500 font-medium">
                        Sudah punya akun? 
                        <a href="{{ route('login') }}" class="font-bold text-gray-900 hover:text-red-600 underline">Masuk di sini</a>
                    </p>
                </div>
            </div>
        </div>

    </div>

</body>
</html>