<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('return_shipments', function (Blueprint $table) {
            $table->unsignedBigInteger('rider_payout_minor')->nullable();
            $table->timestamp('wallet_settled_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('return_shipments', function (Blueprint $table) {
            $table->dropColumn(['rider_payout_minor', 'wallet_settled_at']);
        });
    }
};
