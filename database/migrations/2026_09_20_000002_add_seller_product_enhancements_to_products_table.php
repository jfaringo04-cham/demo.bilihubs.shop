<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('subcategory_id')->nullable()->after('category_id')->constrained('categories')->nullOnDelete();
            $table->string('sku')->nullable()->after('price');
            $table->integer('low_stock_threshold')->nullable()->after('sku');
            $table->json('attributes')->nullable()->after('low_stock_threshold');
            $table->string('status', 20)->default('published')->after('compliance_status');
        });

        DB::table('products')->whereNull('status')->update(['status' => 'published']);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['subcategory_id', 'sku', 'low_stock_threshold', 'attributes', 'status']);
        });
    }
};
