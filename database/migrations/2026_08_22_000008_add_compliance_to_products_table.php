<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('compliance_status', 50)->default('pending')->after('stock');
            $table->text('admin_notes')->nullable()->after('compliance_status');
            $table->text('flagged_reason')->nullable()->after('admin_notes');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['compliance_status', 'admin_notes', 'flagged_reason']);
        });
    }
};
