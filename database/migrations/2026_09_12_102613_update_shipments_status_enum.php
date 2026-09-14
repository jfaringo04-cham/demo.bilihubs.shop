<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->enum('status', [
                'pending',
                'assigned',
                'picked_up',
                'in_transit',
                'at_sorting_center',
                'sorted',
                'staged',
                'delivered',
                'cancelled',
                'delayed',
                'delivery_failed'
            ])->default('pending')->change();
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->enum('status', [
                'pending',
                'assigned',
                'picked_up',
                'in_transit',
                'delivered',
                'cancelled',
                'delayed'
            ])->default('pending')->change();
        });
    }
};
