<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'price',
                'discounted_price',
            ]);
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn([
                'price',
                'discounted_price',
            ]);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'subtotal',
                'tax',
                'shipping',
                'total',
                'amount_collected',
            ]);
        });

        Schema::table('seller_orders', function (Blueprint $table) {
            $table->dropColumn([
                'subtotal',
                'shipping',
                'total',
            ]);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn([
                'price',
                'subtotal',
            ]);
        });

        Schema::table('commissions', function (Blueprint $table) {
            $table->dropColumn([
                'order_total',
                'amount',
            ]);
        });

        Schema::table('product_price_history', function (Blueprint $table) {
            $table->dropColumn('price');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('discounted_price', 10, 2)->nullable();
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('discounted_price', 10, 2)->nullable();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('subtotal', 10, 2)->nullable();
            $table->decimal('tax', 10, 2)->nullable();
            $table->decimal('shipping', 10, 2)->nullable();
            $table->decimal('total', 10, 2)->nullable();
            $table->decimal('amount_collected', 10, 2)->nullable();
        });

        Schema::table('seller_orders', function (Blueprint $table) {
            $table->decimal('subtotal', 10, 2)->nullable();
            $table->decimal('shipping', 10, 2)->nullable();
            $table->decimal('total', 10, 2)->nullable();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('subtotal', 10, 2)->nullable();
        });

        Schema::table('commissions', function (Blueprint $table) {
            $table->decimal('order_total', 10, 2)->nullable();
            $table->decimal('amount', 10, 2)->nullable();
        });

        Schema::table('product_price_history', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->nullable();
        });
    }
};