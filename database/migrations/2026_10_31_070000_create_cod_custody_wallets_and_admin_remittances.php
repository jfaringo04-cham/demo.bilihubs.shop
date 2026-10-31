<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logistic_cod_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logistic_id')
                ->unique()
                ->constrained('logistics')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('available_balance_minor')->default(0);
            $table->unsignedBigInteger('pending_balance_minor')->default(0);
            $table->timestamps();
        });

        Schema::create('admin_wallets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('available_balance_minor')->default(0);
            $table->timestamps();
        });

        Schema::create('logistic_admin_remittances', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('logistic_id')
                ->constrained('logistics')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->string('status')->default('pending');
            $table->string('transfer_method')->nullable();
            $table->string('transfer_reference')->nullable();
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('submitted_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('confirmed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('rejected_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();

            $table->index(['logistic_id', 'status']);
        });

        Schema::create('logistic_cod_wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logistic_cod_wallet_id')
                ->constrained('logistic_cod_wallets')
                ->cascadeOnDelete();
            $table->foreignId('cod_remittance_id')
                ->nullable()
                ->unique()
                ->constrained('cod_remittances')
                ->nullOnDelete();
            $table->foreignId('logistic_admin_remittance_id')
                ->nullable()
                ->constrained('logistic_admin_remittances')
                ->nullOnDelete();
            $table->string('type');
            $table->bigInteger('amount_minor');
            $table->unsignedBigInteger('balance_after_minor');
            $table->string('reference')->nullable();
            $table->text('description')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['logistic_cod_wallet_id', 'type']);
        });

        Schema::create('admin_wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_wallet_id')
                ->constrained('admin_wallets')
                ->cascadeOnDelete();
            $table->foreignId('logistic_admin_remittance_id')
                ->nullable()
                ->unique()
                ->constrained('logistic_admin_remittances')
                ->nullOnDelete();
            $table->string('type');
            $table->bigInteger('amount_minor');
            $table->unsignedBigInteger('balance_after_minor');
            $table->string('reference')->nullable();
            $table->text('description')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['admin_wallet_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_wallet_transactions');
        Schema::dropIfExists('logistic_cod_wallet_transactions');
        Schema::dropIfExists('logistic_admin_remittances');
        Schema::dropIfExists('admin_wallets');
        Schema::dropIfExists('logistic_cod_wallets');
    }
};
