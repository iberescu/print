<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brand_kits', function (Blueprint $table) {
            // A QR-code image captured from the QR builder — placed (with the logo,
            // if any) onto print products in the "your logo on products" step.
            $table->string('qr_path')->nullable()->after('logo_original_path');
        });
    }

    public function down(): void
    {
        Schema::table('brand_kits', function (Blueprint $table) {
            $table->dropColumn('qr_path');
        });
    }
};
