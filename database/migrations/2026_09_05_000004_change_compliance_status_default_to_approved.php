<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Palitan ang compliance_status ng string + default
        Schema::table('products', function (Blueprint $table) {
            $table->string('compliance_status')->default('approved')->change();
        });

        // Drop old auto-generated CHECK constraint from enum() (PostgreSQL)
        DB::statement("ALTER TABLE products DROP CONSTRAINT IF EXISTS products_compliance_status_check;");
        DB::statement("ALTER TABLE products DROP CONSTRAINT IF EXISTS compliance_status_check;");

        // Optional: CHECK constraint para limit values sa Postgres
        DB::statement("ALTER TABLE products ADD CONSTRAINT compliance_status_check 
            CHECK (compliance_status IN ('pending','approved','flagged','rejected','auto_flagged'));");

        // Update existing rows kung naka-pending pa
        DB::table('products')
            ->where('compliance_status', 'pending')
            ->update(['compliance_status' => 'approved']);
    }

    public function down(): void
    {
        // Rollback: balik sa string + default 'pending'
        Schema::table('products', function (Blueprint $table) {
            $table->string('compliance_status')->default('pending')->change();
        });

        // Drop constraint kapag rollback
        DB::statement("ALTER TABLE products DROP CONSTRAINT IF EXISTS compliance_status_check;");
    }
};
