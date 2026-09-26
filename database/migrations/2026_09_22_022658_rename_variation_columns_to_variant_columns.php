<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // cart_items.variation_id -> variant_id
        Schema::table('cart_items', function (Blueprint $table) {
            $table->renameColumn('variation_id', 'variant_id');
        });

        // order_items.variation_id -> variant_id
        Schema::table('order_items', function (Blueprint $table) {
            $table->renameColumn('variation_id', 'variant_id');
        });

        // Rename pivot table first.
        Schema::rename('product_variation_size', 'product_variant_size');

        // product_variation_id -> product_variant_id
        Schema::table('product_variant_size', function (Blueprint $table) {
            $table->renameColumn(
                'product_variation_id',
                'product_variant_id'
            );
        });
    }

    public function down(): void
    {
        Schema::table('product_variant_size', function (Blueprint $table) {
            $table->renameColumn(
                'product_variant_id',
                'product_variation_id'
            );
        });

        Schema::rename('product_variant_size', 'product_variation_size');

        Schema::table('order_items', function (Blueprint $table) {
            $table->renameColumn('variant_id', 'variation_id');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->renameColumn('variant_id', 'variation_id');
        });
    }
};