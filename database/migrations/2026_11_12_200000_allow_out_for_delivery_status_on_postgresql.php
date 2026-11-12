<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TABLES = ['shipments', 'seller_orders', 'orders'];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::transaction(function () {
            foreach (self::TABLES as $table) {
                $constraint = $this->statusConstraint($table);

                if (!$constraint) {
                    continue;
                }

                $backup = json_encode([
                    'out_for_delivery_original_expression' => $constraint->expression,
                    'comment' => $constraint->comment,
                ], JSON_THROW_ON_ERROR);

                $this->replaceConstraint(
                    $table,
                    "({$constraint->expression}) OR status::text = 'out_for_delivery'",
                    $backup
                );
            }
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::transaction(function () {
            foreach (array_reverse(self::TABLES) as $table) {
                $constraint = $this->statusConstraint($table);
                $backup = json_decode($constraint?->comment ?? '', true);
                $expression = $backup['out_for_delivery_original_expression'] ?? null;

                if (is_string($expression)) {
                    $this->replaceConstraint($table, $expression, $backup['comment']);
                }
            }
        });
    }

    private function statusConstraint(string $table): ?object
    {
        return DB::selectOne(<<<'SQL'
            SELECT pg_get_expr(conbin, conrelid) AS expression,
                   obj_description(oid, 'pg_constraint') AS comment
            FROM pg_constraint
            WHERE conrelid = to_regclass(?)
              AND conname = ?
              AND contype = 'c'
            SQL, [$table, $table . '_status_check']);
    }

    private function replaceConstraint(string $table, string $expression, ?string $comment): void
    {
        $name = $table . '_status_check';
        $quotedComment = $comment === null ? 'NULL' : DB::connection()->getPdo()->quote($comment);

        DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$name}");
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK ({$expression})");
        DB::statement("COMMENT ON CONSTRAINT {$name} ON {$table} IS {$quotedComment}");
    }
};
