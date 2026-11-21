<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password | Sport Mania Distro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style> body { font-family: 'Inter', sans-serif; background-color: #09090b; } </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">

    <div class="max-w-md w-full bg-white rounded-3xl overflow-hidden shadow-[0_20px_50px_rgba(0,0,0,0.5)] border border-gray-800 p-8 sm:p-10 relative">
        
        <div class="text-center mb-8">
            <div class="inline-flex p-2 rounded-xl bg-gray-900 border border-gray-800 shadow-inner mb-6">
                <img src="{{ asset('image/LOGO.jpeg') }}" alt="Logo" class="h-10 w-auto object-contain rounded-lg">
            </div>
            <h1 class="text-2xl font-black text-gray-900 uppercase tracking-tight">Lupa Password?</h1>
            <p class="text-gray-500 text-xs font-medium mt-3 leading-relaxed">
                Tidak masalah. Masukkan email akunmu dan kami akan mengirimkan tautan untuk membuat password baru.
            </p>
        </div>

        <!-- Session Status -->
        <x-auth-session-status class="mb-4" :status="session('status')" />
        @if ($errors->any())
            <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-600 text-[11px] rounded-xl font-medium">
                <ul class="list-disc pl-4 space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('password.email') }}" class="space-y-6">
            @csrf

            <div>
                <label for="email" class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1.5 ml-1">Email Terdaftar</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                       class="w-full bg-[#F3F4F6] border-2 border-transparent focus:border-gray-900 focus:bg-white rounded-xl px-4 py-3.5 text-sm font-medium text-gray-900 transition-all outline-none text-center" 
                       placeholder="user@gmail.com">
            </div>

            <button type="submit" class="w-full bg-gray-900 hover:bg-gray-800 text-white font-black text-xs uppercase tracking-widest py-4 rounded-xl transition shadow-lg active:scale-[0.98]">
                Kirim Link Reset Password
            </button>
        </form>

        <div class="mt-8 text-center border-t border-gray-100 pt-6">
            <a href="{{ route('login') }}" class="text-xs font-bold text-gray-500 hover:text-gray-900 transition uppercase tracking-widest flex items-center justify-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16l-4-4m0 0l4-4m-4 4h18"></path></svg>
                Kembali ke Login
            </a>
        </div>

    </div>
</body>
</html>