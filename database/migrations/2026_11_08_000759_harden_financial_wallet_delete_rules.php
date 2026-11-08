<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Financial foreign keys that must preserve historical records.
     *
     * Format:
     * table, constraint, column, referenced table, delete rule
     */
    private array $financialForeignKeys = [
        [
            'admin_wallet_transactions',
            'admin_wallet_transactions_admin_wallet_id_foreign',
            'admin_wallet_id',
            'admin_wallets',
            'RESTRICT',
        ],
        [
            'admin_wallet_transactions',
            'admin_wallet_transactions_logistic_admin_remittance_id_foreign',
            'logistic_admin_remittance_id',
            'logistic_admin_remittances',
            'RESTRICT',
        ],
        [
            'admin_wallet_transactions',
            'admin_wallet_transactions_seller_settlement_id_foreign',
            'seller_settlement_id',
            'seller_settlements',
            'RESTRICT',
        ],
        [
            'logistic_admin_remittances',
            'logistic_admin_remittances_logistic_id_foreign',
            'logistic_id',
            'logistics',
            'RESTRICT',
        ],
        [
            'logistic_cod_wallet_transactions',
            'logistic_cod_wallet_transactions_cod_remittance_id_foreign',
            'cod_remittance_id',
            'cod_remittances',
            'RESTRICT',
        ],
        [
            'logistic_cod_wallet_transactions',
            'logistic_cod_wallet_transactions_logistic_admin_remittance_id_f',
            'logistic_admin_remittance_id',
            'logistic_admin_remittances',
            'RESTRICT',
        ],
        [
            'logistic_cod_wallet_transactions',
            'logistic_cod_wallet_transactions_logistic_cod_wallet_id_foreign',
            'logistic_cod_wallet_id',
            'logistic_cod_wallets',
            'RESTRICT',
        ],
        [
            'logistic_cod_wallets',
            'logistic_cod_wallets_logistic_id_foreign',
            'logistic_id',
            'logistics',
            'RESTRICT',
        ],
        [
            'seller_wallet_transactions',
            'seller_wallet_transactions_seller_settlement_id_foreign',
            'seller_settlement_id',
            'seller_settlements',
            'RESTRICT',
        ],
        [
            'seller_wallet_transactions',
            'seller_wallet_transactions_seller_wallet_id_foreign',
            'seller_wallet_id',
            'seller_wallets',
            'RESTRICT',
        ],
        [
            'seller_wallets',
            'seller_wallets_seller_id_foreign',
            'seller_id',
            'sellers',
            'RESTRICT',
        ],
        [
            'seller_withdrawals',
            'seller_withdrawals_seller_id_foreign',
            'seller_id',
            'sellers',
            'RESTRICT',
        ],
        [
            'seller_withdrawals',
            'seller_withdrawals_seller_wallet_id_foreign',
            'seller_wallet_id',
            'seller_wallets',
            'RESTRICT',
        ],
    ];

    /**
     * Original delete rules for rollback.
     */
    private array $originalDeleteRules = [
        'admin_wallet_transactions_admin_wallet_id_foreign' => 'CASCADE',
        'admin_wallet_transactions_logistic_admin_remittance_id_foreign' => 'SET NULL',
        'admin_wallet_transactions_seller_settlement_id_foreign' => 'SET NULL',
        'logistic_admin_remittances_logistic_id_foreign' => 'CASCADE',
        'logistic_cod_wallet_transactions_cod_remittance_id_foreign' => 'SET NULL',
        'logistic_cod_wallet_transactions_logistic_admin_remittance_id_f' => 'SET NULL',
        'logistic_cod_wallet_transactions_logistic_cod_wallet_id_foreign' => 'CASCADE',
        'logistic_cod_wallets_logistic_id_foreign' => 'CASCADE',
        'seller_wallet_transactions_seller_settlement_id_foreign' => 'SET NULL',
        'seller_wallet_transactions_seller_wallet_id_foreign' => 'CASCADE',
        'seller_wallets_seller_id_foreign' => 'CASCADE',
        'seller_withdrawals_seller_id_foreign' => 'CASCADE',
        'seller_withdrawals_seller_wallet_id_foreign' => 'CASCADE',
    ];

    /**
     * Ensure this migration only runs on PostgreSQL.
     */
    private function assertPostgres(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            throw new RuntimeException(
                'This financial hardening migration requires PostgreSQL.'
            );
        }
    }

    public function up(): void
    {
        $this->assertPostgres();

        // Preserve existing production wallet records.
        // Never delete legacy wallets during financial hardening.
        // CodCustodyService handles active wallet selection.

        DB::transaction(function () {
            foreach ($this->financialForeignKeys as $foreignKey) {
                $this->replaceForeignKey(
                    $foreignKey[0],
                    $foreignKey[1],
                    $foreignKey[2],
                    $foreignKey[3],
                    'RESTRICT'
                );
            }

            // Only enforce singleton when existing records permit it.
            // Legacy multi-wallet databases must retain their existing rows.
            if (DB::table('admin_wallets')->count() <= 1) {
                DB::statement(
                    'CREATE UNIQUE INDEX IF NOT EXISTS admin_wallets_singleton_idx
                     ON public.admin_wallets ((true))'
                );
            }
        });
    }

    public function down(): void
    {
        $this->assertPostgres();

        DB::transaction(function () {
            DB::statement(
                'DROP INDEX IF EXISTS public.admin_wallets_singleton_idx'
            );

            foreach ($this->financialForeignKeys as $foreignKey) {
                $constraintName = $foreignKey[1];

                $this->replaceForeignKey(
                    $foreignKey[0],
                    $constraintName,
                    $foreignKey[2],
                    $foreignKey[3],
                    $this->originalDeleteRules[$constraintName]
                );
            }
        });
    }

    private function replaceForeignKey(
        string $table,
        string $constraint,
        string $column,
        string $referencedTable,
        string $deleteRule
    ): void {
        $allowedRules = ['RESTRICT', 'CASCADE', 'SET NULL'];

        if (!in_array($deleteRule, $allowedRules, true)) {
            throw new RuntimeException(
                'Invalid foreign key delete rule.'
            );
        }

        DB::statement(
            "ALTER TABLE public.\"{$table}\"
             DROP CONSTRAINT IF EXISTS \"{$constraint}\""
        );

        DB::statement(
            "ALTER TABLE public.\"{$table}\"
             ADD CONSTRAINT \"{$constraint}\"
             FOREIGN KEY (\"{$column}\")
             REFERENCES public.\"{$referencedTable}\" (\"id\")
             ON DELETE {$deleteRule}"
        );
    }
};
