<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::table('users', function (Blueprint $table) {
                $table->string('role_temp')->default('customer')->after('email');
            });

            DB::table('users')->update(['role_temp' => DB::raw('role')]);

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role');
            });

            Schema::table('users', function (Blueprint $table) {
                $table->enum('role', ['customer', 'seller', 'admin', 'rider', 'logistic_owner'])->default('customer')->after('email');
            });

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role_temp');
            });
        } else {
            Schema::table('users', function (Blueprint $table) {
                $table->enum('role', ['customer', 'seller', 'admin', 'rider', 'logistic_owner'])->default('customer')->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::table('users', function (Blueprint $table) {
                $table->string('role_temp')->default('customer')->after('email');
            });

            DB::table('users')->update(['role_temp' => DB::raw('role')]);

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role');
            });

            Schema::table('users', function (Blueprint $table) {
                $table->enum('role', ['customer', 'seller', 'admin', 'rider'])->default('customer')->after('email');
            });

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role_temp');
            });
        } else {
            Schema::table('users', function (Blueprint $table) {
                $table->enum('role', ['customer', 'seller', 'admin', 'rider'])->default('customer')->change();
            });
        }
    }
};
