<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // AI-generated material/finish preview shown on the final-step page
        // (options:previews fills it; Img::url() resolves the extension).
        Schema::table('option_values', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('swatch');
        });
    }

    public function down(): void
    {
        Schema::table('option_values', fn (Blueprint $table) => $table->dropColumn('image_path'));
    }
};
