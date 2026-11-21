<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up(): void
{
    Schema::table('promo_banners', function (Blueprint $table) {
        if (!Schema::hasColumn('promo_banners', 'bg_typography')) {
            $table->string('bg_typography')->nullable()->after('title'); // Teks raksasa di belakang (misal: JERSEY, RUNNING, SALE)
        }
        if (!Schema::hasColumn('promo_banners', 'media_type')) {
            $table->string('media_type')->default('image')->after('image'); // 'image' atau 'video'
        }
        if (!Schema::hasColumn('promo_banners', 'video_path')) {
            $table->string('video_path')->nullable()->after('media_type');
        }
        if (!Schema::hasColumn('promo_banners', 'button_text')) {
            $table->string('button_text')->default('LIHAT KOLEKSI')->after('description');
        }
    });
}

public function down(): void
{
    Schema::table('promo_banners', function (Blueprint $table) {
        $table->dropColumn(['bg_typography', 'media_type', 'video_path', 'button_text']);
    });
}
};
