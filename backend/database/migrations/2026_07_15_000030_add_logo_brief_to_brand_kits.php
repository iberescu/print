<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brand_kits', function (Blueprint $table) {
            // The AI-logo-maker brief travels with the capture: the website preview
            // and the no-URL summary use the real industry/slogan instead of guessing.
            $table->string('industry')->nullable()->after('company');
            $table->string('slogan')->nullable()->after('industry');
        });
    }

    public function down(): void
    {
        Schema::table('brand_kits', function (Blueprint $table) {
            $table->dropColumn(['industry', 'slogan']);
        });
    }
};
