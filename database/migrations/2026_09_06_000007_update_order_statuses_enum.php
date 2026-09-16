<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Change status column to string + default
        Schema::table('orders', function (Blueprint $table) {
            $table->string('status')->default('placed')->change();
        });

        // Drop old constraint kung meron
        DB::statement("ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_status_check;");

        // Add updated CHECK constraint para limit values sa Postgres
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_status_check 
            CHECK (status IN (
                'placed',
                'confirmed',
                'preparing',
                'ready_for_pickup',
                'picked_up',
                'at_sorting_center',
                'sorted',
                'assigned_to_rider',
                'out_for_delivery',
                'delivered',
                'completed',
                'delivery_failed',
                'returned',
                'cancelled',
                'reschedule_requested',
                'rescheduled',
                'return_requested',
                'pending',
                'processing',
                'shipped'
            ));");
    }

    public function down(): void
    {
        // Rollback: balik sa string + default 'pending'
        Schema::table('orders', function (Blueprint $table) {
            $table->string('status')->default('pending')->change();
        });

        // Drop constraint kapag rollback
        DB::statement("ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_status_check;");
    }
};
