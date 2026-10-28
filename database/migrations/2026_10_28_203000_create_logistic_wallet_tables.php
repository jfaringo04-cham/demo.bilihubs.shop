<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('logistic_wallets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('logistic_id')->unique();
            $table->bigInteger('available_balance_minor')->default(0);
            $table->timestamps();

            $table->foreign('logistic_id')
                ->references('id')
                ->on('logistics')
                ->cascadeOnDelete();
        });

        Schema::create('logistic_wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('logistic_wallet_id');
            $table->unsignedBigInteger('shipment_id')->nullable();
            $table->string('type', 50);
            $table->bigInteger('amount_minor')->default(0);
            $table->bigInteger('balance_after_minor')->default(0);
            $table->string('reference', 150)->nullable();
            $table->string('description')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('logistic_wallet_id')
                ->references('id')
                ->on('logistic_wallets')
                ->cascadeOnDelete();

            $table->index(['logistic_wallet_id', 'created_at']);
            $table->unique(
                ['shipment_id', 'type'],
                'logistic_wallet_shipment_type_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logistic_wallet_transactions');
        Schema::dropIfExists('logistic_wallets');
    }
};
