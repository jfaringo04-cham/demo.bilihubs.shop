<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('sorting_area')->nullable()->after('notes');
            $table->string('delivery_zone')->nullable()->after('sorting_area');
            $table->string('delivery_type', 50)->default('standard')->after('delivery_zone');
            $table->string('sorting_status', 50)->default('pending')->after('delivery_type');
            $table->string('rack_number')->nullable()->after('sorting_status');
            $table->timestamp('received_at')->nullable()->after('rack_number');
            $table->timestamp('scanned_at')->nullable()->after('received_at');
            $table->timestamp('sorted_at')->nullable()->after('scanned_at');
            $table->timestamp('staged_at')->nullable()->after('sorted_at');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn([
                'sorting_area',
                'delivery_zone',
                'delivery_type',
                'sorting_status',
                'rack_number',
                'received_at',
                'scanned_at',
                'sorted_at',
                'staged_at',
            ]);
        });
    }
};
