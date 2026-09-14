<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('store_name')->nullable()->after('address');
            $table->string('business_address')->nullable()->after('store_name');
            $table->string('business_documents')->nullable()->after('business_address');
            $table->string('id_verification')->nullable()->after('business_documents');
            $table->string('mobile_number')->nullable()->after('id_verification');
            $table->text('selling_categories')->nullable()->after('mobile_number');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['store_name', 'business_address', 'business_documents', 'id_verification', 'mobile_number', 'selling_categories']);
        });
    }
};
