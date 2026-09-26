<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('product_variations', 'product_variants');
    }

    public function down(): void
    {
        Schema::rename('product_variants', 'product_variations');
    }
};