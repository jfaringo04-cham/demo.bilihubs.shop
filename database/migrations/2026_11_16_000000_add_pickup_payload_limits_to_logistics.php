<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logistics', function (Blueprint $table) {
            $table->json('pickup_payload_limits_kg')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('logistics', function (Blueprint $table) {
            $table->dropColumn('pickup_payload_limits_kg');
        });
    }
};
