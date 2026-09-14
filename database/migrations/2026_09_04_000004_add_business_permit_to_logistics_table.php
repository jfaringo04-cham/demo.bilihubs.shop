<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('logistics', 'business_permit')) {
            Schema::table('logistics', function (Blueprint $table) {
                $table->string('business_permit')->nullable()->after('logo');
            });
        }
    }

    public function down(): void
    {
        Schema::table('logistics', function (Blueprint $table) {
            $table->dropColumn('business_permit');
        });
    }
};
