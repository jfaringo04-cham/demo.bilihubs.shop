<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->integer('max_capacity')->default(10)->after('vehicle_type');
            $table->integer('current_load')->default(0)->after('max_capacity');
            $table->enum('availability_status', ['available', 'busy', 'offline'])->default('offline')->after('current_load');
            $table->string('assigned_zone')->nullable()->after('availability_status');
            $table->timestamp('last_active_at')->nullable()->after('assigned_zone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'max_capacity',
                'current_load',
                'availability_status',
                'assigned_zone',
                'last_active_at',
            ]);
        });
    }
};
