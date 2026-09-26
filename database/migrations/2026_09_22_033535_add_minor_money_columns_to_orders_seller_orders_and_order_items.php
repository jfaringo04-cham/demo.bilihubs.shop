<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('subtotal_minor')->nullable();
            $table->unsignedBigInteger('tax_minor')->nullable();
            $table->unsignedBigInteger('shipping_minor')->nullable();
            $table->unsignedBigInteger('total_minor')->nullable();
            $table->unsignedBigInteger('amount_collected_minor')->nullable();
        });

        Schema::table('seller_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('subtotal_minor')->nullable();
            $table->unsignedBigInteger('shipping_minor')->nullable();
            $table->unsignedBigInteger('total_minor')->nullable();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('price_minor')->nullable();
            $table->unsignedBigInteger('subtotal_minor')->nullable();
        });

        // Backfill existing Order money values from pesos to centavos.
        DB::statement("
            UPDATE orders
            SET subtotal_minor = ROUND(subtotal * 100)::bigint
            WHERE subtotal IS NOT NULL
        ");

        DB::statement("
            UPDATE orders
            SET tax_minor = ROUND(tax * 100)::bigint
            WHERE tax IS NOT NULL
        ");

        DB::statement("
            UPDATE orders
            SET shipping_minor = ROUND(shipping * 100)::bigint
            WHERE shipping IS NOT NULL
        ");

        DB::statement("
            UPDATE orders
            SET total_minor = ROUND(total * 100)::bigint
            WHERE total IS NOT NULL
        ");

        DB::statement("
            UPDATE orders
            SET amount_collected_minor = ROUND(amount_collected * 100)::bigint
            WHERE amount_collected IS NOT NULL
        ");

        // Backfill SellerOrder money values.
        DB::statement("
            UPDATE seller_orders
            SET subtotal_minor = ROUND(subtotal * 100)::bigint
            WHERE subtotal IS NOT NULL
        ");

        DB::statement("
            UPDATE seller_orders
            SET shipping_minor = ROUND(shipping * 100)::bigint
            WHERE shipping IS NOT NULL
        ");

        DB::statement("
            UPDATE seller_orders
            SET total_minor = ROUND(total * 100)::bigint
            WHERE total IS NOT NULL
        ");

        // Backfill OrderItem money values.
        DB::statement("
            UPDATE order_items
            SET price_minor = ROUND(price * 100)::bigint
            WHERE price IS NOT NULL
        ");

        DB::statement("
            UPDATE order_items
            SET subtotal_minor = ROUND(subtotal * 100)::bigint
            WHERE subtotal IS NOT NULL
        ");
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn([
                'price_minor',
                'subtotal_minor',
            ]);
        });

        Schema::table('seller_orders', function (Blueprint $table) {
            $table->dropColumn([
                'subtotal_minor',
                'shipping_minor',
                'total_minor',
            ]);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'subtotal_minor',
                'tax_minor',
                'shipping_minor',
                'total_minor',
                'amount_collected_minor',
            ]);
        });
    }
};