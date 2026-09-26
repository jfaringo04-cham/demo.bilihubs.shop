<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            // New Milestone 4 relationship:
            // one shipment/parcel belongs to one seller order.
            $table->foreignId('seller_order_id')
                ->nullable()
                ->constrained('seller_orders')
                ->nullOnDelete();

            // New rider profile relationship.
            // Keep legacy rider_id -> users.id temporarily.
            $table->foreignId('rider_profile_id')
                ->nullable()
                ->constrained('riders')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rider_profile_id');
            $table->dropConstrainedForeignId('seller_order_id');
        });
    }
};