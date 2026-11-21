<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    Schema::create('orders', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->onDelete('cascade');
        $table->string('order_number')->unique(); // Cth: ORD-8A9B2C
        $table->string('name');
        $table->string('phone');
        $table->string('email')->nullable();
        $table->text('address')->nullable();
        $table->string('shipping_method');
        $table->string('payment_method');
        $table->decimal('subtotal', 12, 2);
        $table->decimal('shipping_cost', 12, 2);
        $table->decimal('total_amount', 12, 2);
        $table->enum('status', ['pending', 'diproses', 'dikirim', 'selesai', 'dibatalkan'])->default('pending');
        $table->string('resi')->nullable(); // Nomor Resi
        $table->text('notes')->nullable();
        $table->timestamps();
    });
}

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};