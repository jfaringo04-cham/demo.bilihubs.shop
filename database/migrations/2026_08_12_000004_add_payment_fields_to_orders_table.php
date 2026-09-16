<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_method', 50)->default('cod')->after('delivery_notes');
            $table->string('payment_status', 50)->default('unpaid')->after('payment_method');
            $table->decimal('amount_collected', 10, 2)->nullable()->after('payment_status');
            $table->timestamp('collected_at')->nullable()->after('amount_collected');
            $table->foreignId('collected_by')->nullable()->after('collected_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['collected_by']);
            $table->dropColumn(['payment_method', 'payment_status', 'amount_collected', 'collected_at', 'collected_by']);
        });
    }
};
