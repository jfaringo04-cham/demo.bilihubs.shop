<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'rider_id')) {
                $table->unsignedBigInteger('rider_id')->nullable()->after('user_id');
            }
        });

        try {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreign('rider_id')->references('id')->on('users')->nullOnDelete();
            });
        } catch (\Exception $e) {
            // Foreign key already exists
        }

        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'delivery_status')) {
                $table->enum('delivery_status', ['pending', 'assigned', 'delivered_to_sorting_center', 'ready_for_delivery_pickup', 'picked_up_from_sorting_center', 'on_the_way', 'delivered', 'failed'])->default('pending')->after('return_status');
            }
            if (!Schema::hasColumn('orders', 'proof_type')) {
                $table->string('proof_type')->nullable()->after('delivery_status');
            }
            if (!Schema::hasColumn('orders', 'proof_data')) {
                $table->text('proof_data')->nullable()->after('proof_type');
            }
            if (!Schema::hasColumn('orders', 'delivery_notes')) {
                $table->text('delivery_notes')->nullable()->after('proof_data');
            }
            if (!Schema::hasColumn('orders', 'assigned_at')) {
                $table->timestamp('assigned_at')->nullable()->after('delivery_notes');
            }
            if (!Schema::hasColumn('orders', 'failed_at')) {
                $table->timestamp('failed_at')->nullable()->after('delivered_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['rider_id']);
            $table->dropColumn([
                'rider_id', 'delivery_status', 'proof_type', 'proof_data',
                'delivery_notes', 'assigned_at', 'delivered_at', 'failed_at'
            ]);
        });
    }
};
