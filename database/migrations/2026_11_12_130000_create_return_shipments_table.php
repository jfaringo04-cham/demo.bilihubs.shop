<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('return_shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_order_id')->unique()->constrained();
            $table->foreignId('logistic_id')->constrained('logistics');
            $table->foreignId('rider_id')->nullable()->constrained('users');
            $table->string('tracking_number')->unique();
            $table->text('pickup_address');
            $table->text('delivery_address');
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('handover_photo')->nullable();
            $table->timestamps();
            $table->index(['logistic_id', 'delivered_at']);
            $table->index(['rider_id', 'delivered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_shipments');
    }
};
