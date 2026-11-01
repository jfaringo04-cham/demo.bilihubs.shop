<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('linehaul_trips', function (Blueprint $table) {
            $table->id();

            $table->foreignId('logistic_id')
                ->constrained('logistics')
                ->cascadeOnDelete();

            $table->foreignId('origin_hub_id')
                ->constrained('hubs')
                ->restrictOnDelete();

            $table->foreignId('destination_hub_id')
                ->constrained('hubs')
                ->restrictOnDelete();

            $table->foreignId('driver_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('trip_number')->unique();

            $table->string('status')->default('loading');

            // Minimum number of parcels before early dispatch is allowed.
            $table->unsignedInteger('minimum_load')->default(10);

            // Snapshot of the truck's available capacity when trip is created.
            $table->unsignedInteger('capacity_limit');

            // Cutoff time. After this time, the truck may leave even
            // if minimum_load has not been reached.
            $table->timestamp('scheduled_departure_at');

            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('arrived_at')->nullable();

            $table->timestamps();

            $table->index([
                'logistic_id',
                'origin_hub_id',
                'destination_hub_id',
                'status',
            ], 'linehaul_trip_route_status_idx');
        });

        Schema::create('linehaul_trip_shipments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('linehaul_trip_id')
                ->constrained('linehaul_trips')
                ->cascadeOnDelete();

            $table->foreignId('shipment_id')
                ->constrained('shipments')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique('shipment_id');

            $table->index('linehaul_trip_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('linehaul_trip_shipments');
        Schema::dropIfExists('linehaul_trips');
    }
};
