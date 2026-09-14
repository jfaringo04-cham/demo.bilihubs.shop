<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('daily_pickups_completed')->default(0)->after('current_load');
            $table->unsignedInteger('daily_deliveries_completed')->default(0)->after('daily_pickups_completed');
            $table->date('last_quota_reset_date')->nullable()->after('daily_deliveries_completed');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['daily_pickups_completed', 'daily_deliveries_completed', 'last_quota_reset_date']);
        });
    }
};
