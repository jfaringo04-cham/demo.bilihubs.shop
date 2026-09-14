<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete()->after('id');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete()->after('category_id');
            $table->text('description')->nullable()->after('name');
            $table->integer('stock')->default(0)->after('description');
            $table->string('image_path')->nullable()->after('image');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropForeign(['user_id']);
            $table->dropColumn(['category_id', 'user_id', 'description', 'stock', 'image_path', 'deleted_at']);
        });
    }
};
