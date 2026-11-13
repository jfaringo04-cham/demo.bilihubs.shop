<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::transaction(function () {
            $constraint = DB::selectOne(<<<'SQL'
                SELECT pg_get_expr(conbin, conrelid) AS expression
                FROM pg_constraint
                WHERE conrelid = 'orders'::regclass
                  AND conname = 'orders_payment_status_check'
                  AND contype = 'c'
                SQL);

            if (!$constraint) {
                return;
            }

            // Preserve the statuses accepted by the existing constraint.
            $original = $constraint->expression;
            $quotedOriginal = DB::connection()->getPdo()->quote($original);

            DB::statement('ALTER TABLE orders DROP CONSTRAINT orders_payment_status_check');
            DB::statement(
                "ALTER TABLE orders ADD CONSTRAINT orders_payment_status_check
                 CHECK (({$original}) OR payment_status::text = 'partially_paid')"
            );
            DB::statement("COMMENT ON CONSTRAINT orders_payment_status_check ON orders IS {$quotedOriginal}");
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::transaction(function () {
            $constraint = DB::selectOne(<<<'SQL'
                SELECT obj_description(oid, 'pg_constraint') AS original_expression
                FROM pg_constraint
                WHERE conrelid = 'orders'::regclass
                  AND conname = 'orders_payment_status_check'
                  AND contype = 'c'
                SQL);

            if (!$constraint?->original_expression) {
                return;
            }

            DB::statement('ALTER TABLE orders DROP CONSTRAINT orders_payment_status_check');
            DB::statement(
                "ALTER TABLE orders ADD CONSTRAINT orders_payment_status_check
                 CHECK ({$constraint->original_expression})"
            );
        });
    }
};
