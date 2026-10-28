<?php

namespace Tests\Feature;

use App\Models\Shipment;
use App\Services\HubTransferService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class HubTransferServiceTest extends BaseTestCase
{
    public function createApplication()
    {
        $app = require __DIR__ . '/../../bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        app('db')->purge('sqlite');
        app('db')->reconnect('sqlite');

        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hub_id')->nullable();
            $table->unsignedBigInteger('origin_hub_id')->nullable();
            $table->unsignedBigInteger('destination_hub_id')->nullable();
            $table->string('routing_status')->default('awaiting_pickup');
            $table->timestamp('origin_hub_received_at')->nullable();
            $table->timestamp('transfer_dispatched_at')->nullable();
            $table->timestamp('destination_hub_received_at')->nullable();
            $table->string('status')->default('pending');
            $table->string('sorting_status')->default('pending');
            $table->boolean('received_by_sorting_center')->default(false);
            $table->string('qr_token')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('at_sorting_center_at')->nullable();
            $table->unsignedBigInteger('rider_id')->nullable();
            $table->timestamps();
        });
    }

    public function test_parcel_moves_from_origin_to_destination_hub(): void
    {
        $shipment = Shipment::create([
            'hub_id' => 10,
            'origin_hub_id' => 10,
            'destination_hub_id' => 20,
            'routing_status' => 'at_origin_hub',
            'origin_hub_received_at' => now(),
            'status' => 'at_sorting_center',
            'sorting_status' => 'pending',
            'received_by_sorting_center' => false,
        ]);

        HubTransferService::dispatch($shipment);

        $shipment->refresh();
        $this->assertSame('in_hub_transfer', $shipment->routing_status);
        $this->assertSame('in_transit', $shipment->status);
        $this->assertNotNull($shipment->transfer_dispatched_at);
        $this->assertFalse($shipment->received_by_sorting_center);

        HubTransferService::receiveAtDestination($shipment);

        $shipment->refresh();
        $this->assertSame(20, (int) $shipment->hub_id);
        $this->assertSame('at_destination_hub', $shipment->routing_status);
        $this->assertSame('at_sorting_center', $shipment->status);
        $this->assertSame('received', $shipment->sorting_status);
        $this->assertTrue($shipment->received_by_sorting_center);
        $this->assertNotNull($shipment->destination_hub_received_at);
    }

    public function test_cannot_receive_destination_before_dispatch(): void
    {
        $shipment = Shipment::create([
            'hub_id' => 10,
            'origin_hub_id' => 10,
            'destination_hub_id' => 20,
            'routing_status' => 'at_origin_hub',
            'origin_hub_received_at' => now(),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('dispatched');

        HubTransferService::receiveAtDestination($shipment);
    }

    public function test_sorting_is_blocked_until_destination_arrival(): void
    {
        $shipment = Shipment::create([
            'hub_id' => 10,
            'origin_hub_id' => 10,
            'destination_hub_id' => 20,
            'routing_status' => 'at_origin_hub',
            'origin_hub_received_at' => now(),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('destination hub');

        HubTransferService::ensureAtDestinationForSorting($shipment);
    }
}
