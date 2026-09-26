<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_price_history', function (Blueprint $table) {
            $table->unsignedBigInteger('price_minor')->nullable()->after('price');
        });

        // Convert existing peso prices to integer centavos.
        DB::statement('
            UPDATE product_price_history
            SET price_minor = ROUND(price * 100)::bigint
            WHERE price IS NOT NULL
        ');
    }

    public function down(): void
    {
        Schema::table('product_price_history', function (Blueprint $table) {
            $table->dropColumn('price_minor');
        });
    }
};