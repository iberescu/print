<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brand_kits', function (Blueprint $table) {
            // SpyFu top competitors for URL captures — feeds the ads-step market
            // simulation (fetched in the background, null when no data/API access).
            $table->json('competitors')->nullable()->after('summary');
        });
    }

    public function down(): void
    {
        Schema::table('brand_kits', function (Blueprint $table) {
            $table->dropColumn('competitors');
        });
    }
};
