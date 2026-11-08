<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('service_fee_minor')
                ->nullable()
                ->after('tax_minor');
        });

        Schema::table('seller_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('service_fee_minor')
                ->nullable()
                ->after('subtotal_minor');
        });
    }

    public function down(): void
    {
        Schema::table('seller_orders', function (Blueprint $table) {
            $table->dropColumn('service_fee_minor');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('service_fee_minor');
        });
    }
};
