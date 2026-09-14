<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('qr_token', 64)->nullable()->unique()->after('tracking_number');
            $table->timestamp('scanned_at_seller')->nullable()->after('scanned_at');
            $table->string('scanned_by_rider_id')->nullable()->after('scanned_at_seller');
            $table->timestamp('seller_scan_confirmed_at')->nullable()->after('scanned_by_rider_id');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn(['qr_token', 'scanned_at_seller', 'scanned_by_rider_id', 'seller_scan_confirmed_at']);
        });
    }
};
