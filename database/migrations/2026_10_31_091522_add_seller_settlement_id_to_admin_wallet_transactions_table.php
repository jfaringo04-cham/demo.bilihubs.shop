<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_wallet_transactions', function (Blueprint $table) {
            $table->foreignId('seller_settlement_id')
                ->nullable()
                ->after('logistic_admin_remittance_id')
                ->constrained('seller_settlements')
                ->nullOnDelete();

            $table->unique(
                ['seller_settlement_id', 'type'],
                'admin_wallet_txn_settlement_type_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('admin_wallet_transactions', function (Blueprint $table) {
            $table->dropUnique(
                'admin_wallet_txn_settlement_type_unique'
            );

            $table->dropConstrainedForeignId(
                'seller_settlement_id'
            );
        });
    }
};
