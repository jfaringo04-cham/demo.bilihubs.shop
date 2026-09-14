<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blacklisted_images', function (Blueprint $table) {
            $table->string('phash', 64)->nullable()->after('image_hash');
        });
    }

    public function down(): void
    {
        Schema::table('blacklisted_images', function (Blueprint $table) {
            $table->dropColumn('phash');
        });
    }
};
