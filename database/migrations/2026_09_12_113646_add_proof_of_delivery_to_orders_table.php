<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('proof_of_delivery')->nullable()->after('delivered_at');
            $table->text('delivery_signature')->nullable()->after('proof_of_delivery');
            $table->string('delivered_to')->nullable()->after('delivery_signature');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['proof_of_delivery', 'delivery_signature', 'delivered_to']);
        });
    }
};
