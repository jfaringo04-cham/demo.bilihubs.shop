<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('name');
            $table->string('middle_name')->nullable()->after('first_name');
            $table->string('last_name')->nullable()->after('middle_name');
            $table->string('sex')->nullable()->after('last_name');
            $table->date('birthday')->nullable()->after('sex');
            $table->integer('age')->nullable()->after('birthday');
            $table->string('house_number')->nullable()->after('age');
            $table->string('street_address')->nullable()->after('house_number');
            $table->string('barangay')->nullable()->after('street_address');
            $table->string('municipality')->nullable()->after('barangay');
            $table->string('province')->nullable()->after('municipality');
            $table->string('business_name')->nullable()->after('province');
            $table->string('business_permit')->nullable()->after('business_name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'first_name', 'middle_name', 'last_name', 'sex', 'birthday', 'age',
                'house_number', 'street_address', 'barangay', 'municipality', 'province',
                'business_name', 'business_permit'
            ]);
        });
    }
};
