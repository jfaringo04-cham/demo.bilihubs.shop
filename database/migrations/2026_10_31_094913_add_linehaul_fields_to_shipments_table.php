<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->foreignId('linehaul_rider_id')
                ->nullable()
                ->after('pickup_rider_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->string('required_vehicle_type', 30)
                ->nullable()
                ->after('linehaul_rider_id');

            $table->decimal('parcel_weight_kg', 10, 2)
                ->nullable()
                ->after('required_vehicle_type');

            $table->decimal('estimated_distance_km', 10, 2)
                ->nullable()
                ->after('parcel_weight_kg');

            $table->string('linehaul_status', 40)
                ->default('not_required')
                ->after('estimated_distance_km');

            $table->timestamp('linehaul_assigned_at')
                ->nullable()
                ->after('linehaul_status');

            $table->timestamp('linehaul_started_at')
                ->nullable()
                ->after('linehaul_assigned_at');

            $table->timestamp('linehaul_completed_at')
                ->nullable()
                ->after('linehaul_started_at');

            $table->index(
                ['logistic_id', 'linehaul_status'],
                'shipments_linehaul_status_index'
            );

            $table->index(
                ['origin_hub_id', 'required_vehicle_type'],
                'shipments_linehaul_vehicle_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropIndex('shipments_linehaul_vehicle_index');
            $table->dropIndex('shipments_linehaul_status_index');

            $table->dropConstrainedForeignId('linehaul_rider_id');

            $table->dropColumn([
                'required_vehicle_type',
                'parcel_weight_kg',
                'estimated_distance_km',
                'linehaul_status',
                'linehaul_assigned_at',
                'linehaul_started_at',
                'linehaul_completed_at',
            ]);
        });
    }
};
