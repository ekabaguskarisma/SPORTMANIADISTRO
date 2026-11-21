<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;

// Import Controllers
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\AdminProductController;
use App\Http\Controllers\AdminOrderController;
use App\Http\Controllers\CustomerOrderController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\Auth\SocialiteController;
use App\Http\Controllers\Admin\PromoBannerController;
use App\Http\Controllers\Admin\PosController;
use App\Http\Controllers\StoreFeatureController;

// ==========================================
// VIEW COMPOSER: OTOMATIS KIRIM PROMO AKTIF KE ETALASE (welcome.blade.php)
// ==========================================
View::composer('welcome', function ($view) {
    $promoBanners = collect();

    if (Schema::hasTable('promo_banners') && class_exists(\App\Models\PromoBanner::class)) {
        $query = \App\Models\PromoBanner::query();

        if (Schema::hasColumn('promo_banners', 'is_active')) {
            $query->where('is_active', true);
        }

        if (Schema::hasColumn('promo_banners', 'sort_order')) {
            $query->orderBy('sort_order', 'asc');
        } elseif (Schema::hasColumn('promo_banners', 'order')) {
            $query->orderBy('order', 'asc');
        }

        $promoBanners = $query->latest()->get();
    }

    $view->with('promoBanners', $promoBanners);
});


// ==========================================
// RUTE UMUM & ETALASE
// ==========================================

// Halaman Utama (Homepage) & Pencarian Katalog
Route::get('/', [ProductController::class, 'index'])->name('home');

// Halaman Detail Produk
Route::get('/produk/{id}', [ProductController::class, 'show'])->name('produk.show');

// Route Keranjang Belanja (Bisa diakses tanpa login)
Route::get('/customer/cart', function () {
    return view('customer.cart');
})->name('customer.cart');

// Lacak Pesanan Publik & Cek Voucher
Route::get('/lacak-pesanan', [StoreFeatureController::class, 'trackOrder'])->name('order.track');
Route::post('/voucher/apply', [StoreFeatureController::class, 'applyVoucher'])->name('voucher.apply');

// Google OAuth
Route::get('/auth/google', [SocialiteController::class, 'redirectToGoogle'])->name('auth.google');
Route::get('/auth/google/callback', [SocialiteController::class, 'handleGoogleCallback'])->name('auth.google.callback');


// ==========================================
// RUTE AUTENTIKASI & PROFIL
// ==========================================

// Halaman Dashboard Default (Redirect Berdasarkan Role)
Route::get('/dashboard', function () {
    $role = strtolower((string) (auth()->user()->role ?? ''));
    if ($role === 'admin') {
        return redirect()->route('admin.dashboard');
    }
    return redirect('/');
})->middleware(['auth', 'verified'])->name('dashboard');

// Profil Pengguna
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});


// ==========================================
// RUTE KHUSUS PELANGGAN (CUSTOMER)
// ==========================================
Route::middleware('auth')->group(function () {
    // Halaman Form Checkout
    Route::get('/customer/checkout', [CheckoutController::class, 'index'])->name('customer.checkout');

    // API Simpan Order dari Checkout ke Database (Diakses via Fetch Javascript)
    Route::post('/checkout/process', [CheckoutController::class, 'store'])->name('checkout.process');

    // Riwayat Pesanan, Pembatalan & Konfirmasi Selesai
    Route::get('/customer/orders', [CustomerOrderController::class, 'index'])->name('customer.orders');
    Route::post('/customer/orders/{id}/cancel', [CustomerOrderController::class, 'cancel'])->name('customer.orders.cancel');
    Route::post('/customer/orders/{id}/complete', [CustomerOrderController::class, 'complete'])->name('customer.orders.complete');

    // Simpan Ulasan Produk setelah Pesanan Selesai
    Route::post('/customer/reviews', [ReviewController::class, 'store'])->name('customer.reviews.store');

    // Unggah / Unggah Ulang Bukti Pembayaran
    Route::post('/orders/{id}/payment-proof', [CustomerOrderController::class, 'uploadPaymentProof'])
        ->name('customer.orders.payment-proof');
});


