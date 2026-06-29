<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('tagline')->nullable();
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('tagline')->nullable();
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();   // primary product photo
            $table->json('gallery')->nullable();         // extra images
            $table->decimal('from_price', 10, 2)->default(0); // "From $X" display
            $table->string('badge')->nullable();         // e.g. "Bestseller"
            $table->boolean('supports_design')->default(true);
            $table->boolean('supports_upload')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('product_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name');                       // "Paper Stock", "Corners", "Size"
            $table->string('type')->default('select');    // select | radio | swatch
            $table->boolean('required')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('option_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_option_id')->constrained()->cascadeOnDelete();
            $table->string('label');                      // "Matte", "Rounded"
            $table->decimal('price_delta', 10, 2)->default(0); // added to order total
            $table->string('description')->nullable();
            $table->string('badge')->nullable();          // "Recommended"
            $table->string('swatch')->nullable();         // hex for color swatches
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('product_quantities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 10, 4);         // per-unit price at this tier
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_quantities');
        Schema::dropIfExists('option_values');
        Schema::dropIfExists('product_options');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
    }
};
