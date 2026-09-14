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
        });

        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled', 'reschedule_requested', 'rescheduled', 'return_requested', 'returned') DEFAULT 'pending'");
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['reschedule_reason', 'reschedule_requested_at', 'rescheduled_at']);
        });

        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending'");
    }
};
