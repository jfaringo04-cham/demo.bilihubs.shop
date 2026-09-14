<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        try {
            DB::statement('ALTER TABLE cart_items DROP INDEX cart_items_user_id_product_id_unique');
        } catch (\Exception $e) {
            // Index might not exist
        }

        try {
            DB::statement('ALTER TABLE cart_items ADD UNIQUE KEY cart_items_user_id_product_id_size_id_unique (user_id, product_id, size_id)');
        } catch (\Exception $e) {
            // Unique constraint might already exist
        }
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'product_id', 'size_id']);
            $table->unique(['user_id', 'product_id']);
        });
    }
};
