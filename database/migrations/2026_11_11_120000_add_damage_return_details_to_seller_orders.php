<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seller_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('return_order_item_id')->nullable();
            $table->unsignedInteger('return_quantity')->nullable();
            $table->string('return_photo_path')->nullable();
            $table->timestamp('return_received_at')->nullable();
            $table->string('return_resolution', 20)->nullable();
            $table->timestamp('return_resolved_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('seller_orders', function (Blueprint $table) {
            $table->dropColumn([
                'return_order_item_id', 'return_quantity', 'return_photo_path',
                'return_received_at', 'return_resolution', 'return_resolved_at',
            ]);
        });
    }
};
