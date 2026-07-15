<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brand_kits', function (Blueprint $table) {
            // Generated homepage design for the "$10 website" upsell shown to
            // captures WITHOUT a website (in place of the Layout.ai ads offer).
            $table->string('website_preview_path')->nullable()->after('site_shot_path');
        });
    }

    public function down(): void
    {
        Schema::table('brand_kits', function (Blueprint $table) {
            $table->dropColumn('website_preview_path');
        });
    }
};
