<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brand_kits', function (Blueprint $table) {
            // per-keyword monthly-traffic stats for the ads-step keyword report
            $table->json('keyword_stats')->nullable()->after('summary');
            // competitors were replaced by the keyword report before ever going live
            $table->dropColumn('competitors');
        });
    }

    public function down(): void
    {
        Schema::table('brand_kits', function (Blueprint $table) {
            $table->dropColumn('keyword_stats');
            $table->json('competitors')->nullable();
        });
    }
};
