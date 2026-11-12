<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logistics', function (Blueprint $table) {
            $table->text('rider_application_terms')->nullable();
            $table->text('rider_application_privacy')->nullable();
        });

        Schema::create('rider_policy_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('logistic_id')->nullable()->constrained('logistics')->nullOnDelete();
            $table->string('company_name');
            $table->text('terms');
            $table->text('privacy_policy');
            $table->timestamp('accepted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_policy_acceptances');

        Schema::table('logistics', function (Blueprint $table) {
            $table->dropColumn(['rider_application_terms', 'rider_application_privacy']);
        });
    }
};
