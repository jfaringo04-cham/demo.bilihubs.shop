<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commissions', function (Blueprint $table) {
            $table->unsignedBigInteger('order_total_minor')
                ->nullable()
                ->after('order_total');

            $table->unsignedBigInteger('amount_minor')
                ->nullable()
                ->after('amount');
        });

        // Convert existing peso values to integer centavos.
        DB::statement('
            UPDATE commissions
            SET
                order_total_minor = ROUND(order_total * 100)::bigint,
                amount_minor = ROUND(amount * 100)::bigint
        ');
    }

    public function down(): void
    {
        Schema::table('commissions', function (Blueprint $table) {
            $table->dropColumn([
                'order_total_minor',
                'amount_minor',
            ]);
        });
    }
};