<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('products', 'slug')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('slug')->nullable()->unique()->after('name');
            });

            // Mengisi slug otomatis untuk produk lama yang sudah ada di tabel
            DB::table('products')->whereNull('slug')->get()->each(function ($product) {
                DB::table('products')
                    ->where('id', $product->id)
                    ->update([
                        'slug' => Str::slug($product->name) . '-' . $product->id,
                    ]);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'slug')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropUnique(['slug']);
                $table->dropColumn('slug');
            });
        }
    }
};