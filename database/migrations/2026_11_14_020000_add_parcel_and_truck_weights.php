<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('packed_weight_kg', 7, 3)->nullable();
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('packed_weight_kg', 7, 3)->nullable();
        });
        Schema::table('shipments', function (Blueprint $table) {
            $table->decimal('estimated_weight_kg', 14, 3)->nullable();
            $table->decimal('verified_weight_kg', 8, 3)->nullable();
        });
        Schema::table('linehaul_trips', function (Blueprint $table) {
            $table->decimal('payload_limit_kg', 8, 3)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('linehaul_trips', fn (Blueprint $table) => $table->dropColumn('payload_limit_kg'));
        Schema::table('shipments', fn (Blueprint $table) => $table->dropColumn(['estimated_weight_kg', 'verified_weight_kg']));
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('packed_weight_kg'));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('packed_weight_kg'));
    }
};
