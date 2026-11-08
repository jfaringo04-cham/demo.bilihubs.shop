<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_revenue_entries', function (Blueprint $table) {
            $table->id();

            // service_fee or seller_commission
            $table->string('revenue_type', 40);

            // payment or commission
            $table->string('source_type', 40);
            $table->unsignedBigInteger('source_id');

            // earned or reversal
            $table->string('entry_type', 20)->default('earned');

            $table->foreignId('order_id')
                ->nullable()
                ->constrained('orders')
                ->restrictOnDelete();

            $table->foreignId('seller_order_id')
                ->nullable()
                ->constrained('seller_orders')
                ->restrictOnDelete();

            // Positive amount in centavos; reversals are separate entries.
            $table->bigInteger('amount_minor');

            $table->char('currency', 3)->default('PHP');

            $table->timestampTz('recognized_at');

            $table->foreignId('reversal_of_entry_id')
                ->nullable()
                ->unique()
                ->constrained('platform_revenue_entries')
                ->restrictOnDelete();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->unique(
                ['revenue_type', 'source_type', 'source_id', 'entry_type'],
                'platform_revenue_source_unique'
            );

            $table->index('order_id');
            $table->index('seller_order_id');
            $table->index('recognized_at');
            $table->index('revenue_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_revenue_entries');
    }
};
