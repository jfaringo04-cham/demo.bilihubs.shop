<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('logistics', 'logo')) {
            Schema::table('logistics', function (Blueprint $table) {
                $table->string('logo')->nullable()->after('approved_at');
            });
        }
    }

    public function down(): void
    {
        Schema::table('logistics', function (Blueprint $table) {
            $table->dropColumn('logo');
        });
    }
};
