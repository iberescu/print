<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brand_kits', function (Blueprint $table) {
            // Full-render homepage screenshot (css/images/js) used as a visual style
            // reference for the website-styled display ad.
            $table->string('site_shot_path')->nullable()->after('crawl_text');
        });
    }

    public function down(): void
    {
        Schema::table('brand_kits', function (Blueprint $table) {
            $table->dropColumn('site_shot_path');
        });
    }
};
