<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Indices for the hot storefront queries (audit): category/product listings filter on
// (is_active, category_id), home on featured, the template gallery on
// (is_active, category, score), and account/dashboard look orders up by email.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->index(['is_active', 'category_id'], 'products_active_category_idx');
            $table->index(['is_active', 'featured'], 'products_active_featured_idx');
        });
        Schema::table('templates', function (Blueprint $table) {
            $table->index(['is_active', 'category', 'score'], 'templates_gallery_idx');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->index('email', 'orders_email_idx');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_active_category_idx');
            $table->dropIndex('products_active_featured_idx');
        });
        Schema::table('templates', fn (Blueprint $table) => $table->dropIndex('templates_gallery_idx'));
        Schema::table('orders', fn (Blueprint $table) => $table->dropIndex('orders_email_idx'));
    }
};
