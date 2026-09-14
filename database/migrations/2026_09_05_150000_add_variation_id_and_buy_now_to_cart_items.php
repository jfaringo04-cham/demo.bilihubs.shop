<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->foreignId('variation_id')->nullable()->after('size_id')->constrained('product_variations')->nullOnDelete();
            $table->boolean('is_buy_now')->default(false)->after('variation_id');
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('variation_id');
            $table->dropColumn('is_buy_now');
        });
    }
};
