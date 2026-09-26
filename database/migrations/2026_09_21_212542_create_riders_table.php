<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riders', function (Blueprint $table) {
            $table->id();

            // Rider's login/account
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Logistics company the rider belongs to
            $table->foreignId('logistic_id')
                ->nullable()
                ->constrained('logistics')
                ->nullOnDelete();

            // Existing rider information currently stored in users
            $table->string('vehicle_type')->nullable();
            $table->string('license_number')->nullable();

            $table->string('rider_documents')->nullable();
            $table->string('or_document')->nullable();
            $table->string('cr_document')->nullable();

            // Logistics approval
            $table->string('status', 50)->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('approved_at')->nullable();

            // Capacity / assignment
            $table->unsignedInteger('max_capacity')->default(10);
            $table->unsignedInteger('current_load')->default(0);
            $table->string('availability_status', 50)->default('offline');
            $table->string('assigned_zone')->nullable();
            $table->timestamp('last_active_at')->nullable();

            // Existing rider quota fields
            $table->unsignedInteger('daily_pickups_completed')->default(0);
            $table->unsignedInteger('daily_deliveries_completed')->default(0);
            $table->date('last_quota_reset_date')->nullable();

            $table->timestamps();

            // One rider profile per user account
            $table->unique('user_id');

            $table->index(['logistic_id', 'status']);
            $table->index(['logistic_id', 'availability_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riders');
    }
};