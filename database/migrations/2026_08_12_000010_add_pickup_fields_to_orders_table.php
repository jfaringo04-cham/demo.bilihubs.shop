<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('ready_for_pickup')->default(false)->after('delivery_zone');
            $table->timestamp('picked_up_at')->nullable()->after('ready_for_pickup');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['ready_for_pickup', 'picked_up_at']);
        });
    }
};
