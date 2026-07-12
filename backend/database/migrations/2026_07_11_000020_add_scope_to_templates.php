<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            // Scope a template to a specific product (null = whole category, legacy)
            // and an orientation/shape (landscape|portrait|square|null).
            $table->string('product_slug')->nullable()->after('category');
            $table->string('orientation')->nullable()->after('product_slug');
            $table->index(['product_slug', 'orientation']);
        });
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropIndex(['product_slug', 'orientation']);
            $table->dropColumn(['product_slug', 'orientation']);
        });
    }
};
