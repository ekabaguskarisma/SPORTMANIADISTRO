<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Kolom harga diskon (boleh kosong)
            $table->decimal('discount_price', 12, 2)->nullable()->after('price');
            
            // Kolom penanda apakah produk ini tampil di "Apparel Pilihan" (default: tidak)
            $table->boolean('is_featured')->default(false)->after('discount_price');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['discount_price', 'is_featured']);
        });
    }
};