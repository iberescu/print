<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brand_kits', function (Blueprint $table) {
            // The pristine uploaded logo, kept untouched alongside the working
            // (resized/upscaled) logo_path used by mockups and ads.
            $table->string('logo_original_path')->nullable()->after('logo_url');
        });
    }

    public function down(): void
    {
        Schema::table('brand_kits', function (Blueprint $table) {
            $table->dropColumn('logo_original_path');
        });
    }
};
