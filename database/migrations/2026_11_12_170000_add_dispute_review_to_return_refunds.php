<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('return_refunds', function (Blueprint $table) {
            $table->timestamp('dispute_reviewed_at')->nullable();
            $table->timestamp('dispute_proof_requested_at')->nullable();
            $table->string('dispute_proof_path')->nullable();
            $table->timestamp('dispute_proof_submitted_at')->nullable();
            $table->timestamp('dispute_resolved_at')->nullable();
            $table->text('dispute_resolution')->nullable();
            $table->foreignId('dispute_resolved_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('return_refunds', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dispute_resolved_by');
            $table->dropColumn([
                'dispute_reviewed_at', 'dispute_proof_requested_at', 'dispute_proof_path',
                'dispute_proof_submitted_at', 'dispute_resolved_at', 'dispute_resolution',
            ]);
        });
    }
};
