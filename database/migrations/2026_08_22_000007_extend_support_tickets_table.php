<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->enum('type', ['support', 'complaint', 'dispute'])
                ->default('support')->after('user_id');
            $table->foreignId('order_id')->nullable()->constrained('orders')->onDelete('set null')->after('type');
            $table->foreignId('against_user_id')->nullable()->constrained('users')->onDelete('set null')->after('order_id');
            $table->string('evidence')->nullable()->after('against_user_id');
            $table->enum('priority', ['low', 'medium', 'high'])->nullable()->after('evidence');
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('against_user_id');
            $table->dropConstrainedForeignId('order_id');
            $table->dropColumn(['type', 'evidence', 'priority']);
        });
    }
};
