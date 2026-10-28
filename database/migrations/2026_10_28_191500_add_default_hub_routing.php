<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hubs', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('status');
            $table->index(['logistic_id', 'status', 'is_default'], 'hubs_default_routing_index');
        });
    }

    public function down(): void
    {
        Schema::table('hubs', function (Blueprint $table) {
            $table->dropIndex('hubs_default_routing_index');
            $table->dropColumn('is_default');
        });
    }
};
