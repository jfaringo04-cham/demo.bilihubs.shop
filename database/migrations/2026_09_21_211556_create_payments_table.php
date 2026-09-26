<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            $table->string('method', 50);
            $table->string('status', 50)->default('pending');

            // Milestone requirement:
            // Money is stored as integer centavos, not decimal/float.
            $table->unsignedBigInteger('amount_minor');

            $table->string('currency', 3)->default('PHP');

            $table->string('provider')->nullable();
            $table->string('provider_reference')->nullable();

            $table->timestamp('paid_at')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index('order_id');
            $table->index('status');
            $table->index('provider_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};