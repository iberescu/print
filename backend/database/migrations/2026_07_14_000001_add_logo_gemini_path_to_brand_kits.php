<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brand_kits', function (Blueprint $table) {
            // Square-padded logo used ONLY for Gemini mockups (avoids aspect-warping).
            // logo_path stays the tight, non-square display logo (designer, QR centre).
            $table->string('logo_gemini_path')->nullable()->after('logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('brand_kits', function (Blueprint $table) {
            $table->dropColumn('logo_gemini_path');
        });
    }
};
