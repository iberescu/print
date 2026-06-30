<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surfaces', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('unit', 8)->default('mm');     // mm | in
            $table->decimal('width', 8, 2);
            $table->decimal('height', 8, 2);
            $table->decimal('bleed', 6, 2)->default(0);
            $table->decimal('safety', 6, 2)->default(0);
            $table->json('no_print_areas')->nullable();    // [{label,x,y,w,h}] in unit, trim-relative
            $table->json('fold_lines')->nullable();         // [{label,orientation,position}]
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Per-value extra info (weight, thickness, width/height, …) + optional surface.
        Schema::table('option_values', function (Blueprint $table) {
            $table->json('attributes')->nullable()->after('description'); // [{name,value}]
            $table->foreignId('surface_id')->nullable()->after('attributes')->constrained()->nullOnDelete();
        });

        // A product's default print surface (used by the online designer).
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('surface_id')->nullable()->after('category_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('surface_id');
        });
        Schema::table('option_values', function (Blueprint $table) {
            $table->dropConstrainedForeignId('surface_id');
            $table->dropColumn('attributes');
        });
        Schema::dropIfExists('surfaces');
    }
};
