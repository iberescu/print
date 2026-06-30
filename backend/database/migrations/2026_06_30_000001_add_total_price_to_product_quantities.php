<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_quantities', function (Blueprint $table) {
            // Exact crawled total for a tier (e.g. Vistaprint 100 cards = $14.99).
            // Preferred over qty × unit_price, which can't hold .99 totals at scale.
            $table->decimal('total_price', 10, 2)->nullable()->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('product_quantities', function (Blueprint $table) {
            $table->dropColumn('total_price');
        });
    }
};
