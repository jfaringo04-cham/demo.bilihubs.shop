<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hubs', function (Blueprint $table) {
            $table->string('barangay')->nullable()->after('api_address');
            $table->string('city')->nullable()->after('barangay');
            $table->string('province')->nullable()->after('city');

            $table->index(
                ['logistic_id', 'status', 'province', 'city', 'barangay'],
                'hubs_barangay_coverage_index'
            );
        });

        Schema::table('addresses', function (Blueprint $table) {
            $table->string('barangay')->nullable()->after('address_line2');
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->foreignId('origin_hub_id')
                ->nullable()
                ->after('hub_id')
                ->constrained('hubs')
                ->nullOnDelete();

            $table->foreignId('destination_hub_id')
                ->nullable()
                ->after('origin_hub_id')
                ->constrained('hubs')
                ->nullOnDelete();

            $table->string('routing_status')
                ->default('awaiting_pickup')
                ->after('destination_hub_id');

            $table->timestamp('origin_hub_received_at')->nullable();
            $table->timestamp('transfer_dispatched_at')->nullable();
            $table->timestamp('destination_hub_received_at')->nullable();

            $table->index(
                ['logistic_id', 'origin_hub_id', 'destination_hub_id'],
                'shipments_hub_route_index'
            );

            $table->index(
                ['routing_status', 'status'],
                'shipments_routing_status_index'
            );
        });

        // Backward compatibility for shipments created before barangay routing.
        DB::table('shipments')
            ->whereNull('origin_hub_id')
            ->whereNotNull('hub_id')
            ->update([
                'origin_hub_id' => DB::raw('hub_id'),
                'destination_hub_id' => DB::raw('hub_id'),
            ]);
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropIndex('shipments_routing_status_index');
            $table->dropIndex('shipments_hub_route_index');

            $table->dropConstrainedForeignId('destination_hub_id');
            $table->dropConstrainedForeignId('origin_hub_id');

            $table->dropColumn([
                'routing_status',
                'origin_hub_received_at',
                'transfer_dispatched_at',
                'destination_hub_received_at',
            ]);
        });

        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn('barangay');
        });

        Schema::table('hubs', function (Blueprint $table) {
            $table->dropIndex('hubs_barangay_coverage_index');
            $table->dropColumn([
                'barangay',
                'city',
                'province',
            ]);
        });
    }
};
