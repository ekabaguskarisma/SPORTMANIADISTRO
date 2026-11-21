<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Satukan 'recipient_name' ke 'name'
        if (Schema::hasColumn('orders', 'recipient_name') && Schema::hasColumn('orders', 'name')) {
            DB::table('orders')->whereNull('name')->whereNotNull('recipient_name')
                ->update(['name' => DB::raw('recipient_name')]);
        }

        // 2. Satukan 'shipping_address' ke 'address'
        if (Schema::hasColumn('orders', 'shipping_address') && Schema::hasColumn('orders', 'address')) {
            DB::table('orders')->whereNull('address')->whereNotNull('shipping_address')
                ->update(['address' => DB::raw('shipping_address')]);
        }

        // 3. Satukan 'total_price' ke 'total_amount'
        if (Schema::hasColumn('orders', 'total_price') && Schema::hasColumn('orders', 'total_amount')) {
            DB::table('orders')->where(function ($q) {
                $q->whereNull('total_amount')->orWhere('total_amount', 0);
            })->whereNotNull('total_price')
              ->update(['total_amount' => DB::raw('total_price')]);
        }

        // 4. Satukan 'resi' ke 'tracking_number'
        if (Schema::hasColumn('orders', 'resi') && Schema::hasColumn('orders', 'tracking_number')) {
            DB::table('orders')->whereNull('tracking_number')->whereNotNull('resi')
                ->update(['tracking_number' => DB::raw('resi')]);
        }

        // Hapus kolom redundan
        Schema::table('orders', function (Blueprint $table) {
            $dropCols = array_filter(
                ['recipient_name', 'shipping_address', 'total_price', 'resi'],
                fn($col) => Schema::hasColumn('orders', $col)
            );
            if (!empty($dropCols)) {
                $table->dropColumn($dropCols);
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'recipient_name')) $table->string('recipient_name')->nullable();
            if (!Schema::hasColumn('orders', 'shipping_address')) $table->text('shipping_address')->nullable();
            if (!Schema::hasColumn('orders', 'total_price')) $table->decimal('total_price', 12, 2)->nullable();
            if (!Schema::hasColumn('orders', 'resi')) $table->string('resi')->nullable();
        });
    }
};