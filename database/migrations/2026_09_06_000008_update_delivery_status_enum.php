<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE orders MODIFY COLUMN delivery_status ENUM('pending','assigned','assigned_to_rider','in_transit','out_for_delivery','delivered_to_sorting_center','ready_for_delivery_pickup','picked_up_from_sorting_center','delivered','delivery_failed','failed','on_the_way') DEFAULT 'pending'");

        DB::statement("UPDATE orders SET delivery_status = 'out_for_delivery' WHERE delivery_status = 'on_the_way'");
        DB::statement("UPDATE orders SET delivery_status = 'delivery_failed' WHERE delivery_status = 'failed'");

        DB::statement("ALTER TABLE orders MODIFY COLUMN delivery_status ENUM('pending','assigned','assigned_to_rider','in_transit','out_for_delivery','delivered_to_sorting_center','ready_for_delivery_pickup','picked_up_from_sorting_center','delivered','delivery_failed') DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE orders MODIFY COLUMN delivery_status ENUM('pending','assigned','delivered_to_sorting_center','ready_for_delivery_pickup','picked_up_from_sorting_center','on_the_way','delivered','failed') DEFAULT 'pending'");
    }
};
