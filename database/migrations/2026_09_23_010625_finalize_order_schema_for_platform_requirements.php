<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * 1. Orders: add the required unique reference.
         *
         * Keep order_number because the existing application still uses it.
         */
        if (!Schema::hasColumn('orders', 'reference')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('reference')->nullable();
            });
        }

        /*
         * Copy the existing order_number values into reference.
         */
        DB::statement("
            UPDATE orders
            SET reference = order_number
            WHERE reference IS NULL
        ");

        /*
         * reference is now required and unique.
         */
        DB::statement("
            ALTER TABLE orders
            ALTER COLUMN reference SET NOT NULL
        ");

        $orderReferenceIndexExists = collect(Schema::getIndexes('orders'))
            ->contains(fn ($index) =>
                $index['name'] === 'orders_reference_unique'
            );

        if (!$orderReferenceIndexExists) {
            Schema::table('orders', function (Blueprint $table) {
                $table->unique('reference', 'orders_reference_unique');
            });
        }

        /*
         * 2. Orders: shipping_address must be a JSON snapshot.
         *
         * Existing values are preserved inside:
         * {
         *     "address": "old text address"
         * }
         *
         * This avoids losing existing address information.
         */
        $ordersShippingType = DB::selectOne("
            SELECT data_type
            FROM information_schema.columns
            WHERE table_schema = current_schema()
              AND table_name = 'orders'
              AND column_name = 'shipping_address'
        ");

        if ($ordersShippingType && $ordersShippingType->data_type !== 'json') {
            DB::statement("
                ALTER TABLE orders
                ALTER COLUMN shipping_address TYPE json
                USING json_build_object(
                    'address',
                    shipping_address
                )
            ");
        }

        /*
         * 3. Shipments: add required unique tracking_code.
         *
         * Keep tracking_number because existing application code uses it.
         */
        if (!Schema::hasColumn('shipments', 'tracking_code')) {
            Schema::table('shipments', function (Blueprint $table) {
                $table->string('tracking_code')->nullable();
            });
        }

        /*
         * Copy existing tracking_number values.
         */
        DB::statement("
            UPDATE shipments
            SET tracking_code = tracking_number
            WHERE tracking_code IS NULL
        ");

        /*
         * Only enforce NOT NULL when every existing shipment has a tracking
         * number. This prevents the migration from failing on legacy rows
         * without tracking numbers.
         */
        $missingTrackingCodes = DB::table('shipments')
            ->whereNull('tracking_code')
            ->count();

        if ($missingTrackingCodes === 0) {
            DB::statement("
                ALTER TABLE shipments
                ALTER COLUMN tracking_code SET NOT NULL
            ");
        }

        $trackingCodeIndexExists = collect(Schema::getIndexes('shipments'))
            ->contains(fn ($index) =>
                $index['name'] === 'shipments_tracking_code_unique'
            );

        if (!$trackingCodeIndexExists) {
            Schema::table('shipments', function (Blueprint $table) {
                $table->unique('tracking_code', 'shipments_tracking_code_unique');
            });
        }
    }

    public function down(): void
    {
        /*
         * Remove tracking_code unique constraint/column.
         */
        if (Schema::hasColumn('shipments', 'tracking_code')) {
            Schema::table('shipments', function (Blueprint $table) {
                $table->dropUnique('shipments_tracking_code_unique');
            });

            Schema::table('shipments', function (Blueprint $table) {
                $table->dropColumn('tracking_code');
            });
        }

        /*
         * Convert shipping_address back to text.
         */
        if (Schema::hasColumn('orders', 'shipping_address')) {
            DB::statement("
                ALTER TABLE orders
                ALTER COLUMN shipping_address TYPE text
                USING shipping_address::text
            ");
        }

        /*
         * Remove reference.
         */
        if (Schema::hasColumn('orders', 'reference')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropUnique('orders_reference_unique');
                $table->dropColumn('reference');
            });
        }
    }
};