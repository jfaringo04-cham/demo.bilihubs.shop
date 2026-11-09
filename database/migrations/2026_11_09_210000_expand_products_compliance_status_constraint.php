<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ALLOWED_STATUSES = [
        'pending',
        'approved',
        'flagged',
        'rejected',
        'auto_flagged',
    ];

    private const LEGACY_STATUSES = [
        'pending',
        'approved',
        'flagged',
    ];

    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement(
                'ALTER TABLE products
                 DROP CONSTRAINT IF EXISTS products_compliance_status_check'
            );

            DB::statement("
                ALTER TABLE products
                ADD CONSTRAINT products_compliance_status_check
                CHECK (
                    compliance_status IN (
                        'pending',
                        'approved',
                        'flagged',
                        'rejected',
                        'auto_flagged'
                    )
                )
            ");

            DB::statement("
                ALTER TABLE products
                ALTER COLUMN compliance_status SET DEFAULT 'pending'
            ");

            return;
        }

        if ($driver === 'mysql') {
            DB::statement("
                ALTER TABLE products
                MODIFY COLUMN compliance_status
                ENUM(
                    'pending',
                    'approved',
                    'flagged',
                    'rejected',
                    'auto_flagged'
                )
                NOT NULL DEFAULT 'pending'
            ");

            return;
        }

        throw new RuntimeException(
            "Unsupported database driver for compliance migration: {$driver}"
        );
    }

    public function down(): void
    {
        $unsupported = DB::table('products')
            ->whereNotIn('compliance_status', self::LEGACY_STATUSES)
            ->exists();

        if ($unsupported) {
            throw new RuntimeException(
                'Rollback blocked: products contain compliance statuses '
                . 'that are not supported by the legacy constraint.'
            );
        }

        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement(
                'ALTER TABLE products
                 DROP CONSTRAINT IF EXISTS products_compliance_status_check'
            );

            DB::statement("
                ALTER TABLE products
                ADD CONSTRAINT products_compliance_status_check
                CHECK (
                    compliance_status IN (
                        'pending',
                        'approved',
                        'flagged'
                    )
                )
            ");

            DB::statement("
                ALTER TABLE products
                ALTER COLUMN compliance_status SET DEFAULT 'pending'
            ");

            return;
        }

        if ($driver === 'mysql') {
            DB::statement("
                ALTER TABLE products
                MODIFY COLUMN compliance_status
                ENUM('pending', 'approved', 'flagged')
                NOT NULL DEFAULT 'pending'
            ");

            return;
        }

        throw new RuntimeException(
            "Unsupported database driver for compliance rollback: {$driver}"
        );
    }
};