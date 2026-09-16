<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Fix stale auto-generated CHECK constraints from enum() columns
        // that may restrict values incorrectly on PostgreSQL.
        // When Laravel's enum() was used, PostgreSQL auto-generates
        // CHECK constraints named {table}_{column}_check with a limited
        // set of values. Later migrations that broaden the allowed values
        // need these stale constraints dropped first.

        $tablesToFix = [
            'users' => [
                'auto_constraints' => ['users_role_check'],
                'named_constraints' => ['role_check'],
                'correct_check' => "CHECK (role IN ('customer','seller','admin','rider','logistic_owner','guest'))",
            ],
        ];

        foreach ($tablesToFix as $table => $config) {
            foreach ($config['auto_constraints'] as $name) {
                DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$name};");
            }
            foreach ($config['named_constraints'] as $name) {
                DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$name};");
            }
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$config['named_constraints'][0]} {$config['correct_check']};");
        }

        // Also drop any auto-generated CHECK constraints on other tables
        // that may have been created from enum() columns and could block
        // values allowed by later migrations
        $extraDrops = [
            ['products', 'products_compliance_status_check'],
            ['orders', 'orders_status_check'],
            ['orders', 'orders_delivery_status_check'],
            ['orders', 'orders_return_status_check'],
            ['orders', 'orders_payment_method_check'],
            ['orders', 'orders_payment_status_check'],
            ['shipments', 'shipments_status_check'],
            ['shipments', 'shipments_delivery_type_check'],
            ['shipments', 'shipments_sorting_status_check'],
            ['support_tickets', 'support_tickets_status_check'],
            ['support_tickets', 'support_tickets_type_check'],
            ['support_tickets', 'support_tickets_priority_check'],
            ['commissions', 'commissions_status_check'],
            ['logistics', 'logistics_status_check'],
            ['hubs', 'hubs_status_check'],
        ];

        foreach ($extraDrops as [$table, $name]) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$name};");
        }
    }

    public function down(): void
    {
        // No rollback needed — enum() constraints are intentionally removed
    }
};
