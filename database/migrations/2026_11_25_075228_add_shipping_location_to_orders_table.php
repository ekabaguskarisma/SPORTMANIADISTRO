<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    Schema::table('orders', function (Blueprint $table) {
        if (!Schema::hasColumn('orders', 'shipping_lat')) {
            $table->decimal('shipping_lat', 10, 7)->nullable()->after('address');
        }
        if (!Schema::hasColumn('orders', 'shipping_lng')) {
            $table->decimal('shipping_lng', 10, 7)->nullable()->after('shipping_lat');
        }
        if (!Schema::hasColumn('orders', 'shipping_distance_km')) {
            $table->decimal('shipping_distance_km', 8, 2)->default(0)->after('shipping_lng');
        }
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            //
        });
    }
};
