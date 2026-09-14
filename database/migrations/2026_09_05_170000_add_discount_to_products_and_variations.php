<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('discount_percent', 5, 2)->nullable()->after('price')->default(0);
            $table->decimal('discounted_price', 10, 2)->nullable()->after('discount_percent');
            $table->timestamp('discount_starts_at')->nullable()->after('discounted_price');
            $table->timestamp('discount_ends_at')->nullable()->after('discount_starts_at');
        });

        Schema::table('product_variations', function (Blueprint $table) {
            $table->decimal('discount_percent', 5, 2)->nullable()->after('price')->default(0);
            $table->decimal('discounted_price', 10, 2)->nullable()->after('discount_percent');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['discount_percent', 'discounted_price', 'discount_starts_at', 'discount_ends_at']);
        });
        Schema::table('product_variations', function (Blueprint $table) {
            $table->dropColumn(['discount_percent', 'discounted_price']);
        });
    }
};
