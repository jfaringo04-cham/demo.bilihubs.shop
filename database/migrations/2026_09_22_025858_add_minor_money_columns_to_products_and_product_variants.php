<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('price_minor')->nullable();
            $table->unsignedBigInteger('discounted_price_minor')->nullable();
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->unsignedBigInteger('price_minor')->nullable();
            $table->unsignedBigInteger('discounted_price_minor')->nullable();
        });

        // Convert existing peso values to integer centavos.
        DB::statement("
            UPDATE products
            SET price_minor = ROUND(price * 100)::bigint
            WHERE price IS NOT NULL
        ");

        DB::statement("
            UPDATE products
            SET discounted_price_minor = ROUND(discounted_price * 100)::bigint
            WHERE discounted_price IS NOT NULL
        ");

        DB::statement("
            UPDATE product_variants
            SET price_minor = ROUND(price * 100)::bigint
            WHERE price IS NOT NULL
        ");

        DB::statement("
            UPDATE product_variants
            SET discounted_price_minor = ROUND(discounted_price * 100)::bigint
            WHERE discounted_price IS NOT NULL
        ");
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn([
                'price_minor',
                'discounted_price_minor',
            ]);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'price_minor',
                'discounted_price_minor',
            ]);
        });
    }
};