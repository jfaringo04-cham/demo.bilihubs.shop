<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_price_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->decimal('price', 12, 2);
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index(['product_id', 'changed_at']);
        });

        Schema::create('product_listing_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamp('snapshot_at');
            $table->timestamps();

            $table->index(['product_id', 'snapshot_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_listing_snapshots');
        Schema::dropIfExists('product_price_history');
    }
};
