
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('buyer_voucher_claims', function (Blueprint $table) {
            $table->id();

            // Existing voucher
            $table->foreignId('voucher_id')
                ->constrained('vouchers')
                ->cascadeOnDelete();

            // Buyer who claimed the voucher
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Date and time the voucher was claimed
            $table->timestamp('claimed_at')->useCurrent();

            $table->timestamps();

            // Prevent duplicate claims
            $table->unique(
                ['voucher_id', 'user_id'],
                'buyer_voucher_claims_unique'
            );

            // Faster lookup of buyer vouchers
            $table->index(['user_id', 'claimed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('buyer_voucher_claims');
    }
};
