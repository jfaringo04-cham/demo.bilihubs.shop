<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('notes');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->decimal('rider_latitude', 10, 7)->nullable()->after('longitude');
            $table->decimal('rider_longitude', 10, 7)->nullable()->after('rider_latitude');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'rider_latitude', 'rider_longitude']);
        });
    }
};
