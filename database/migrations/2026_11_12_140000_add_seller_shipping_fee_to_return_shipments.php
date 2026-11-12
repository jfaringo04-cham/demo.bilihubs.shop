<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('return_shipments', function (Blueprint $table) {
            $table->unsignedBigInteger('seller_shipping_fee_minor')->nullable();
            $table->timestamp('seller_shipping_fee_posted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('return_shipments', function (Blueprint $table) {
            $table->dropColumn(['seller_shipping_fee_minor', 'seller_shipping_fee_posted_at']);
        });
    }
};
