<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->string('ref')->unique();              // generator id, e.g. "001"
            $table->string('name');
            $table->string('category')->default('business-cards');
            $table->string('style')->nullable();
            $table->string('font')->nullable();
            $table->decimal('score', 3, 1)->nullable();   // Gemini quality score
            $table->json('data');                          // fabric.js canvas JSON
            $table->string('preview_path')->nullable();    // public-disk path
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
