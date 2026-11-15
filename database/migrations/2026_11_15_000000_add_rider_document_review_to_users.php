<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('license_document')->nullable();
            $table->date('license_expires_on')->nullable();
            $table->string('vehicle_plate_number', 20)->nullable();
            $table->json('rider_document_reviews')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'license_document',
                'license_expires_on',
                'vehicle_plate_number',
                'rider_document_reviews',
            ]);
        });
    }
};
