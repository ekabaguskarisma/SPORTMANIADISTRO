<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_banners', function (Blueprint $table) {
            $table->id();
            $table->enum('placement', ['hero', 'showcase'])->default('showcase'); // hero = slider atas, showcase = kotak promo beranda
            $table->string('badge_text')->nullable();       // Contoh: 01 / BEST SELLER atau FEATURED SHOWCASE
            $table->string('subtitle')->nullable();         // Contoh: PERFORMANCE APPAREL
            $table->string('title');                        // Contoh: PRO RUNNING 2026 / DISKON AKHIR BULAN
            $table->text('description')->nullable();
            $table->string('button_text')->default('BELANJA SEKARANG');
            $table->string('button_url')->default('/katalog');
            $table->string('image')->nullable();
            $table->string('image_caption')->nullable();    // Untuk caption di pojok gambar kotak showcase
            $table->integer('sort_order')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_banners');
    }
};