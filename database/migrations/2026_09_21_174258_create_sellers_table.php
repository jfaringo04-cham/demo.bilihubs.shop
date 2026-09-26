<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sellers', function (Blueprint $table) {
            $table->id();

            // Owner of the shop
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Shop information
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            // Shop images
            $table->string('logo_path')->nullable();
            $table->string('banner_path')->nullable();

            // Seller approval
            $table->string('status')->default('pending');
            $table->text('rejection_reason')->nullable();

            // Commission in basis points
            $table->unsignedInteger('commission_bps')->default(0);

            // Pickup address
            $table->foreignId('pickup_address_id')
                ->nullable()
                ->constrained('addresses')
                ->nullOnDelete();

            $table->timestamps();

            // One user can own only one seller/shop record
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sellers');
    }
};