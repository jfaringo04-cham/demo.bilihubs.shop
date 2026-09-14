<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('placed', 'confirmed', 'preparing', 'ready_for_pickup', 'picked_up', 'at_sorting_center', 'sorted', 'assigned_to_rider', 'out_for_delivery', 'delivered', 'completed', 'delivery_failed', 'returned', 'cancelled', 'reschedule_requested', 'rescheduled', 'return_requested', 'pending', 'processing', 'shipped') DEFAULT 'placed'");

        DB::statement("UPDATE orders SET status = 'placed' WHERE status = 'pending'");
        DB::statement("UPDATE orders SET status = 'confirmed' WHERE status = 'processing'");
        DB::statement("UPDATE orders SET status = 'ready_for_pickup' WHERE status = 'shipped'");

        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('placed', 'confirmed', 'preparing', 'ready_for_pickup', 'picked_up', 'at_sorting_center', 'sorted', 'assigned_to_rider', 'out_for_delivery', 'delivered', 'completed', 'delivery_failed', 'returned', 'cancelled', 'reschedule_requested', 'rescheduled', 'return_requested') DEFAULT 'placed'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled', 'reschedule_requested', 'rescheduled', 'return_requested') DEFAULT 'pending'");

        DB::statement("UPDATE orders SET status = 'pending' WHERE status = 'placed'");
        DB::statement("UPDATE orders SET status = 'processing' WHERE status = 'confirmed'");
        DB::statement("UPDATE orders SET status = 'shipped' WHERE status = 'ready_for_pickup'");

        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending'");
    }
};
