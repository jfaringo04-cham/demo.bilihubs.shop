<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('logistic_wallets', function (Blueprint $table) {
            $table->bigInteger('pending_balance_minor')->default(0)->after('available_balance_minor');
        });

        Schema::create('logistic_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('logistic_wallet_id');
            $table->unsignedBigInteger('logistic_id');
            $table->bigInteger('amount_minor');
            $table->string('payment_method', 50);
            $table->string('account_name', 150);
            $table->string('account_number', 100);
            $table->string('bank_name', 150)->nullable();
            $table->string('status', 30)->default('pending');
            $table->string('transaction_reference', 200)->nullable()->unique();
            $table->text('logistic_notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->timestamps();

            $table->foreign('logistic_wallet_id')
                ->references('id')->on('logistic_wallets')->cascadeOnDelete();
            $table->foreign('logistic_id')
                ->references('id')->on('logistics')->cascadeOnDelete();
            $table->foreign('processed_by')
                ->references('id')->on('users')->nullOnDelete();

            $table->index(['logistic_id', 'status']);
            $table->index(['status', 'requested_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logistic_withdrawals');

        Schema::table('logistic_wallets', function (Blueprint $table) {
            $table->dropColumn('pending_balance_minor');
        });
    }
};
