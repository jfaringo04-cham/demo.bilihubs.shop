<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            $table->foreignId('seller_id')
                ->constrained('sellers')
                ->cascadeOnDelete();

            $table->string('status')->default('pending');

            // Temporary decimal money fields.
            // We will convert all monetary fields to integer centavos together later.
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('shipping', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            $table->timestamps();

            // One seller should only have one seller-order
            // inside a single buyer checkout/order.
            $table->unique(['order_id', 'seller_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_orders');
    }
};