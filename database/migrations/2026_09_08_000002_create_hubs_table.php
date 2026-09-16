<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hubs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logistic_id')->constrained('logistics')->cascadeOnDelete();
            $table->string('name');
            $table->string('address');
            $table->string('api_address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('status', 50)->default('active');
            $table->timestamps();

            $table->index(['logistic_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hubs');
    }
};