// ==========================================
// RUTE KHUSUS ADMIN (TERPROTEKSI MIDDLEWARE ROLE:ADMIN)
// ==========================================
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {

    // Dashboard Admin Utama (Dengan Eager Loading untuk mencegah N+1 Query)
    Route::get('/dashboard', function () {
        $relations = ['category', 'variants'];
        if (method_exists(\App\Models\Product::class, 'images')) {
            $relations[] = 'images';
        }
        if (method_exists(\App\Models\Product::class, 'videos')) {
            $relations[] = 'videos';
        }

        $products = \App\Models\Product::with($relations)->latest()->get();

        $orders = Schema::hasTable('orders')
            ? \App\Models\Order::with(['items.product', 'user'])->latest()->get()
            : collect();

        $customers = \App\Models\User::where(function ($q) {
            $q->whereNotIn('role', ['admin', 'owner'])
              ->orWhereNull('role');
        })->latest()->get();

        $promos = (Schema::hasTable('promo_banners') && class_exists(\App\Models\PromoBanner::class))
            ? \App\Models\PromoBanner::orderBy('placement')->orderBy('sort_order')->latest()->get()
            : collect();

        $vouchers = (Schema::hasTable('vouchers') && class_exists(\App\Models\Voucher::class))
            ? \App\Models\Voucher::latest()->get()
            : collect();

        // Kirim $promos dan alias $promoBanners agar kompatibel dengan blade
        $promoBanners = $promos;

        return view('dashboard', compact('products', 'orders', 'customers', 'promos', 'promoBanners', 'vouchers'));
    })->name('dashboard');

    // Kasir Offline Store (POS)
    Route::post('/pos', [PosController::class, 'store']);
    Route::post('/pos/checkout', [PosController::class, 'store'])->name('pos.store');

    // Manajemen Produk
    Route::get('/products', [AdminProductController::class, 'index'])->name('products.index');
    Route::get('/produk/create', [AdminProductController::class, 'create'])->name('produk.create');
    Route::post('/produk', [AdminProductController::class, 'store'])->name('produk.store');
    Route::get('/produk/{id}/edit', [AdminProductController::class, 'edit'])->name('produk.edit');
    Route::put('/produk/{id}', [AdminProductController::class, 'update'])->name('produk.update');
    Route::delete('/produk/{id}', [AdminProductController::class, 'destroy'])->name('produk.destroy');
    Route::delete('/product-image/{id}', [AdminProductController::class, 'destroyImage'])->name('product-image.destroy');
    Route::delete('/product-video/{id}', [AdminProductController::class, 'destroyVideo'])->name('product-video.destroy');

    // Manajemen Pesanan & Rekap Bulanan (Route statis wajib di atas wildcard {id})
    Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/export-csv', [AdminOrderController::class, 'exportCsv'])->name('orders.export-csv');
    Route::get('/orders/{id}', [AdminOrderController::class, 'show'])->name('orders.show');
    Route::put('/orders/{id}', [AdminOrderController::class, 'update'])->name('orders.update');
    Route::patch('/orders/{id}/verify-payment', [AdminOrderController::class, 'verifyPayment'])->name('orders.verify-payment');
    Route::get('/rekap-bulanan', [AdminOrderController::class, 'rekapBulanan'])->name('rekap.bulanan');

    // Manajemen Promo Banner
    Route::post('/promos', [PromoBannerController::class, 'store'])->name('promos.store');
    Route::put('/promos/{promo}', [PromoBannerController::class, 'update'])->name('promos.update');
    Route::patch('/promos/{promo}/toggle', [PromoBannerController::class, 'toggle'])->name('promos.toggle');
    Route::delete('/promos/{promo}', [PromoBannerController::class, 'destroy'])->name('promos.destroy');

    // Manajemen Voucher Admin
    Route::post('/vouchers', [StoreFeatureController::class, 'storeVoucher'])->name('vouchers.store');
    Route::patch('/vouchers/{voucher}/toggle', [StoreFeatureController::class, 'toggleVoucher'])->name('vouchers.toggle');
    Route::delete('/vouchers/{voucher}', [StoreFeatureController::class, 'destroyVoucher'])->name('vouchers.destroy');
});

require __DIR__.'/auth.php';