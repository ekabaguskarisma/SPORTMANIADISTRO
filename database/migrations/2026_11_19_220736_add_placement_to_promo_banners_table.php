<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::table('promo_banners', function (Blueprint $table) {
        if (!Schema::hasColumn('promo_banners', 'placement')) {
            $table->string('placement')->default('hero')->after('image');
        }
    });
}

public function down(): void
{
    Schema::table('promo_banners', function (Blueprint $table) {
        if (Schema::hasColumn('promo_banners', 'placement')) {
            $table->dropColumn('placement');
        }
    });
}
};
