<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Change delivery_status column to string + default
        Schema::table('orders', function (Blueprint $table) {
            $table->string('delivery_status')->default('pending')->change();
        });

        // Drop old constraint kung meron
        DB::statement("ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_delivery_status_check;");

        // Add updated CHECK constraint para limit values sa Postgres
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_delivery_status_check 
            CHECK (delivery_status IN (
                'pending',
                'assigned',
                'assigned_to_rider',
                'in_transit',
                'out_for_delivery',
                'delivered_to_sorting_center',
                'ready_for_delivery_pickup',
                'picked_up_from_sorting_center',
                'delivered',
                'delivery_failed',
                'failed',
                'on_the_way'
            ));");
    }

    public function down(): void
    {
        // Rollback: balik sa string + default 'pending'
        Schema::table('orders', function (Blueprint $table) {
            $table->string('delivery_status')->default('pending')->change();
        });

        // Drop constraint kapag rollback
        DB::statement("ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_delivery_status_check;");
    }
};
