<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Palitan ang status column ng string + default
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('status')->default('pending')->change();
        });

        // Drop old constraint kung meron
        DB::statement("ALTER TABLE shipments DROP CONSTRAINT IF EXISTS shipments_status_check;");

        // Add updated CHECK constraint
        DB::statement("ALTER TABLE shipments ADD CONSTRAINT shipments_status_check 
            CHECK (status IN (
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
            ));");
    }

    public function down(): void
    {
        // Rollback: balik sa string + default 'pending'
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('status')->default('pending')->change();
        });

        // Drop constraint kapag rollback
        DB::statement("ALTER TABLE shipments DROP CONSTRAINT IF EXISTS shipments_status_check;");
    }
};
