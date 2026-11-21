<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    Schema::create('order_items', function (Blueprint $table) {
        $table->id();
        $table->foreignId('order_id')->constrained()->onDelete('cascade');
        // nullable constraint agar jika produk dihapus, history pesanan tidak hilang
        $table->foreignId('product_id')->nullable()->constrained()->onDelete('set null'); 
        $table->string('product_name');
        $table->string('variant_size')->nullable();
        $table->string('variant_color')->nullable();
        $table->decimal('price', 12, 2);
        $table->integer('quantity');
        $table->timestamps();
    });
}

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};