<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->text('reschedule_reason')->nullable()->after('failure_reason');
            $table->timestamp('reschedule_requested_at')->nullable()->after('reschedule_reason');
            $table->timestamp('rescheduled_at')->nullable()->after('reschedule_requested_at');
            $table->string('status')->default('pending')->change();
        });

        // Drop old constraint kung meron
        DB::statement("ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_status_check;");

        // Add updated constraint
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_status_check 
            CHECK (status IN (
                'pending',
                'processing',
                'shipped',
                'delivered',
                'cancelled',
                'reschedule_requested',
                'rescheduled',
                'return_requested',
                'returned'
            ));");
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['reschedule_reason', 'reschedule_requested_at', 'rescheduled_at']);
            $table->string('status')->default('pending')->change();
        });

        // Drop constraint kapag rollback
        DB::statement("ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_status_check;");
    }
};
