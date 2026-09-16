<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Palitan ang enum ng string + default
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('customer')->change();
        });

        // Drop any existing CHECK constraints on role column (handles both
        // PostgreSQL auto-generated names and named constraints)
        DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS role_check;");
        DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check;");

        // Add CHECK constraint with all valid roles
        DB::statement("ALTER TABLE users ADD CONSTRAINT role_check 
            CHECK (role IN ('customer','seller','admin','rider','logistic_owner','guest'));");
    }

    public function down(): void
    {
        // Rollback: balik sa simpleng string + default
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('customer')->change();
        });

        // Drop constraint kapag rollback
        DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS role_check;");
    }
};

