<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_id');
            $table->string('actor_name');
            $table->string('area', 60);
            $table->string('action', 100);
            $table->json('targets');
            $table->string('outcome', 20);
            $table->unsignedSmallInteger('http_status');
            $table->timestamp('occurred_at');

            $table->index('occurred_at');
            $table->index(['actor_id', 'occurred_at']);
            $table->index(['area', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_activity_logs');
    }
};
