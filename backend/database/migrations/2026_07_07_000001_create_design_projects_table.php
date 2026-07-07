<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Design projects: one row per editor project (the ?project= uuid). Written at
// Review, owned by the session until a login claims it — powers "My designs"
// in the account and edit-from-cart across sessions.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->string('product_slug');
            $table->string('product_name');
            $table->string('preview')->nullable();  // stored URL of the latest review preview
            $table->string('design_path');          // designs/YYYYMM/{uuid}.json on the public disk
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_projects');
    }
};
