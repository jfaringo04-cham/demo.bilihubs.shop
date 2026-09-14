<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('region_name')->nullable()->after('region');
            $table->string('province_name')->nullable()->after('province');
            $table->string('municipality_name')->nullable()->after('municipality');
            $table->string('barangay_name')->nullable()->after('barangay');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['region_name', 'province_name', 'municipality_name', 'barangay_name']);
        });
    }
};
