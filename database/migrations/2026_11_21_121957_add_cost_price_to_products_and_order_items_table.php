<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('products') && !Schema::hasColumn('products', 'cost_price')) {
            Schema::table('products', function (Blueprint $table) {
                $table->unsignedBigInteger('cost_price')->default(0)->after('price');
            });
        }

        if (Schema::hasTable('order_items') && !Schema::hasColumn('order_items', 'cost_price')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->unsignedBigInteger('cost_price')->default(0)->after('price');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('products') && Schema::hasColumn('products', 'cost_price')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('cost_price');
            });
        }

        if (Schema::hasTable('order_items') && Schema::hasColumn('order_items', 'cost_price')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropColumn('cost_price');
            });
        }
    }
};