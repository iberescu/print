<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_quantities', function (Blueprint $table) {
            // pre-discount price, so a reduced tier can show a strikethrough / "save %"
            $table->decimal('compare_at_total', 10, 2)->nullable()->after('total_price');
        });
    }

    public function down(): void
    {
        Schema::table('product_quantities', function (Blueprint $table) {
            $table->dropColumn('compare_at_total');
        });
    }
};
