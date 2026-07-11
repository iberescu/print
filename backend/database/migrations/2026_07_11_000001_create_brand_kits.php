<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_kits', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();          // reuses the session pqsg.key
            $table->string('source')->default('designer'); // designer|image|pdf|logo-maker
            $table->string('status')->default('pending');   // pending|processing|complete|failed

            // brand inputs
            $table->string('logo_path')->nullable();  // public-disk path
            $table->string('logo_url')->nullable();   // absolute URL
            $table->string('website')->nullable();
            $table->string('company')->nullable();
            $table->string('source_file')->nullable(); // uploaded image/pdf path (for extraction)

            // derived / generated (streamed in as jobs finish)
            $table->json('extract')->nullable();     // raw Gemini extraction of image/pdf
            $table->json('summary')->nullable();     // {description, keywords[], fonts[], google_search_keywords[]}
            $table->longText('crawl_text')->nullable();
            $table->json('products')->nullable();    // [{key,label,img,product_slug}]
            $table->json('ads')->nullable();         // [{key,img}]
            $table->json('stages')->nullable();      // {extract,summary,products,ads => pending|running|done|failed}

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_kits');
    }
};
