<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Cek nama kolom total harga yang tersedia di tabel orders
            $afterColumn = collect(['total_price', 'total_amount', 'grand_total', 'subtotal', 'total'])
                ->first(fn ($col) => Schema::hasColumn('orders', $col));

            if (!Schema::hasColumn('orders', 'voucher_code')) {
                $col = $table->string('voucher_code')->nullable();
                if ($afterColumn) {
                    $col->after($afterColumn);
                }
            }

            if (!Schema::hasColumn('orders', 'discount_amount')) {
                $col = $table->decimal('discount_amount', 15, 2)->default(0);
                if (Schema::hasColumn('orders', 'voucher_code')) {
                    $col->after('voucher_code');
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $columns = array_filter(['voucher_code', 'discount_amount'], fn ($col) => Schema::hasColumn('orders', $col));
            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};