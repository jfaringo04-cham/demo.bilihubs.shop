<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Legacy financial relationships that must preserve their history.
     */
    private array $foreignKeys = [
        ['table' => 'seller_orders', 'constraint' => 'seller_orders_order_id_foreign', 'column' => 'order_id', 'referenced_table' => 'orders'],
        ['table' => 'seller_orders', 'constraint' => 'seller_orders_seller_id_foreign', 'column' => 'seller_id', 'referenced_table' => 'sellers'],
        ['table' => 'logistic_wallets', 'constraint' => 'logistic_wallets_logistic_id_foreign', 'column' => 'logistic_id', 'referenced_table' => 'logistics'],
        ['table' => 'logistic_wallet_transactions', 'constraint' => 'logistic_wallet_transactions_logistic_wallet_id_foreign', 'column' => 'logistic_wallet_id', 'referenced_table' => 'logistic_wallets'],
        ['table' => 'logistic_withdrawals', 'constraint' => 'logistic_withdrawals_logistic_wallet_id_foreign', 'column' => 'logistic_wallet_id', 'referenced_table' => 'logistic_wallets'],
        ['table' => 'logistic_withdrawals', 'constraint' => 'logistic_withdrawals_logistic_id_foreign', 'column' => 'logistic_id', 'referenced_table' => 'logistics'],
    ];

    public function up(): void
    {
        $this->assertPostgres();

        DB::transaction(function (): void {
            foreach ($this->foreignKeys as $foreignKey) {
                $this->setDeleteRule($foreignKey, 'RESTRICT');
            }
        });
    }

    public function down(): void
    {
        $this->assertPostgres();

        DB::transaction(function (): void {
            foreach ($this->foreignKeys as $foreignKey) {
                $this->setDeleteRule($foreignKey, 'CASCADE');
            }
        });
    }

    private function assertPostgres(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            throw new RuntimeException(
                'This financial hardening migration requires PostgreSQL.'
            );
        }
    }

    private function setDeleteRule(array $foreignKey, string $deleteRule): void
    {
        if (! in_array($deleteRule, ['RESTRICT', 'CASCADE'], true)) {
            throw new RuntimeException('Invalid foreign key delete rule.');
        }

        $table = $foreignKey['table'];
        $constraint = $foreignKey['constraint'];
        $column = $foreignKey['column'];
        $referencedTable = $foreignKey['referenced_table'];

        // Confirm the exact existing relationship before changing it.
        $existing = DB::selectOne(
            <<<'SQL'
                SELECT
                    c.confdeltype,
                    c.convalidated,
                    referenced.relname AS referenced_table,
                    (
                        SELECT source_column.attname
                        FROM unnest(c.conkey) WITH ORDINALITY AS key_column(attnum, position)
                        JOIN pg_attribute AS source_column
                          ON source_column.attrelid = c.conrelid
                         AND source_column.attnum = key_column.attnum
                        ORDER BY key_column.position
                        LIMIT 1
                    ) AS source_column,
                    (
                        SELECT referenced_column.attname
                        FROM unnest(c.confkey) WITH ORDINALITY AS key_column(attnum, position)
                        JOIN pg_attribute AS referenced_column
                          ON referenced_column.attrelid = c.confrelid
                         AND referenced_column.attnum = key_column.attnum
                        ORDER BY key_column.position
                        LIMIT 1
                    ) AS referenced_column
                FROM pg_constraint AS c
                JOIN pg_class AS source_table
                  ON source_table.oid = c.conrelid
                JOIN pg_namespace AS source_schema
                  ON source_schema.oid = source_table.relnamespace
                JOIN pg_class AS referenced
                  ON referenced.oid = c.confrelid
                JOIN pg_namespace AS referenced_schema
                  ON referenced_schema.oid = referenced.relnamespace
                WHERE source_schema.nspname = 'public'
                  AND referenced_schema.nspname = 'public'
                  AND source_table.relname = ?
                  AND c.conname = ?
                  AND c.contype = 'f'
                SQL,
            [$table, $constraint]
        );

        if (! $existing) {
            throw new RuntimeException(
                "Expected foreign key {$constraint} was not found on public.{$table}."
            );
        }

        if (
            $existing->referenced_table !== $referencedTable
            || $existing->source_column !== $column
            || $existing->referenced_column !== 'id'
        ) {
            throw new RuntimeException(
                "Foreign key {$constraint} does not match the expected relationship."
            );
        }

        if (! $existing->convalidated) {
            throw new RuntimeException(
                "Foreign key {$constraint} is not validated. Manual review is required."
            );
        }

        $desiredDeleteType = $deleteRule === 'RESTRICT' ? 'r' : 'c';

        if ($existing->confdeltype === $desiredDeleteType) {
            return;
        }

        DB::statement(
            "ALTER TABLE public.\"{$table}\" DROP CONSTRAINT \"{$constraint}\""
        );

        DB::statement(
            "ALTER TABLE public.\"{$table}\" "
            . "ADD CONSTRAINT \"{$constraint}\" "
            . "FOREIGN KEY (\"{$column}\") "
            . "REFERENCES public.\"{$referencedTable}\" (\"id\") "
            . "ON DELETE {$deleteRule} ON UPDATE NO ACTION"
        );
    }
};
