<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('logistic_id')->nullable()->after('status')->constrained()->nullOnDelete();
            $table->string('logistic_status', 50)->nullable()->after('logistic_id');
            $table->timestamp('logistic_approved_at')->nullable()->after('logistic_status');
            $table->text('logistic_rejection_reason')->nullable()->after('logistic_approved_at');

            $table->index(['logistic_id', 'logistic_status']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['logistic_id', 'logistic_status']);
            $table->dropColumn(['logistic_id', 'logistic_status', 'logistic_approved_at', 'logistic_rejection_reason']);
        });
    }
};
